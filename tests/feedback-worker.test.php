<?php
declare(strict_types=1);
require __DIR__.'/../src/EmailWorker.php';
function verify(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$directory = sys_get_temp_dir().'/feedback-worker-'.bin2hex(random_bytes(8));
$path = $directory.'/bookings.sqlite';
putenv('FEEDBACK_EMAIL_ENABLED=1'); putenv('BOOKING_EMAIL_ENABLED=0'); putenv('BOOKING_SITE_URL=https://visits.example.com');
try {
    $store = new BookingStore($path);
    $booking = ['fullName'=>'Test Visitor','phone'=>'+254712345678','email'=>'visitor@example.com','location'=>'feeding','visitDate'=>'2099-05-01','timeSlot'=>'11:00','attendees'=>'1','overnight'=>'','arrivalDate'=>null,'departureDate'=>null,'overnightGuests'=>null];
    $store->create($booking, 'one'); $store->create($booking, 'one');
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $now = strtotime('2099-05-02T06:00:00Z');
    $worker = new EmailWorker($db, fn()=>$now);
    $other = new EmailWorker(new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]), fn()=>$now);
    $nested = null;
    $result = $worker->run(function() use ($other, &$nested) {
        $nested = $other->run(fn()=>throw new RuntimeException('Duplicate claim'), 'test@example.com');
        return 'provider-one';
    }, 'test@example.com');
    verify($result['accepted']===1 && $nested['accepted']===0 && $nested['retry']===0, 'Workers must not claim an active lease');
    // Operational fixture: a visitor completed feedback before the first mail attempt.
    $saved = $store->create($booking, 'two');
    $payload = json_decode($db->query("SELECT payload FROM booking_emails WHERE booking_id = ".(int)$saved['id'])->fetchColumn(), true);
    preg_match('/token=([a-f0-9]{64})/', $payload['text'], $match);
    $feedback = new Feedback($db);
    verify($feedback->submit($match[1], ['attendance'=>'not_attended','rating'=>'5'])===[], 'Absent visitor can respond');
    $suppressed = $worker->run(fn()=>throw new RuntimeException('Unnecessary invitation'), 'test@example.com');
    verify($suppressed['suppressed']===1 && $suppressed['retry']===0, 'Suppress unattempted completed feedback');
    // Both message types remain independent when feedback is paused.
    putenv('BOOKING_EMAIL_ENABLED=1');
    $store->create($booking, 'three');
    putenv('FEEDBACK_EMAIL_ENABLED=0');
    $messages=[];
    $sent = $worker->run(function($payload) use (&$messages) {$messages[]=$payload; return 'immediate';}, 'test@example.com');
    verify($sent['accepted']===2, 'Pausing feedback must not pause immediate emails');
    putenv('FEEDBACK_EMAIL_ENABLED=1');
    verify($worker->run(fn()=>'feedback-three', 'test@example.com')['accepted']===1, 'Resume pending invitation');
    putenv('FEEDBACK_EMAIL_ENABLED=0');
    $store->create($booking, 'disabled');
    putenv('FEEDBACK_EMAIL_ENABLED=1'); putenv('BOOKING_EMAIL_ENABLED=0');
    verify($worker->run(fn()=>throw new RuntimeException('Historical invitation'), 'test@example.com')['retry']===0, 'Do not backfill bookings made while disabled');
    // Response integrity at the existing private-storage boundary.
    $stored=$db->query('SELECT * FROM feedback_responses WHERE booking_id = '.(int)$saved['id'])->fetch(PDO::FETCH_ASSOC);
    verify($stored['rating']===null, 'Non-attendance discards tampered rating');
    $feedback->submit($match[1], ['attendance'=>'attended','rating'=>'1','comments'=>'Overwrite']);
    verify($db->query('SELECT * FROM feedback_responses WHERE booking_id = '.(int)$saved['id'])->fetch(PDO::FETCH_ASSOC)===$stored, 'Duplicate submission cannot change answers');
    $new=$store->create($booking,'isolated');
    $payload=json_decode($db->query('SELECT payload FROM booking_emails WHERE booking_id = '.(int)$new['id'])->fetchColumn(),true);
    preg_match('/token=([a-f0-9]{64})/', $payload['text'], $second);
    verify($feedback->find($second[1])['submitted_at']===null, 'Another invitation cannot retrieve the first response');
    $feedback->submit($second[1], ['attendance'=>'attended','rating'=>'4','booking_id'=>(string)$saved['id']]);
    verify($db->query('SELECT * FROM feedback_responses WHERE booking_id = '.(int)$saved['id'])->fetch(PDO::FETCH_ASSOC)===$stored, 'Posted booking ID cannot redirect a response');
    // Simulate lease takeover while an old delivery callback is still returning.
    $store->create($booking,'takeover');
    $takeover=new EmailWorker(new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),fn()=>$now+61);
    $taken=null;
    $late=$worker->run(function()use($takeover,&$taken){$taken=$takeover->run(fn()=>'new-owner','test@example.com');throw new RuntimeException('Late old failure');},'test@example.com');
    verify($taken['accepted']===1 && $late['retry']===0, 'Outdated worker cannot report a rejected transition');
    echo "PASS leases, response suppression, independent switches, replay and rollout\n";
} finally {unset($store,$db,$worker,$other,$feedback);foreach(glob($directory.'/*') as $file)unlink($file);@rmdir($directory);}
