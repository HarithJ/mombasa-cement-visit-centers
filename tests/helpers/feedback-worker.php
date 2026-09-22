<?php
require __DIR__.'/../../src/EmailWorker.php';
$db = new PDO('sqlite:'.$argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$messages = [];
$clock = fn() => (int)$argv[2];
$result = (new EmailWorker($db, $clock))->run(function ($payload, $key) use (&$messages, $argv) {
    $messages[] = ['payload'=>$payload, 'key'=>$key];
    if (($argv[3] ?? '') === 'fail') throw new RuntimeException('Mock timeout');
    return 'mock-'.count($messages);
}, 'bookings@example.com');
echo json_encode(['result'=>$result, 'messages'=>$messages], JSON_THROW_ON_ERROR);
