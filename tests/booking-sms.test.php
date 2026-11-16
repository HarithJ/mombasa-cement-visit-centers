<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingStore.php';
require __DIR__.'/../src/DayBooking.php';
require __DIR__.'/../src/SmsWorker.php';
require_once __DIR__.'/../src/UserStore.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function response(string $body, int $status = 201): array { return ['status'=>$status,'body'=>$body]; }
function successTransport(string $url, array $headers, string $body): array {
    parse_str($body, $form);
    return response(json_encode(['SMSMessageData'=>['Recipients'=>[['number'=>$form['to'],'statusCode'=>101,'messageId'=>'test-id']]]]));
}
function client(?Closure $transport = null): AfricasTalkingClient {
    return new AfricasTalkingClient('sandbox','fake-key','sandbox','', $transport ?? successTransport(...));
}
$path = sys_get_temp_dir().'/nyumba-sms-'.bin2hex(random_bytes(8)).'/bookings.sqlite';
putenv('BOOKING_EMAIL_ENABLED=0'); putenv('FEEDBACK_EMAIL_ENABLED=0'); putenv('BOOKING_SMS_ENABLED=1'); putenv('BOOKING_SITE_URL=https://example.com');
try {
    $store = new BookingStore($path);
    // Five active admins get the new-booking SMS; team users and deactivated admins don't.
    $users = UserStore::open($path);
    foreach (['A','B','C','D','E'] as $i => $letter) $users->add(['name'=>"Admin $letter",'email'=>"admin$letter@example.test",'phone'=>'+25471100000'.($i+1),'role'=>'admin']);
    $users->add(['name'=>'Team Member','email'=>'team@example.test','phone'=>'+254711000009','role'=>'team']);
    $users->deactivate($users->add(['name'=>'Gone Admin','email'=>'gone@example.test','phone'=>'+254711000008','role'=>'admin'])['id']);
    $db = new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $schedule = require __DIR__.'/../config/destinations.php';
    $input = ['fullName'=>'Alex','phone'=>'0712345678','email'=>'','location'=>'galana','visitDate'=>'2099-05-01','timeSlot'=>'09:00','attendees'=>'3'];
    [$booking,$errors] = DayBooking::validate($input,$schedule);
    check(!$errors, 'Booking validates');
    foreach (['0712 345 678'=>'+254712345678','254712345678'=>'+254712345678','+44 20 7946 0958'=>'+442079460958','1234567'=>'1234567'] as $raw=>$expected) {
        [$value] = DayBooking::validate(array_merge($input,['phone'=>(string)$raw]),$schedule);
        check($value['phone'] === $expected && PhoneNumber::normalize((string)$raw) === $expected, 'Shared normalization preserves booking policy');
    }
    putenv('BOOKING_SMS_ENABLED=0');putenv('BOOKING_EMAIL_ENABLED=1');
    $legacy=$store->create($booking,'legacy');
    $emailSnapshot=$db->query('SELECT * FROM booking_emails')->fetchAll();
    $db->exec("DROP TABLE booking_sms; DROP TABLE sms_control; DELETE FROM schema_migrations WHERE version='008-booking-sms.sql'");
    $store=new BookingStore($path);
    check($store->findByToken('legacy')===$legacy && $emailSnapshot===$db->query('SELECT * FROM booking_emails')->fetchAll(),'Upgrade preserves existing bookings and email state');
    check((int)$db->query('SELECT COUNT(*) FROM booking_sms')->fetchColumn()===0,'Upgrade never backfills historical bookings');
    putenv('BOOKING_SMS_ENABLED=1');putenv('BOOKING_EMAIL_ENABLED=0');
    $saved = $store->create($booking,'first');
    $store->create($booking,'first');
    $jobs = $db->query('SELECT * FROM booking_sms')->fetchAll();
    check(count($jobs)===6, 'Visitor and five active admins once');
    check(array_column($jobs,'recipient') === ['+254712345678','+254711000001','+254711000002','+254711000003','+254711000004','+254711000005'], 'Visitor, then active admins on their account phones');
    $fixture = array_merge($saved,['reference'=>'NY-TEST']);
    check(BookingSms::visitorMessage($fixture) === "Hi Alex, thanks for your request to visit Galana Farm on 1 May 2099 at 9:00 am EAT. It isn't confirmed yet; we'll let you know shortly. Ref NY-TEST.", 'Approved day visitor text');
    foreach ([0,1] as $overnight) foreach ([0,1] as $transport) {
        $variant = array_merge($fixture,['overnight'=>$overnight,'transport_requested'=>$transport,'arrival_date'=>$overnight?'2099-05-01':null,'departure_date'=>$overnight?'2099-05-03':null,'overnight_guests'=>$overnight?2:null]);
        $visitor = BookingSms::visitorMessage($variant); $admin = BookingSms::adminMessage($variant);
        check(str_contains($admin,', 3 people'), 'Every admin scenario includes total attendees');
        check(!str_contains($visitor,'group') && !str_contains($visitor,'3 people'), 'Visitor is not separated from group');
        check(str_contains($visitor,'Ref NY-TEST.') && str_contains($admin,'Ref NY-TEST.'), 'Reference in both');
        check(str_contains($visitor,'transport') === (bool)$transport && str_contains($admin,', transport') === (bool)$transport, 'Transport mentioned only when requested');
        check(str_contains($visitor,'overnight') === (bool)$overnight && str_contains($admin,', overnight') === (bool)$overnight, 'Overnight stay mentioned only when requested');
        foreach ([$visitor,$admin] as $text) check(!str_contains($text,'3 May 2099') && !str_contains($text,'2 guests'), 'No overnight dates or staying-guest count');
        check(str_contains($visitor,"It isn't confirmed yet") && str_contains($admin,'Awaiting confirmation; see your email to accept or reject.'), 'Both say the visit is not confirmed yet');
    }
    $solo=array_merge($fixture,['attendees'=>1]);
    check(str_contains(BookingSms::adminMessage($solo),', 1 person.'), 'Solo wording');
    check(count(BookingSms::messagesFor($fixture,[['phone'=>'+254711000001'],['phone'=>'+254711000001']]))===2,'Deduplicate admin phones');
    $now=1000; $worker=new SmsWorker($db,fn()=>$now);
    // Missing credentials leave every field untouched, not merely attempts.
    $before=$db->query('SELECT * FROM booking_sms')->fetchAll();
    try { $worker->run(new AfricasTalkingClient('sandbox','','sandbox','',successTransport(...))); throw new LogicException('Expected config failure'); }
    catch (SmsFailure $e) { check($e->category==='configuration','Config category'); }
    check($before===$db->query('SELECT * FROM booking_sms')->fetchAll(),'Configuration error does not touch queue');
    $result=$worker->run(client(fn()=>response('private auth text',401)));
    check($result['paused']===1 && (int)$db->query('SELECT SUM(attempts) FROM booking_sms')->fetchColumn()===0,'Provider auth pauses without attempts');
    check($worker->run(client(fn()=>throw new LogicException('Must remain paused')))['paused']===1,'Pause persists between runs');
    $db->exec('UPDATE sms_control SET paused=0,reason=NULL');
    // Safe transient failure has backoff, fixed payload, and independent recipients.
    $sent=[];
    $result=$worker->run(client(function($url,$headers,$body) use (&$sent) { $sent[]=$body; return response('',429); }),1);
    check($result['retry']===1,'Rate limit retries');
    $job=$db->query('SELECT * FROM booking_sms WHERE id=1')->fetch();
    check($job['attempts']===1 && $job['next_attempt_at']===1060,'Backoff persisted');
    check($worker->run(client())['accepted']===5,'Other recipients proceed independently');
    check($worker->run(client())['accepted']===0,'Backoff and accepted jobs not resent');
    $worker=new SmsWorker($db,fn()=>1060);
    check($worker->run(client(function($url,$headers,$body) use ($sent) {check($body===$sent[0],'Retry payload unchanged');return successTransport($url,$headers,$body);}))['accepted']===1,'Retry succeeds');
    check($worker->run(client())['accepted']===0,'Accepted jobs stay accepted');
    // A timeout is uncertain, so it requires review, not an automatic retry.
    $store->create($booking,'timeout');
    check($worker->run(client(fn()=>throw new RuntimeException('secret timeout')))['review']===6,'Uncertain messages held');
    check(!str_contains(json_encode($db->query('SELECT * FROM booking_sms')->fetchAll()),'secret timeout'),'No sensitive diagnostics stored');
    // Nested worker simulates a competing process while the first send is active.
    $saved2=$store->create($booking,'concurrent');
    $otherDb=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $competingWorker=new SmsWorker($otherDb,fn()=>1060);
    $calls=0;
    $result=$worker->run(client(function($url,$headers,$body) use ($competingWorker,&$calls) {
        $calls++;
        check($competingWorker->run(client())['accepted']===5,'Competing worker skips active claim');
        return successTransport($url,$headers,$body);
    }),1);
    check($calls===1 && $result['accepted']===1,'Claim sends only once');
    $store->create($booking,'crash');
    $db->exec("UPDATE booking_sms SET status='sending',lease_until=1000,lease_token='dead' WHERE status='pending'");
    check($worker->run(client(fn()=>throw new LogicException('Must not resend expired claim')))['review']===6,'Expired sending claims require review');
    $store->create($booking,'post-send-storage');
    $db->exec("CREATE TRIGGER fail_sms_accept BEFORE UPDATE OF status ON booking_sms WHEN NEW.status='accepted' BEGIN SELECT RAISE(ABORT,'test'); END");
    try {$worker->run(client(),1);throw new LogicException('Expected post-send storage failure');}catch(PDOException $e){}
    check((int)$db->query("SELECT COUNT(*) FROM booking_sms WHERE status='sending'")->fetchColumn()===1,'Post-send storage failure remains uncertain');
    $db->exec('DROP TRIGGER fail_sms_accept');
    $recovery=new SmsWorker($db,fn()=>1200);
    $recovered=$recovery->run(client());
    check($recovered['review']===1 && $recovered['accepted']===5,'Recovery never resends uncertain accepted message');
    $store->create($booking,'expired-window');
    $db->exec("UPDATE booking_sms SET first_attempt_at=-90000 WHERE status='pending'");
    check($worker->run(client(fn()=>throw new LogicException('Expired work must not send')))['review']===6,'24-hour retry window');
    $store->create($booking,'bounded');
    $db->exec("UPDATE booking_sms SET attempts=4,first_attempt_at=1000 WHERE status='pending'");
    check($worker->run(client(fn()=>response('',429)))['review']===6,'Five attempts maximum');
    $store->create($booking,'profile');
    $worker->run(client(fn()=>response('',429)),1);
    $db->exec("UPDATE booking_sms SET next_attempt_at=0 WHERE status='pending'");
    $changed=new AfricasTalkingClient('sandbox','fake-key','sandbox','CHANGED',successTransport(...));
    check($worker->run($changed)['review']===1,'Changing environment/sender after attempt requires review');
    $unroutable=array_merge($booking,['phone'=>'1234567']);
    $saved3=$store->create($unroutable,'ambiguous');
    $q=$db->prepare("SELECT status FROM booking_sms WHERE booking_id=? AND audience='visitor'");$q->execute([$saved3['id']]);
    check($q->fetchColumn()==='review','Ambiguous number retains booking but cannot send');$q->closeCursor();
    $before=(int)$db->query('SELECT COUNT(*) FROM booking_sms')->fetchColumn();
    putenv('BOOKING_SMS_ENABLED=0');$store->create($booking,'disabled');
    check($worker->run(client())['accepted']===0,'Disabled worker does not send');
    putenv('BOOKING_SMS_ENABLED=1');$store->create($booking,'disabled');
    check((int)$db->query('SELECT COUNT(*) FROM booking_sms')->fetchColumn()===$before,'No historical backfill');
    $db->exec("CREATE TRIGGER fail_sms BEFORE INSERT ON booking_sms BEGIN SELECT RAISE(ABORT,'test'); END");
    try {$store->create($booking,'rollback');throw new LogicException('Expected rollback');}catch(PDOException $e){}
    check($store->findByToken('rollback')===null,'Booking and outbox commit atomically');
    $db->exec('DROP TRIGGER fail_sms');
    $snapshot=$db->query('SELECT * FROM booking_sms')->fetchAll();new BookingStore($path);
    check($snapshot===$db->query('SELECT * FROM booking_sms')->fetchAll(),'Migration repeat preserves queue state');
    echo "PASS booking SMS: templates, routing, shared normalization, atomic queue, configuration pause, retries, claims, recovery and rollout\n";
} finally {
    unset($worker,$store,$db,$otherDb,$competingWorker,$recovery);@unlink($path);@rmdir(dirname($path));
}
