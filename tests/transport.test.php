<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingStore.php';
require __DIR__.'/../src/DayBooking.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$directory = sys_get_temp_dir().'/nyumba-transport-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory.'/bookings.sqlite';
putenv('BOOKING_EMAIL_ENABLED=1');
putenv('FEEDBACK_EMAIL_ENABLED=0');
try {
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE schema_migrations (version TEXT PRIMARY KEY)');
    foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
        if (basename($file) >= '007') continue;
        $db->exec(file_get_contents($file));
        $db->prepare('INSERT INTO schema_migrations VALUES (?)')->execute([basename($file)]);
    }
    $db->exec("INSERT INTO bookings(reference,submission_token,full_name,phone,destination,visit_date,booked_time,attendees,status,created_at) VALUES ('NY-OLD','old','Old booking','+254700000000','feeding','2099-05-01','11:00',1,'automatically_confirmed','2026-01-01')");
    $db->exec("INSERT INTO booking_emails(booking_id,payload,idempotency_key,status,provider_id) VALUES (1,'{}','old-email','accepted','original-id')");
    $store = new BookingStore($path);
    check($store->findByToken('old')['transport_requested'] === 0, 'Migration defaults old bookings to not requested');
    check($db->query('SELECT provider_id FROM booking_emails')->fetchColumn() === 'original-id', 'Migration preserves existing email state');
    $schedule = require __DIR__.'/../config/destinations.php';
    $base = ['fullName'=>'Transport visitor','phone'=>'0712345678','email'=>'visitor@example.com','location'=>'feeding','visitDate'=>'2099-05-01','timeSlot'=>'11:00','attendees'=>'3'];
    foreach ([null, '', 'on'] as $choice) {
        $input = $base;
        if ($choice !== null) $input['transportRequested'] = $choice;
        [$booking, $errors] = DayBooking::validate($input, $schedule);
        check(!$errors, 'Optional transport choice is valid');
        $token = 'choice-'.($choice ?? 'missing');
        $saved = $store->create($booking, $token);
        $expected = $choice === 'on' ? 1 : 0;
        check($saved['transport_requested'] === $expected, 'Choice saved');
        check($store->adminBooking((int)$saved['id'])['transport_requested'] === $expected, 'Choice available to admin');
        $booking['transportRequested'] = $expected ? '' : 'on';
        check($store->create($booking, $token)['transport_requested'] === $expected, 'Replay preserves original choice');
        $q = $db->prepare('SELECT payload FROM booking_emails WHERE booking_id = ?');
        $q->execute([$saved['id']]);
        $messages = $q->fetchAll(PDO::FETCH_COLUMN);
        check(count($messages) === 2, 'Replay does not enqueue extra emails');
        foreach ($messages as $json) {
            $payload = json_decode($json, true);
            $line = 'Transport: '.($expected ? 'Requested (subject to availability)' : 'Not requested');
            check(str_contains($payload['text'], $line) && str_contains($payload['html'], $line), 'Visitor and destination emails include the choice');
        }
    }
    foreach (['yes', ['on'], 1] as $invalid) {
        [, $errors] = DayBooking::validate($base + ['transportRequested'=>$invalid], $schedule);
        check(isset($errors['transportRequested']), 'Reject malformed transport values');
    }
    [$retained, $errors] = DayBooking::validate(array_merge($base, ['transportRequested'=>'on','fullName'=>'']), $schedule);
    check(isset($errors['fullName']) && $retained['transportRequested'] === 'on', 'Retain checkbox after validation failure');
    $store = new BookingStore($path);
    check($store->findByToken('choice-on')['transport_requested'] === 1, 'Repeat migration and restart preserve choice');
    echo "PASS transport: defaults, validation, migration, persistence, admin, email payloads and replay (no messages sent)\n";
} finally {
    unset($store, $db);
    @unlink($path); @rmdir($directory);
}
