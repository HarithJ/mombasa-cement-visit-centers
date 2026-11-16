<?php
declare(strict_types=1);
require __DIR__.'/../src/EmailWorker.php';
require __DIR__.'/../src/DayBooking.php';
require_once __DIR__.'/../src/UserStore.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$path = sys_get_temp_dir().'/nyumba-email-'.bin2hex(random_bytes(8)).'/bookings.sqlite';
try {
    putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SITE_URL=https://example.com');
    $users = UserStore::open($path);
    $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0711000001', 'role' => 'admin']);
    $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0711000002', 'role' => 'team']);
    $store = new BookingStore($path);
    $booking = ['email' => 'visitor@example.com', 'fullName' => '<Visitor & Test>', 'phone' => '+254712345678', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '11:00', 'attendees' => '3', 'overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '3'];
    $oneNight = fn(string $text): bool => str_contains($text, 'Overnight stay: Requested (subject to availability)') && !str_contains($text, '2 May 2099') && !str_contains($text, 'Staying guests');
    $schedule = require __DIR__.'/../config/destinations.php';
    foreach (['invalid', 'a@b', "a@example.com\r\nBcc: b@example.com", str_repeat('a', 255).'@example.com'] as $invalid) {
        [, $errors] = DayBooking::validate(array_merge($booking, ['email' => $invalid]), $schedule);
        check(isset($errors['email']), 'Invalid email rejected');
    }
    [$valid, $errors] = DayBooking::validate(array_merge($booking, ['email' => '  Visitor+test@example.com  ']), $schedule);
    check(!$errors && $valid['email'] === 'Visitor+test@example.com', 'Trim email without changing local part');
    foreach (['2099-02-30', 'not-a-date'] as $badDate) {
        [$invalid, $errors] = DayBooking::validate(array_merge($booking, ['visitDate' => $badDate]), $schedule);
        check(isset($errors['visitDate']) && $invalid['arrivalDate'] === null && $invalid['departureDate'] === null && $invalid['overnightGuests'] === null, 'An invalid visit date never yields a partial overnight stay');
    }
    $saved = $store->create($booking, 'test-token');
    check($saved['email'] === 'visitor@example.com', 'Email persisted');
    $store->create($booking, 'test-token');
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 3, 'Replay must not queue twice');
    $worker = new EmailWorker($db);
    $firstPayload = null; $firstKey = null;
    $failed = $worker->run(function ($payload, $key) use (&$firstPayload, &$firstKey, $oneNight) {
        $firstPayload = $payload; $firstKey = $key;
        check($payload['to'] === ['ada@example.test'], 'Each user gets their own team email');
        check(str_contains($payload['html'], '&lt;Visitor &amp; Test&gt;'), 'HTML escaping');
        check($oneNight($payload['text']), 'Overnight details');
        throw new RuntimeException('Simulated timeout');
    }, 'bookings@example.com', 1);
    check($failed['retry'] === 1, 'Failed delivery must remain retryable');
    check($firstPayload['to'] === ['ada@example.test'], 'Recipient routing');
    check(str_contains($firstPayload['html'], '&lt;Visitor &amp; Test&gt;'), 'Escaped visitor name');
    check($oneNight($firstPayload['text']) && str_contains($firstPayload['text'], 'Attendees: 3'), 'Overnight content');
    check($store->findByToken('test-token')['reference'] === $saved['reference'], 'Failure preserves booking');
    $sent = [];
    $visitor = $worker->run(function ($payload) use (&$sent, $oneNight) {
        $sent[] = $payload['to'];
        if ($payload['to'] === ['visitor@example.com']) { check(str_contains($payload['text'], 'jangabriel@nyumbagri.com'), 'Visitor contact details'); check($oneNight($payload['text']) && str_contains($payload['text'], 'availability'), 'Visitor overnight request'); }
        return 'id-'.count($sent);
    }, 'bookings@example.com');
    check($visitor['accepted'] === 2 && $sent === [['tom@example.test'], ['visitor@example.com']], 'Separate team and visitor emails');
    check($worker->run(fn() => throw new RuntimeException('Should not retry immediately'), 'bookings@example.com')['retry'] === 0, 'Backoff');
    $db->exec('UPDATE booking_emails SET next_attempt_at = 0');
    $result = $worker->run(function ($payload, $key) use ($firstPayload, $firstKey) {
        check($payload === $firstPayload && $key === $firstKey, 'Immutable retry payload and key');
        return 'mock-email-id';
    }, 'changed@example.com');
    check($result['accepted'] === 1, 'Provider acceptance');
    check($worker->run(fn() => throw new RuntimeException('Duplicate'), 'bookings@example.com')['retry'] === 0, 'Accepted email not resent');
    // Two Galana Farm day visits: a team email for each user and a visitor email, six emails left to expire below.
    foreach (['day-one', 'day-two'] as $token) {
        $day = array_merge($booking, ['location'=>'galana', 'overnight'=>'', 'arrivalDate'=>null, 'departureDate'=>null, 'overnightGuests'=>null]);
        $store->create($day, $token);
        $job = $db->query("SELECT payload FROM booking_emails WHERE kind = 'decision_request' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $payload = json_decode($job, true);
        check($payload['to'] === ['tom@example.test'], 'Team emails go to users, one each');
        check(!str_contains($payload['text'], 'Overnight stay'), 'Day visit content');
    }
    $db->exec('UPDATE booking_emails SET first_attempt_at = '.(time()-86400)." WHERE status = 'pending'");
    check($worker->run(fn() => throw new RuntimeException('Expired retry'), 'bookings@example.com')['review'] === 6, 'Expired requests require review');
    putenv('BOOKING_EMAIL_ENABLED=0');
    $store->create($booking, 'disabled');
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 9, 'Disabled sending queues nothing');
    putenv('BOOKING_EMAIL_ENABLED=1');
    $store->create($booking, 'disabled');
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 9, 'Do not email old bookings after enabling');
    $db->exec("CREATE TRIGGER fail_queue BEFORE INSERT ON booking_emails BEGIN SELECT RAISE(ABORT, 'Simulated storage failure'); END");
    try { $store->create($booking, 'rollback'); throw new RuntimeException('Expected queue storage failure'); }
    catch (PDOException $expected) {}
    check($store->findByToken('rollback') === null, 'Booking and notification must commit atomically');
    $db->exec('DROP TRIGGER fail_queue');
    putenv('FEEDBACK_EMAIL_ENABLED=1');
    putenv('BOOKING_SITE_URL=https://example.com');
    foreach (['', '   ', null] as $optional) {
        $input = array_merge($booking, ['email' => $optional]);
        if ($optional === null) unset($input['email']);
        [$withoutEmail, $errors] = DayBooking::validate($input, $schedule);
        check(!$errors, 'Blank, whitespace or omitted email is valid');
        $record = $store->create($withoutEmail, 'optional-'.bin2hex(random_bytes(4)));
        check($record['email'] === null, 'Missing email stored as null');
        $query = $db->prepare('SELECT kind, payload FROM booking_emails WHERE booking_id = ?');
        $query->execute([$record['id']]); $jobs = $query->fetchAll(PDO::FETCH_ASSOC);
        check(array_column($jobs, 'kind') === ['decision_request', 'decision_request'], 'Only team emails queued without a visitor email');
        check(str_contains(json_decode($jobs[0]['payload'], true)['text'], 'Email: Not provided'), 'Team email explains missing email');
        check((int)$db->query('SELECT COUNT(*) FROM feedback_invitations WHERE booking_id = '.(int)$record['id'])->fetchColumn() === 0, 'No feedback invitation without email');
    }
    echo "PASS email queue: routing, escaping, overnight, replay, failure isolation, retries, immutable payload, expiry and disabled mode\n";
} finally {
    unset($db, $store, $worker, $users);
    @unlink($path); @rmdir(dirname($path));
}
