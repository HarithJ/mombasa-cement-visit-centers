<?php
declare(strict_types=1);
require_once __DIR__.'/CommunityStore.php';
require_once __DIR__.'/SchoolRequest.php';
require_once __DIR__.'/SchoolEmailWorker.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);header('Allow: GET');return;}
$db=CommunityStore::open();
if (str_starts_with($path,'/admin/donations')) { require __DIR__.'/donation-admin.php'; return; }
$kind='school-requests'; $title='School requests'; $detailFields=[]; $history=[];
if (!preg_match('#^/admin/school-requests(?:/([1-9][0-9]{0,12}))?$#D',$path,$matches)) {http_response_code(404);echo 'Page not found.';return;}
$base='/admin/'.$kind;
$filterKeys=['q','from','to','state'];$f=[];$bad=false;
foreach ($filterKeys as $key) {if(isset($_GET[$key])&&!is_string($_GET[$key]))$bad=true;$f[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';}
$states=SchoolRequest::TYPES;
if (strlen($f['q'])>200 || ($f['state']!==''&&!isset($states[$f['state']])))$bad=true;
foreach (['from','to'] as $key) {if($f[$key]!=='') {$date=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key],new DateTimeZone('Africa/Nairobi'));if(!$date||$date->format('Y-m-d')!==$f[$key])$bad=true;}}
if ($f['from']&&$f['to']&&$f['from']>$f['to'])$bad=true;
$page=filter_var($_GET['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
if($page===false)$bad=true;
if($bad){http_response_code(400);echo 'Please check the filters. <a href="'.ae($base).'">Clear filters</a>';return;}
$notificationState=static function(array $row):string {
    if($row['notification']==='accepted')return 'Provider accepted';
    if($row['notification']==='review')return 'Review required';
    if(!SchoolEmailWorker::configuration())return 'Configuration required';
    return $row['attempts']?'Retrying':'Pending';
};
$select='SELECT r.*, n.status AS notification, n.attempts FROM school_requests r JOIN school_notifications n ON n.request_id=r.id';
$detail=isset($matches[1]);
if($detail){
 $q=$db->prepare($select.' WHERE r.id=?');$q->execute([(int)$matches[1]]);$record=$q->fetch();
 if(!$record){http_response_code(404);echo 'Request not found.';return;}
 $detailFields=['Reference'=>$record['reference']];
 foreach(SchoolRequest::FIELDS as $key=>[$label])$detailFields[$label]=$key==='support'?SchoolRequest::TYPES[$record[$key]]:$record[$key];
 $detailFields['Submitted']=adminTimestamp($record['created_at']);$detailFields['Notification']=$notificationState($record);
}else{
 $where=[];$params=[];
 if($f['q']!==''){$where[]='(instr(lower(r.reference),lower(?))>0 OR instr(lower(r.school),lower(?))>0 OR instr(lower(r.name),lower(?))>0)';array_push($params,$f['q'],$f['q'],$f['q']);}
 if($f['state']!==''){$where[]='r.support=?';$params[]=$f['state'];}
 foreach(['from'=>'>=','to'=>'<']as $key=>$operator)if($f[$key]!==''){$date=new DateTimeImmutable($f[$key],new DateTimeZone('Africa/Nairobi'));if($key==='to')$date=$date->modify('+1 day');$where[]='r.created_at '.$operator.' ?';$params[]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
 $q=$db->prepare($select.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.created_at DESC,r.id DESC LIMIT 26 OFFSET ?');
 foreach($params as $i=>$value)$q->bindValue($i+1,$value);$q->bindValue(count($params)+1,($page-1)*25,PDO::PARAM_INT);$q->execute();$raw=$q->fetchAll();
 $rows=[];foreach(array_slice($raw,0,25)as $row)$rows[]=['id'=>$row['id'],'Reference'=>$row['reference'],'School'=>$row['school'],'Contact'=>$row['name'],'Support'=>SchoolRequest::TYPES[$row['support']],'Submitted'=>adminTimestamp($row['created_at']),'Notification'=>$notificationState($row)];
 $hasNext=count($raw)>25;
}
require __DIR__.'/views/community-admin.php';
