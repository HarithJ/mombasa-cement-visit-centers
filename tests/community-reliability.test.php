<?php
declare(strict_types=1);
require __DIR__.'/../src/SchoolEmailWorker.php';
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/nyumba-school-reliability-'.bin2hex(random_bytes(6));
try{
 $db=CommunityStore::open($dir.'/db');$input=['name'=>'Example Teacher','relationship'=>'Teacher','email'=>'teacher@example.com','phone'=>'+254712345678','school'=>'Example School','county'=>'Kilifi','locality'=>'Town','support'=>'classroom','description'=>'A classroom.'];
 $first=SchoolRequest::save($db,$input,'one');$again=SchoolRequest::save($db,$input,'one');check($first['reference']===$again['reference'],'Repeated submission is one request');
 check((int)$db->query('SELECT COUNT(*) FROM school_notifications')->fetchColumn()===1,'Repeated submission retains one notification');
 $db->exec("CREATE TRIGGER fail_outbox BEFORE INSERT ON school_notifications BEGIN SELECT RAISE(ABORT,'test failure'); END");
 try{SchoolRequest::save($db,$input,'rollback');throw new RuntimeException('Expected persistence failure');}catch(PDOException $expected){}
 check((int)$db->query('SELECT COUNT(*) FROM school_requests')->fetchColumn()===1,'Outbox failure rolls back request');$db->exec('DROP TRIGGER fail_outbox');
 putenv('SCHOOL_EMAIL_ENABLED=0');$now=1800000000;$worker=new SchoolEmailWorker($db,fn()=>$now);
 check($worker->run(fn()=>throw new RuntimeException('No send when disabled'))['configuration_required']===1,'Unconfigured intent retained');
 foreach(['SCHOOL_EMAIL_ENABLED=1','SCHOOL_NOTIFICATION_TO=schools@example.com','RESEND_FROM=visits@example.com','RESEND_API_KEY=fixture','COMMUNITY_BASE_URL=https://nyumba.example']as $setting)putenv($setting);
 $worker=new SchoolEmailWorker($db,function()use(&$now){return $now;});$attempts=[];
 $result=$worker->run(function($payload,$key)use(&$attempts){$attempts[]=[$payload,$key];throw new RuntimeException('Network timeout');});check($result['retry']===1,'Provider failure scheduled');
 $result=$worker->run(fn()=>throw new RuntimeException('Not yet due'));check($result['retry']===0,'Backoff respected');
 $now+=61;$result=$worker->run(function($payload,$key)use(&$attempts,$worker){check([$payload,$key]===$attempts[0],'Stable retry payload and key');$nested=$worker->run(fn()=>throw new RuntimeException('Lease prevents second sender'));check($nested['accepted']===0,'Concurrent worker skips lease');return 'provider-1';});
 check($result['accepted']===1,'Retry accepted');
 SchoolRequest::save($db,$input,'two');$worker->run(fn()=>throw new RuntimeException('Timeout'));$now+=23*3600;
 check($worker->run(fn()=>throw new RuntimeException('Do not send beyond idempotency window'))['review']===1,'Old ambiguous jobs need review');
 check((int)$db->query('SELECT COUNT(*) FROM school_requests')->fetchColumn()===2,'Delivery failure does not delete requests');
 echo "PASS school outbox atomicity, replay, disabled config, backoff, leases, stable retries and expiry\n";
}finally{$db=null;if(is_file($dir.'/db'))unlink($dir.'/db');if(is_dir($dir))rmdir($dir);}
