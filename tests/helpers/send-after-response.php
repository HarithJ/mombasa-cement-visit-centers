<?php
// Plays one web request that queues emails and then ends: send-after-response.php <db> <session-dir> <scenario>
// Scenarios: ok | no-finisher | provider-down. Prints the event order as JSON lines.
declare(strict_types=1);
require __DIR__.'/../../src/SignIn.php';
require __DIR__.'/../../src/EmailDispatch.php';
[, $path, $sessions, $scenario] = $argv;
putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SITE_URL=https://example.com');
session_save_path($sessions); session_start(); $_SESSION['seen'] = 1;
$log = fn(string $event) => print(json_encode($event)."\n");
SignIn::open($path)->requestLink('ada@example.test', time());
(new BookingStore($path))->create(['email' => 'visitor@example.com', 'fullName' => 'Vera Visitor', 'phone' => '+254712345678', 'location' => 'galana', 'visitDate' => '2099-05-01', 'timeSlot' => '11:00', 'attendees' => '3', 'overnight' => '', 'arrivalDate' => null, 'departureDate' => null, 'overnightGuests' => null], 'token-'.bin2hex(random_bytes(4)));
$finish = $scenario === 'no-finisher' ? null : function () use ($log) { $log('finish:session-'.(session_status() === PHP_SESSION_ACTIVE ? 'open' : 'closed')); };
$send = function (array $payload) use ($log, $scenario) {
    if ($scenario === 'provider-down') throw new RuntimeException('Resend HTTP 503');
    $log('send:'.$payload['to'][0]);
    return 'mock-'.bin2hex(random_bytes(4));
};
EmailDispatch::afterResponse($path, $finish, $send, 'bookings@example.com');
$log('response');
