<?php
declare(strict_types=1);
require __DIR__.'/../src/EmailWorker.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$path = sys_get_temp_dir().'/nyumba-email-'.bin2hex(random_bytes(8)).'/bookings.sqlite';
try {
    putenv('BOOKING_EMAIL_ENABLED=1');
    $store = new BookingStore($path);
    $booking = ['fullName' => '<Visitor & Test>', 'phone' => '+254712345678', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '11:00', 'attendees' => '3', 'overnight' => 'on', 'arrivalDate' => '2099-05-01', 'departureDate' => '2099-05-02', 'overnightGuests' => '2'];
    $saved = $store->create($booking, 'test-token');
    $store->create($booking, 'test-token');
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 1, 'Replay must not queue twice');
    $worker = new EmailWorker($db);
    $firstPayload = null; $firstKey = null;
    $failed = $worker->run(function ($payload, $key) use (&$firstPayload, &$firstKey) {
        $firstPayload = $payload; $firstKey = $key;
        check($payload['to'] === ['jaco@nyumbagri.com', 'pravin@nyumbagri.com'], 'Destination recipients');
        check(str_contains($payload['html'], '&lt;Visitor &amp; Test&gt;'), 'HTML escaping');
        check(str_contains($payload['text'], 'Staying guests: 2'), 'Overnight details');
        throw new RuntimeException('Simulated timeout');
    }, 'bookings@example.com');
    check($failed['retry'] === 1, 'Failed delivery must remain retryable');
    check($firstPayload['to'] === ['jaco@nyumbagri.com', 'pravin@nyumbagri.com'], 'Recipient routing');
    check(str_contains($firstPayload['html'], '&lt;Visitor &amp; Test&gt;'), 'Escaped visitor name');
    check(str_contains($firstPayload['text'], 'Staying guests: 2'), 'Overnight content');
    check($store->findByToken('test-token')['reference'] === $saved['reference'], 'Failure preserves booking');
    check($worker->run(fn() => throw new RuntimeException('Should not retry immediately'), 'bookings@example.com')['retry'] === 0, 'Backoff');
    $db->exec('UPDATE booking_emails SET next_attempt_at = 0');
    $result = $worker->run(function ($payload, $key) use ($firstPayload, $firstKey) {
        check($payload === $firstPayload && $key === $firstKey, 'Immutable retry payload and key');
        return 'mock-email-id';
    }, 'changed@example.com');
    check($result['accepted'] === 1, 'Provider acceptance');
    check($worker->run(fn() => throw new RuntimeException('Duplicate'), 'bookings@example.com')['retry'] === 0, 'Accepted email not resent');
    foreach (['sahajanand' => 3, 'feeding' => 2] as $destination => $count) {
        $day = array_merge($booking, ['location'=>$destination, 'overnight'=>'', 'arrivalDate'=>null, 'departureDate'=>null, 'overnightGuests'=>null]);
        $store->create($day, $destination);
        $job = $db->query('SELECT payload FROM booking_emails ORDER BY id DESC LIMIT 1')->fetchColumn();
        $payload = json_decode($job, true);
        check(count($payload['to']) === $count, 'Correct contact count');
        check(!str_contains($payload['text'], 'Overnight stay'), 'Day visit content');
    }
    $db->exec('UPDATE booking_emails SET first_attempt_at = '.(time()-86400)." WHERE status = 'pending'");
    check($worker->run(fn() => throw new RuntimeException('Expired retry'), 'bookings@example.com')['review'] === 2, 'Expired requests require review');
    putenv('BOOKING_EMAIL_ENABLED=0');
    $store->create($booking, 'disabled');
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 3, 'Disabled sending queues nothing');
    putenv('BOOKING_EMAIL_ENABLED=1');
    $store->create($booking, 'disabled');
    check((int)$db->query('SELECT COUNT(*) FROM booking_emails')->fetchColumn() === 3, 'Do not email old bookings after enabling');
    $db->exec("CREATE TRIGGER fail_queue BEFORE INSERT ON booking_emails BEGIN SELECT RAISE(ABORT, 'Simulated storage failure'); END");
    try { $store->create($booking, 'rollback'); throw new RuntimeException('Expected queue storage failure'); }
    catch (PDOException $expected) {}
    check($store->findByToken('rollback') === null, 'Booking and notification must commit atomically');
    echo "PASS email queue: routing, escaping, overnight, replay, failure isolation, retries, immutable payload, expiry and disabled mode\n";
} finally {
    unset($db, $store, $worker);
    @unlink($path); @rmdir(dirname($path));
}
