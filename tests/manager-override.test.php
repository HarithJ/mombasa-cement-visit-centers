<?php
declare(strict_types=1);
require __DIR__.'/../src/EmailWorker.php';
require __DIR__.'/../src/SmsWorker.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
putenv('BOOKING_EMAIL_ENABLED=1'); putenv('BOOKING_SMS_ENABLED=1'); putenv('FEEDBACK_EMAIL_ENABLED=0');
$path = sys_get_temp_dir().'/nyumba-override-'.bin2hex(random_bytes(8)).'/bookings.sqlite';
try {
    $store = new BookingStore($path);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $input = ['fullName'=>'Alex','phone'=>'0712345678','email'=>'visitor@example.com','location'=>'galana','visitDate'=>'2099-05-01','timeSlot'=>'09:00','attendees'=>'3','overnight'=>false,'arrivalDate'=>null,'departureDate'=>null,'overnightGuests'=>null];
    putenv('BOOKING_MANAGER_OVERRIDE_ENABLED');
    foreach (['galana','sahajanand','feeding'] as $destination) {
        $booking = $store->create(array_merge($input,['location'=>$destination]),$destination);
        check(BookingEmail::payload($booking)['to'] === ['harithjaved@gmail.com'], 'Only override email receives manager message');
        check(BookingEmail::visitorPayload($booking)['to'] === ['visitor@example.com'], 'Visitor email unchanged');
        $sms = BookingSms::messagesFor($booking);
        check(array_column($sms,'recipient') === ['+254712345678','+254792488382'], 'Visitor and override SMS only');
    }
    $emailRecipients = [];
    $emailResult = (new EmailWorker($db))->run(function($payload) use (&$emailRecipients) {
        $emailRecipients = [...$emailRecipients,...$payload['to']]; return 'fake-email-id';
    },'sender@example.com');
    check($emailResult['review'] === 0 && $emailResult['accepted'] === 6, 'Visitor and override emails sent');
    check(array_diff($emailRecipients,['visitor@example.com','harithjaved@gmail.com']) === [], 'No original manager email delivered');
    $smsRecipients = [];
    $client = new AfricasTalkingClient('sandbox','fake-key','sandbox','',function($url,$headers,$body) use (&$smsRecipients) {
        parse_str($body,$form); $smsRecipients[]=$form['to'];
        return ['status'=>201,'body'=>json_encode(['SMSMessageData'=>['Recipients'=>[['number'=>$form['to'],'statusCode'=>101,'messageId'=>'fake-sms-id']]]])];
    });
    $smsResult = (new SmsWorker($db))->run($client);
    check($smsResult['review'] === 0 && $smsResult['accepted'] === 6, 'Visitor and override SMS sent');
    check(array_diff($smsRecipients,['+254712345678','+254792488382']) === [], 'No original manager SMS delivered');
    $contacts = require __DIR__.'/../config/contacts.php';
    check($contacts['galana']['people'][0]['email'] === 'jaco@nyumbagri.com', 'Public contacts unchanged');
    putenv('BOOKING_MANAGER_OVERRIDE_ENABLED=0');
    check(count(BookingSms::messagesFor($booking)) === 3, 'Disabling override restores manager routing');
    echo "Manager override tests passed.\n";
} finally {
    $db = null; $store = null;
    foreach (glob(dirname($path).'/*') as $file) unlink($file);
    rmdir(dirname($path));
}
