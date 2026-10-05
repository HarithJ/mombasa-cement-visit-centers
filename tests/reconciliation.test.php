<?php
declare(strict_types=1);
require __DIR__.'/../src/CommunityStore.php';
require __DIR__.'/../src/DonationLedger.php';
require __DIR__.'/../src/MpesaReconciler.php';
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/nyumba-reconcile-'.bin2hex(random_bytes(6));
try{
 $db=CommunityStore::open($dir.'/db');
 $config=['environment'=>'sandbox','merchant'=>'600000','name'=>'Example','number'=>'600000','type'=>'paybill','reference'=>'NYUMBA','dedicated'=>true];
 $ledger=new DonationLedger($db,$config);
 $body=['TransID'=>'TESTLATE0001','TransTime'=>'20261005103000','TransAmount'=>'250.00','BusinessShortCode'=>'600000','BillRefNumber'=>'NYUMBA'];
 $id=$ledger->receive($body,false);
 $reconciler=new MpesaReconciler($db,$config,fn()=>1800000000);
 $result=$reconciler->run(function($receipt,$token){check($receipt==='TESTLATE0001','Query existing receipt only');return ['ResponseCode'=>'0','ConversationID'=>'conversation-1','OriginatorConversationID'=>'origin-1'];});
 check($result['requested']===1,'Unverified receipt scheduled for a provider query');
 $token=$db->query('SELECT token FROM mpesa_query_runs')->fetchColumn();
 $callback=['Result'=>['ResultCode'=>0,'ConversationID'=>'conversation-1','OriginatorConversationID'=>'origin-1','ResultParameters'=>['ResultParameter'=>[
 ['Key'=>'ReceiptNo','Value'=>'TESTLATE0001'],['Key'=>'TransactionStatus','Value'=>'Completed'],['Key'=>'Amount','Value'=>'250.00'],['Key'=>'CreditPartyName','Value'=>'600000 - Example'],['Key'=>'TransactionCompletedDateTime','Value'=>'20261005103000'],['Key'=>'BillReferenceNumber','Value'=>'NYUMBA']
 ]]]];
 $reconciler->acceptResult($token,$callback,true);
 $result=$reconciler->run(fn()=>throw new RuntimeException('Must not make a new query'));
 check($result['verified']===1,'Authoritative status result verifies receipt');
 check($db->query('SELECT state FROM mpesa_transactions')->fetchColumn()==='verified','Verified ledger state persisted');
 $reconciler->acceptResult($token,$callback,true);
 $reconciler->run(fn()=>throw new RuntimeException('Must not make a new query'));
 check((int)$db->query('SELECT COUNT(*) FROM mpesa_transactions')->fetchColumn()===1,'Duplicate reconciliation is idempotent');
 echo "PASS delayed receipt verification through scheduled provider boundary\n";
 $ledger->reconcileStatement($body,'operator-example','statement-2026-10-05 / line 42',false);
 $ledger->reconcileStatement($body,'operator-example','statement-2026-10-05 / line 42 reversal',true);
 $ledger->reconcileStatement($body,'operator-example','statement-2026-10-05 / line 42 reversal',true);
 check($db->query('SELECT state FROM mpesa_transactions')->fetchColumn()==='reversed','Reversal preserves original transaction and updates state');
 check((int)$db->query('SELECT COUNT(*) FROM mpesa_adjustments')->fetchColumn()===1,'Reversal adjustment applied once');
 echo "PASS verified statement reconciliation and linked full reversal are idempotent\n";
 $ledger->receive([...$body,'TransID'=>'TESTLATE0002'],false);
 $now=1800000000;$retryWorker=new MpesaReconciler($db,$config,function()use(&$now){return $now;});
 for($i=0;$i<5;$i++){$result=$retryWorker->run(fn()=>throw new RuntimeException('Timeout'));check($result['retry']===1,'Unknown result retries without charging');$now+=3601;}
 check($retryWorker->run(fn()=>throw new RuntimeException('No sixth query'))['review']===1,'Bounded query attempts reach review');
 $row=$db->query("SELECT state FROM mpesa_transactions WHERE receipt='TESTLATE0002'")->fetchColumn();check($row==='unverified','Timeout never verifies funds');
 $ledger->receive([...$body,'TransID'=>'TESTLATE0003'],false);
 $retryWorker->run(fn()=>['ResponseCode'=>'0','ConversationID'=>'conversation-3','OriginatorConversationID'=>'origin-3']);
 $token=$db->query("SELECT r.token FROM mpesa_query_runs r JOIN mpesa_queries q ON q.id=r.query_id WHERE q.receipt='TESTLATE0003'")->fetchColumn();
 $incomplete=['Result'=>['ResultCode'=>0,'ConversationID'=>'conversation-3','OriginatorConversationID'=>'origin-3']];
 $retryWorker->acceptResult($token,$incomplete,false);$result=$retryWorker->run(fn()=>throw new RuntimeException('No early query'));check($result['verified']===0,'Untrusted query callback ignored');
 $retryWorker->acceptResult($token,$incomplete,true);$result=$retryWorker->run(fn()=>throw new RuntimeException('No early query'));check($result['review']===1,'Query acceptance alone is insufficient evidence');
 echo "PASS status-query backoff, bounded retries, untrusted results and incomplete evidence\n";
 $ledger->receive([...$body,'TransID'=>'TESTLATE0004'],false);
 $retryWorker->run(fn()=>['ResponseCode'=>'0','ConversationID'=>'conversation-4','OriginatorConversationID'=>'origin-4']);
 $token=$db->query("SELECT r.token FROM mpesa_query_runs r JOIN mpesa_queries q ON q.id=r.query_id WHERE q.receipt='TESTLATE0004'")->fetchColumn();
 $retryWorker->acceptResult($token,['Result'=>['ConversationID'=>'conversation-4','OriginatorConversationID'=>'origin-4']],true,true);
 $retryWorker->run(fn()=>throw new RuntimeException('Wait for scheduled retry'));
 $now+=3601;
 check($retryWorker->run(fn()=>['ResponseCode'=>'0','ConversationID'=>'conversation-4-retry','OriginatorConversationID'=>'origin-4-retry'])['requested']===1,'Asynchronous provider timeout remains retryable');
 $token=$db->query("SELECT r.token FROM mpesa_query_runs r JOIN mpesa_queries q ON q.id=r.query_id WHERE q.receipt='TESTLATE0004' AND r.conversation='conversation-4-retry'")->fetchColumn();
 $recovered=$callback;$recovered['Result']['ConversationID']='conversation-4-retry';$recovered['Result']['OriginatorConversationID']='origin-4-retry';$recovered['Result']['ResultParameters']['ResultParameter'][0]['Value']='TESTLATE0004';
 $retryWorker->acceptResult($token,$recovered,true);
 check($retryWorker->run(fn()=>throw new RuntimeException('Already recovered'))['verified']===1,'Timeout followed by success verifies payment');
 echo "PASS asynchronous provider timeout retries and later confirms\n";
}finally{if(isset($db))$db=null;if(is_file($dir.'/db'))unlink($dir.'/db');if(is_dir($dir))rmdir($dir);}
