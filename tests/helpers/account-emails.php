<?php
// Sends queued account emails with a fixed clock and a mock provider: account-emails.php <db> <unixNow>
require __DIR__.'/../../src/EmailWorker.php';
$db = new PDO('sqlite:'.$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$messages = [];
(new EmailWorker($db, fn() => (int)$argv[2], 'account_emails'))->run(function ($payload) use (&$messages) {
    $messages[] = $payload;
    return 'mock-'.count($messages);
}, 'bookings@example.com');
echo json_encode($messages, JSON_THROW_ON_ERROR);
