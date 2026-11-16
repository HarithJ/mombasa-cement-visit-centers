<?php
declare(strict_types=1);
// Test stand-in for confirming the newest pending booking: marks it confirmed and creates its
// feedback invitation, which only confirmed bookings get. Usage: confirm-booking.php <db>
require __DIR__.'/../../src/BookingStore.php';
require_once __DIR__.'/../../src/Feedback.php';
new BookingStore($argv[1]);
$db = new PDO('sqlite:'.$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$booking = $db->query("SELECT * FROM bookings WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch();
if (!$booking) { fwrite(STDERR, "No pending booking\n"); exit(1); }
$db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking['id']]);
Feedback::schedule($db, $booking, 'v3', '/');
echo $booking['reference'], "\n";
