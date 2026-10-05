<?php
declare(strict_types=1);
require_once __DIR__.'/DonationLedger.php';
require_once __DIR__.'/DonationAttempt.php';
$isAttempt=str_starts_with($path,'/admin/donations/attempts');
$kind='donations';$title=$isAttempt?'Donation attempts':'Donations';$base=$isAttempt?'/admin/donations/attempts':'/admin/donations';$states=$isAttempt?DonationAttempt::STATES:DonationLedger::STATES;$history=[];
$table=$isAttempt?'donation_attempt_summary':'mpesa_transactions';$dateColumn=$isAttempt?'created_at':'transacted_at';$stateColumn=$isAttempt?'payment_state':'state';
if(!preg_match('#^'.preg_quote($base,'#').'(?:/([1-9][0-9]{0,12}))?$#D',$path,$matches)){http_response_code(404);echo 'Page not found.';return;}
$f=[];$bad=false;
foreach(['q','from','to','state']as $key){if(isset($_GET[$key])&&!is_string($_GET[$key]))$bad=true;$f[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';}
if(strlen($f['q'])>200||($f['state']!==''&&!isset($states[$f['state']])))$bad=true;
foreach(['from','to']as $key)if($f[$key]!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key],new DateTimeZone('Africa/Nairobi'));if(!$date||$date->format('Y-m-d')!==$f[$key])$bad=true;}
if($f['from']&&$f['to']&&$f['from']>$f['to'])$bad=true;
$page=filter_var($_GET['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);if($page===false)$bad=true;
if($bad){http_response_code(400);echo 'Please check the filters. <a href="/admin/donations">Clear filters</a>';return;}
$detail=isset($matches[1]);
if($detail){
 $q=$db->prepare('SELECT * FROM '.$table.' WHERE id=?');$q->execute([(int)$matches[1]]);$r=$q->fetch();
 if(!$r){http_response_code(404);echo 'Transaction not found.';return;}
 if($isAttempt){
 $detailFields=['Reference'=>$r['reference'],'Expected amount'=>'KES '.number_format($r['amount']/100,2),'Payment state'=>$states[$r['payment_state']],'QR state'=>DonationAttempt::STATES[$r['state']],'Merchant'=>$r['merchant_name'].' / '.$r['merchant'],'Environment'=>$r['environment'],'Provider request'=>$r['provider_id'],'Associated receipts'=>$r['receipts'],'Created'=>adminTimestamp($r['created_at'])];
 $receiptSearch=$r['reference'];
 }else{
 $detailFields=['Receipt'=>$r['receipt'],'Amount'=>'KES '.number_format($r['amount']/100,2),'State'=>$states[$r['state']],'Account reference'=>$r['account_reference'],'Merchant'=>$r['merchant'],'Environment'=>$r['environment'],'Payer identifier'=>$r['payer'],'Payer name'=>$r['payer_name'],'Transaction time'=>adminTimestamp($r['transacted_at']),'Received'=>adminTimestamp($r['received_at'])];
 $q=$db->prepare('SELECT source,evidence,created_at FROM mpesa_events WHERE transaction_id=? ORDER BY id');$q->execute([$r['id']]);foreach($q as $event)$history[]=adminTimestamp($event['created_at']).' · '.$event['source'].($event['evidence']?' · '.$event['evidence']:'');
 $q=$db->prepare('SELECT status,attempts,last_error FROM mpesa_queries WHERE environment=? AND merchant=? AND receipt=?');$q->execute([$r['environment'],$r['merchant'],$r['receipt']]);if($job=$q->fetch())$history[]='Status checks: '.$job['status'].' · '.$job['attempts'].' attempts'.($job['last_error']?' · '.$job['last_error']:'');
 $q=$db->prepare('SELECT * FROM mpesa_adjustments WHERE transaction_id=?');$q->execute([$r['id']]);foreach($q as $adjustment)$history[]=adminTimestamp($adjustment['created_at']).' · Reversal KES '.number_format($adjustment['amount']/100,2).' · '.$adjustment['actor'].' · '.$adjustment['evidence'];
 }
}else{
 $where=[];$params=[];
 if($f['q']!==''){$where[]=$isAttempt?'instr(lower(reference),lower(?))>0':'(instr(lower(receipt),lower(?))>0 OR instr(lower(account_reference),lower(?))>0)';$params[]=$f['q'];if(!$isAttempt)$params[]=$f['q'];}
 if($f['state']!==''){$where[]=$stateColumn.'=?';$params[]=$f['state'];}
 foreach(['from'=>'>=','to'=>'<']as $key=>$operator)if($f[$key]!==''){$date=new DateTimeImmutable($f[$key],new DateTimeZone('Africa/Nairobi'));if($key==='to')$date=$date->modify('+1 day');$where[]=$dateColumn.' '.$operator.' ?';$params[]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
 $condition=$where?' WHERE '.implode(' AND ',$where):'';
 if(!$isAttempt){$q=$db->prepare("SELECT COUNT(CASE WHEN state='verified' AND environment='production' THEN 1 END) AS count, COALESCE(SUM(CASE WHEN state='verified' AND environment='production' THEN amount ELSE 0 END),0) AS amount FROM mpesa_transactions".$condition);$q->execute($params);$totals=$q->fetch();}
 $q=$db->prepare('SELECT * FROM '.$table.$condition.' ORDER BY '.$dateColumn.' DESC,id DESC LIMIT 26 OFFSET ?');foreach($params as $i=>$value)$q->bindValue($i+1,$value);$q->bindValue(count($params)+1,($page-1)*25,PDO::PARAM_INT);$q->execute();$raw=$q->fetchAll();$hasNext=count($raw)>25;
 $rows=[];foreach(array_slice($raw,0,25)as $r)$rows[]=$isAttempt?['id'=>$r['id'],'Reference'=>$r['reference'],'Expected amount'=>'KES '.number_format($r['amount']/100,2),'State'=>$states[$r['payment_state']],'Environment'=>$r['environment'],'Created'=>adminTimestamp($r['created_at'])]:['id'=>$r['id'],'Reference'=>$r['receipt'],'Amount'=>'KES '.number_format($r['amount']/100,2),'State'=>$states[$r['state']],'Account'=>$r['account_reference'],'Environment'=>$r['environment'],'Transaction time'=>adminTimestamp($r['transacted_at'])];
}
require __DIR__.'/views/community-admin.php';
