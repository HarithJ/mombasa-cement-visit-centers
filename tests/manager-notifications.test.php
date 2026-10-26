<?php
declare(strict_types=1);
require __DIR__.'/../src/EmailWorker.php';
require __DIR__.'/../src/SmsWorker.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SMS_ENABLED=1'); putenv('FEEDBACK_EMAIL_ENABLED=0');
$path = sys_get_temp_dir().'/nyumba-notify-'.bin2hex(random_bytes(8)).'/bookings.sqlite';
try {
    $store = new BookingStore($path);
    $contacts = require __DIR__.'/../config/contacts.php';
    $managers = ['harithjaved@gmail.com','apoorvchit@gmail.com'];
    $managerPhones = ['+254792488382','+254115933896'];
    $input = ['fullName'=>'Alex','phone'=>'0712345678','email'=>'visitor@example.com','location'=>'galana','visitDate'=>'2099-05-01','timeSlot'=>'09:00','attendees'=>'3','overnight'=>false,'arrivalDate'=>null,'departureDate'=>null,'overnightGuests'=>null];
    foreach (['galana','sahajanand','feeding'] as $destination) {
        $booking = $store->create(array_merge($input,['location'=>$destination]),$destination);
        $teamEmails = array_column($contacts[$destination]['people'],'email');
        check(BookingEmail::payload($booking)['to'] === array_values(array_unique([...$teamEmails,...$managers])), "$destination: team contacts and all three managers receive the booking email");
        check(BookingEmail::visitorPayload($booking)['to'] === ['visitor@example.com'], 'Visitor email unchanged');
        $sms = array_column(BookingSms::messagesFor($booking),'recipient');
        $teamPhones = array_map(fn($p) => PhoneNumber::normalize($p['tel'] ?? $p['phone']), $contacts[$destination]['people']);
        check($sms === ['+254712345678', ...array_values(array_unique([...$teamPhones, ...$managerPhones]))], "$destination: visitor, destination team and both managers receive SMS");
        check(!in_array('+254788738085', $sms, true), 'Former override number no longer receives SMS');
    }
    echo "Manager notification tests passed.\n";
} finally {
    $store = null;
    foreach (glob(dirname($path).'/*') as $file) unlink($file);
    rmdir(dirname($path));
}
