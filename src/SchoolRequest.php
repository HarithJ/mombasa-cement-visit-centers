<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
final class SchoolRequest {
    public const TYPES = ['classroom'=>'Classroom','school_wall'=>'School wall','both'=>'Classroom and school wall'];
    public const FIELDS = ['name'=>['Your name',120], 'relationship'=>['Relationship to the school',120], 'email'=>['Email address',254], 'phone'=>['Phone number',40], 'school'=>['School name',180], 'county'=>['County',120], 'locality'=>['Town or locality',120], 'support'=>['Support requested',20], 'description'=>['Tell us what your school needs',3000]];
    public static function validate(array $post): array {
        $values=[]; $errors=[];
        foreach (self::FIELDS as $key=>[$label,$max]) {
            $values[$key]=is_string($post[$key]??null)?trim($post[$key]):'';
            if ($values[$key]==='' || mb_strlen($values[$key])>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $values[$key])) $errors[$key]='Enter '.$label.' (up to '.$max.' characters).';
        }
        if (!filter_var($values['email'],FILTER_VALIDATE_EMAIL)) $errors['email']='Enter a valid email address.';
        if (!isset(self::TYPES[$values['support']])) $errors['support']='Choose the support requested.';
        $phone=PhoneNumber::normalize($values['phone']);
        if (!$phone) $errors['phone']='Enter a valid phone number.'; else $values['phone']=$phone;
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
            $db->prepare('INSERT INTO school_notifications(request_id,idempotency_key) VALUES (?,?)')->execute([$id,'school/'.$reference]);
            $db->commit(); return ['id'=>$id,'reference'=>$reference];
        } catch (Throwable $e) { $db->rollBack(); throw $e; }
    }
}
