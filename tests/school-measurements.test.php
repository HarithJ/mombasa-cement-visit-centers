<?php
require __DIR__.'/../src/SchoolRequest.php';
require __DIR__.'/../src/CommunityStore.php';
function check($condition) {if(!$condition)throw new RuntimeException('Measurement check failed');}
$base=['name'=>'Teacher','relationship'=>'Teacher','email'=>'school@example.com','phone'=>'0712345678','school'=>'School','county'=>'Kilifi','locality'=>'Town','description'=>'School construction','support'=>'both'];
$measurements=['classroom_length'=>'8','classroom_width'=>'6','classroom_height'=>'3.5','wall_length'=>'120.5','wall_height'=>'2.4'];
foreach(['classroom','school_wall','both'] as $support){
 [$values,$errors]=SchoolRequest::validate(array_replace($base,$measurements,['support'=>$support]));check(!$errors);
 foreach(SchoolRequest::MEASUREMENTS as $key=>[$label,$type])check(in_array($support,[$type,'both'])?$values[$key]===$measurements[$key]:$values[$key]===null);
}
[$optional,$optionalErrors]=SchoolRequest::validate(array_replace($base,$measurements,['description'=>'','classroom_perimeter'=>'99']));check(!$optionalErrors);check(!array_key_exists('classroom_perimeter',$optional));
foreach(['','0','-1','NaN','1e2','1.234','1000001',[]] as $bad){[, $errors]=SchoolRequest::validate(array_replace($base,$measurements,['wall_height'=>$bad]));check(isset($errors['wall_height']));}
$path=tempnam(sys_get_temp_dir(),'school-measures-');
try {
 $db=CommunityStore::open($path);[$values]=SchoolRequest::validate($base+$measurements);
 SchoolRequest::save($db,$values,'measurements-test');SchoolRequest::save($db,$values,'measurements-test');
 $rows=$db->query('SELECT * FROM school_requests')->fetchAll();check(count($rows)===1);check(!array_key_exists('classroom_perimeter',$rows[0]));
 foreach($measurements as $key=>$value)check((float)$rows[0][$key]===(float)$value);
 check(count(SchoolRequest::measurementDetails($rows[0]))===5);
 echo "PASS conditional measurement validation, precision, storage and duplicate prevention\n";
}finally{unset($db);unlink($path);}
