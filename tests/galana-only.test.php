<?php
declare(strict_types=1);
// Records from places no longer offered survive the move to Galana Farm only; nothing more is sent about them.
require __DIR__.'/../src/BookingStore.php';
require __DIR__.'/../src/DayBooking.php';
require __DIR__.'/../src/Schedule.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$directory = sys_get_temp_dir().'/galana-only-'.bin2hex(random_bytes(8));
mkdir($directory, 0700); $path = $directory.'/bookings.sqlite';
try {
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE schema_migrations(version TEXT PRIMARY KEY)');
    foreach (glob(__DIR__.'/../migrations/0*.sql') as $file) {
        if (basename($file) >= '018') break;
        $db->exec(file_get_contents($file));
        $db->prepare('INSERT INTO schema_migrations VALUES (?)')->execute([basename($file)]);
    }
    $insert = $db->prepare("INSERT INTO bookings(reference,submission_token,full_name,phone,destination,visit_date,booked_time,attendees,status,created_at) VALUES (?,?,?,'+254712345678',?,'2099-05-01','11:00',2,'automatically_confirmed','2026-01-01T00:00:00Z')");
    $insert->execute(['NY-RETIRED', 'retired', 'Retired Visitor', 'retired-place']);
    $insert->execute(['NY-GALANA', 'galana', 'Galana Visitor', 'galana']);
    $db->exec("INSERT INTO interest_registrations(submission_token,full_name,phone,destination,preferred_date,attendees,created_at) VALUES ('interest','Interested Visitor','+254712345678','retired-place','2099-06-01',4,'2026-01-01T00:00:00Z')");
    $email = $db->prepare("INSERT INTO booking_emails(booking_id,interest_id,kind,payload,idempotency_key,status) VALUES (?,?,?,'{}',?,?)");
    $email->execute([1, null, 'feedback', 'feedback/retired', 'pending']);
    $email->execute([1, null, 'visitor', 'visitor/retired', 'accepted']);
    $email->execute([2, null, 'feedback', 'feedback/galana', 'pending']);
    $email->execute([null, 1, 'interest', 'interest/1', 'pending']);
    $sms = $db->prepare("INSERT INTO booking_sms(booking_id,audience,recipient,message,status) VALUES (?,'visitor','+254712345678','Hi',?)");
    $sms->execute([1, 'pending']); $sms->execute([2, 'pending']);

    new BookingStore($path); new BookingStore($path);

    check((int)$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 2, 'Every booking is kept');
    check((int)$db->query('SELECT COUNT(*) FROM interest_registrations')->fetchColumn() === 1, 'Every interest registration is kept');
    $emails = array_column($db->query('SELECT idempotency_key, status FROM booking_emails')->fetchAll(), 'status', 'idempotency_key');
    check($emails === ['feedback/retired' => 'suppressed', 'visitor/retired' => 'accepted', 'feedback/galana' => 'pending', 'interest/1' => 'suppressed'], 'Only pending messages for retired places are parked');
    $texts = array_column($db->query('SELECT booking_id, status FROM booking_sms')->fetchAll(), 'status', 'booking_id');
    check($texts === [1 => 'review', 2 => 'pending'], 'Pending SMS for retired places wait for review instead of sending');
    echo "PASS migration keeps retired-place records and parks only their pending messages\n";

    $schedule = Schedule::load();
    check(array_keys($schedule) === ['galana'], 'Galana Farm is the only scheduled destination');
    $valid = ['fullName' => 'Alex', 'phone' => '0712345678', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '11:00', 'attendees' => '2'];
    check(DayBooking::validate($valid, $schedule)[1] === [], 'Galana Farm bookings are accepted');
    check(isset(DayBooking::validate(array_merge($valid, ['location' => 'retired-place']), $schedule)[1]['location']), 'Bookings for any other place are refused');
    echo "PASS only Galana Farm can be booked\n";
} finally { unset($db); foreach (glob($directory.'/*') as $file) unlink($file); rmdir($directory); }
