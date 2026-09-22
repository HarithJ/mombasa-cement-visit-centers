<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../src/ResendClient.php';
require __DIR__.'/../src/EmailWorker.php';
try {
    if (!BookingEmail::configured()) throw new RuntimeException('BOOKING_EMAIL_ENABLED must be 1');
    $key = getenv('RESEND_API_KEY') ?: '';
    if ($key === '') throw new RuntimeException('RESEND_API_KEY is required');
    if (!extension_loaded('curl')) throw new RuntimeException('PHP cURL is required');
    $path = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
    new BookingStore($path);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $client = new ResendClient($key);
    $result = (new EmailWorker($db))->run([$client, 'send'], getenv('RESEND_FROM') ?: '');
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
    exit($result['retry'] || $result['review'] ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, "Email worker could not run. Check configuration, PHP extensions and private database access.\n");
    exit(1);
}
