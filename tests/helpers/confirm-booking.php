<?php
declare(strict_types=1);
// Confirms the newest pending booking through the decision service, as the first active user
// (a fictional one is added if there are none). Usage: confirm-booking.php <db>
require __DIR__.'/../../src/BookingDecisions.php';
$users = UserStore::open($argv[1]);
$user = $users->activeUsers()[0] ?? $users->add(['name' => 'Dana Decider', 'email' => 'dana@example.test', 'phone' => '0711000009', 'role' => 'admin']);
$db = new PDO('sqlite:'.$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$booking = $db->query("SELECT * FROM bookings WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch();
if (!$booking) { fwrite(STDERR, "No pending booking\n"); exit(1); }
$result = (new BookingDecisions($db))->decide((int)$booking['id'], $user, 'confirmed', null, 'first', time());
if (!$result['ok']) { fwrite(STDERR, "Not confirmed: {$result['error']}\n"); exit(1); }
echo $booking['reference'], "\n";
