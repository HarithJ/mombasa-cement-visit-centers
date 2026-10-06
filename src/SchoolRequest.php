<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
final class SchoolRequest {
    public const TYPES = ['classroom'=>'Classroom','school_wall'=>'School wall','both'=>'Classroom and school wall'];
    public const FIELDS = ['name'=>['Your name',120], 'relationship'=>['Relationship to the school',120], 'email'=>['Email address',254], 'phone'=>['Phone number',40], 'school'=>['School name',180], 'county'=>['County',120], 'locality'=>['Town or locality',120], 'support'=>['What would you like us to build?',20], 'description'=>['Any additional details we should know?',3000]];
    public const MEASUREMENTS = [
        'classroom_length'=>['Classroom length (m)','classroom'], 'classroom_width'=>['Classroom width (m)','classroom'],
        'classroom_height'=>['Classroom height (m)','classroom'],
        'wall_length'=>['Total wall length (m)','school_wall'], 'wall_height'=>['Boundary wall height (m)','school_wall']
    ];
    public static function measurementDetails(array $record): array {
        $details=[];
        foreach (self::MEASUREMENTS as $key=>[$label,$type]) {
            if (in_array($record['support'],[$type,'both'],true)) $details[$label]=isset($record[$key])?(string)$record[$key]:'Not supplied';
        }
        return $details;
    }
    public static function validate(array $post): array {
        $values=[]; $errors=[];
        foreach (self::FIELDS as $key=>[$label,$max]) {
            $values[$key]=is_string($post[$key]??null)?trim($post[$key]):'';
            if (($key!=='description' && $values[$key]==='') || mb_strlen($values[$key])>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $values[$key])) $errors[$key]='Enter '.$label.' (up to '.$max.' characters).';
        }
        if (!filter_var($values['email'],FILTER_VALIDATE_EMAIL)) $errors['email']='Enter a valid email address.';
        if (!isset(self::TYPES[$values['support']])) $errors['support']='Choose the support requested.';
        $phone=PhoneNumber::normalize($values['phone']);
        if (!$phone) $errors['phone']='Enter a valid phone number.'; else $values['phone']=$phone;
        foreach (self::MEASUREMENTS as $key=>[$label,$type]) {
            $values[$key]=null;
            if (!in_array($values['support'],[$type,'both'],true)) continue;
            $raw=is_string($post[$key]??null)?trim($post[$key]):'';
            $values[$key]=$raw;
            if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$raw) || (float)$raw<=0 || (float)$raw>1000000) $errors[$key]='Enter a positive measurement in metres (up to 1,000,000, with at most 2 decimal places).';
        }
        return [$values,$errors];
    }
    public static function save(PDO $db, array $values, string $token): array {
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM school_requests WHERE submission_token=?'); $q->execute([$token]);
            if ($saved=$q->fetch()) { $db->commit(); return $saved; }
            $reference='SC-'.strtoupper(bin2hex(random_bytes(8)));
            $db->prepare('INSERT INTO school_requests(reference,submission_token,name,relationship,email,phone,school,county,locality,support,description,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$reference,$token,...array_map(fn($key)=>$values[$key],array_keys(self::FIELDS)),gmdate('Y-m-d\TH:i:s\Z')]);
            $id=(int)$db->lastInsertId();
            $columns=array_keys(self::MEASUREMENTS);
            $db->prepare('UPDATE school_requests SET '.implode(',',array_map(fn($key)=>$key.'=?',$columns)).' WHERE id=?')->execute([...array_map(fn($key)=>$values[$key]??null,$columns),$id]);
            $db->prepare('INSERT INTO school_notifications(request_id,idempotency_key) VALUES (?,?)')->execute([$id,'school/'.$reference]);
            $db->commit(); return ['id'=>$id,'reference'=>$reference];
        } catch (Throwable $e) { $db->rollBack(); throw $e; }
    }
}
