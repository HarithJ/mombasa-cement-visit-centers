<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../src/BookingStore.php';
require __DIR__.'/../src/SmsWorker.php';
try {
    if (!BookingSms::enabled()) { echo "SMS sending disabled.\n"; exit(0); }
    $client = AfricasTalkingClient::fromEnvironment();
    $client->validateConfiguration();
    $path = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
    new BookingStore($path);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $result = (new SmsWorker($db))->run($client);
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
    if ($result['paused']) fwrite(STDERR, "SMS sending paused: check provider credentials, sender, account balance and sms_control. No configuration failure consumes retry attempts.\n");
    exit($result['retry'] || $result['review'] || $result['paused'] ? 1 : 0);
} catch (SmsFailure $error) {
    fwrite(STDERR, "SMS worker configuration error: check Africa's Talking environment, credentials and PHP cURL. Pending jobs and attempts are unchanged.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, "SMS worker could not complete. Check private database access and review any expired sending claims before resending.\n");
    exit(1);
}
