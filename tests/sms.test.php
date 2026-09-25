<?php
declare(strict_types=1);
require __DIR__.'/../src/AfricasTalkingClient.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function accepted(): array {
    return ['status' => 201, 'body' => json_encode(['SMSMessageData' => ['Recipients' => [[
        'statusCode' => 101, 'number' => '+254700000000', 'messageId' => 'mock-id', 'status' => 'Success',
    ]]]], JSON_THROW_ON_ERROR)];
}
$calls = 0;
$client = new AfricasTalkingClient('sandbox', 'test-key', 'sandbox', 'TEST', function ($url, $headers, $body) use (&$calls) {
    $calls++;
    check($url === 'https://api.sandbox.africastalking.com/version1/messaging', 'Sandbox URL');
    check(in_array('apiKey: test-key', $headers, true), 'API key header');
    check(in_array('Accept: application/json', $headers, true), 'JSON response requested');
    check(in_array('Content-Type: application/x-www-form-urlencoded', $headers, true), 'Form content type');
    parse_str($body, $form);
    check($form === ['username' => 'sandbox', 'to' => '+254700000000', 'message' => 'Hello + & karibu', 'from' => 'TEST'], 'Form preserves message and recipient');
    return accepted();
});
check($calls === 0, 'Construction must not dispatch');
check($client->send('+254700000000', 'Hello + & karibu') === 'mock-id', 'Return accepted message ID');
check($calls === 1, 'Single explicit request');
echo "PASS SMS request and acceptance (fake transport only)\n";
function fails(callable $action, string $expected): void {
    try { $action(); } catch (RuntimeException $error) {
        check($error->getMessage() === $expected, 'Expected sanitized error: '.$expected);
        check($error->getPrevious() === null, 'Do not expose underlying transport errors');
        return;
    }
    throw new RuntimeException('Expected failure: '.$expected);
}
$unexpected = function () { throw new LogicException('Transport must not run'); };
foreach ([['', 'key', 'live'], ['user', '', 'live'], ['user', "key\r\nx", 'live'], ['user', 'key', 'invalid'], ['user', 'key', 'sandbox'], ['sandbox', 'key', 'live']] as $config) {
    $invalid = new AfricasTalkingClient(...[...$config, '', $unexpected]);
    fails(fn() => $invalid->send('+254700000000', 'hello'), 'Invalid Africa’s Talking configuration');
}
$variables = ['AFRICAS_TALKING_USERNAME', 'AFRICAS_TALKING_API_KEY', 'AFRICAS_TALKING_ENVIRONMENT', 'AFRICAS_TALKING_SENDER_ID'];
$original = array_map('getenv', $variables);
try {
    foreach ($variables as $name) putenv($name);
    $unconfigured = AfricasTalkingClient::fromEnvironment($unexpected);
    fails(fn() => $unconfigured->send('+254700000000', 'hello'), 'Invalid Africa’s Talking configuration');
    putenv('AFRICAS_TALKING_USERNAME=example-app');
    putenv('AFRICAS_TALKING_API_KEY=fake-key');
    putenv('AFRICAS_TALKING_ENVIRONMENT=live');
    $live = AfricasTalkingClient::fromEnvironment(function ($url, $headers, $body) {
        check($url === 'https://api.africastalking.com/version1/messaging', 'Explicit live routing');
        parse_str($body, $form);
        check($form['username'] === 'example-app' && !isset($form['from']), 'Environment username and optional sender');
        check(in_array('apiKey: fake-key', $headers, true), 'Environment API key');
        return accepted();
    });
    check($live->send('+254700000000', 'hello') === 'mock-id', 'Live config with fake transport only');
} finally {
    foreach ($variables as $index => $name) putenv($original[$index] === false ? $name : $name.'='.$original[$index]);
}
echo "PASS SMS configuration (fake transport only)\n";
foreach ([
    [['status' => 401, 'body' => 'secret provider text'], 'SMS API HTTP 401; request not confirmed'],
    [['status' => 503, 'body' => 'secret provider text'], 'SMS API HTTP 503; request not confirmed'],
    [['status' => 200, 'body' => 'not json'], 'Invalid SMS API response; acceptance unknown'],
    [['status' => 200, 'body' => '{"SMSMessageData":{"Recipients":[]}}'], 'Invalid SMS API response; acceptance unknown'],
] as [$response, $error]) {
    $client = new AfricasTalkingClient('sandbox', 'key', 'sandbox', '', fn() => $response);
    fails(fn() => $client->send('+254700000000', 'hello'), $error);
}
foreach ([['statusCode', 403, 'SMS recipient rejected (code 403)'], ['number', '+254711111111', 'Invalid SMS API response; acceptance unknown'], ['messageId', '', 'Invalid SMS API response; acceptance unknown']] as [$field, $value, $error]) {
    $response = accepted();
    $body = json_decode($response['body'], true);
    $body['SMSMessageData']['Recipients'][0][$field] = $value;
    $response['body'] = json_encode($body);
    $client = new AfricasTalkingClient('sandbox', 'key', 'sandbox', '', fn() => $response);
    fails(fn() => $client->send('+254700000000', 'hello'), $error);
}
$calls = 0;
$client = new AfricasTalkingClient('sandbox', 'key', 'sandbox', '', function () use (&$calls) {
    $calls++;
    throw new RuntimeException('secret transport diagnostics');
});
fails(fn() => $client->send('+254700000000', 'hello'), 'SMS transport failure; acceptance unknown');
check($calls === 1, 'Never automatically retry');
echo "PASS SMS failure handling (fake transport only)\n";
$client = new AfricasTalkingClient('sandbox', 'key', 'sandbox', '', $unexpected);
foreach ([['0712345678', 'hello'], ['+254700000000,+254711111111', 'hello'], ['+254700000000', '  ']] as [$recipient, $message]) {
    fails(fn() => $client->send($recipient, $message), 'Provide one international-format recipient and a nonempty message');
}
echo "PASS SMS input validation (no transport calls)\n";
