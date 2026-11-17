<?php
// Emails queued by a web request go out right after the page is sent; the worker timer retries anything left.
declare(strict_types=1);
require __DIR__.'/../src/UserStore.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function play(string $scenario): array {
    $dir = sys_get_temp_dir().'/nyumba-dispatch-'.bin2hex(random_bytes(8)); mkdir($dir);
    $path = "$dir/bookings.sqlite";
    $users = UserStore::open($path);
    $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0711000001', 'role' => 'admin']);
    $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0711000002', 'role' => 'team']);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/helpers/send-after-response.php').' '.escapeshellarg($path).' '.escapeshellarg($dir).' '.escapeshellarg($scenario).' 2>&1', $lines, $exit);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $status = fn(string $table) => $db->query("SELECT status, COUNT(*) FROM $table GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['exit' => $exit, 'events' => array_map(fn($line) => json_decode($line, true) ?? $line, $lines), 'account' => $status('account_emails'), 'booking' => $status('booking_emails'), 'db' => $db];
}

$run = play('ok');
check($run['exit'] === 0, 'The request ends cleanly: '.json_encode($run['events']));
check($run['events'][0] === 'response', 'The page is produced before any email is sent');
check($run['events'][1] === 'finish:session-closed', 'The session is saved and closed before the response is finished, so the next page never waits on it');
$sends = array_slice($run['events'], 2);
check($sends === ['send:ada@example.test', 'send:ada@example.test', 'send:tom@example.test', 'send:visitor@example.com'], 'Sign-in link first, then every queued booking email: '.json_encode($sends));
check($run['account'] === ['accepted' => 1] && $run['booking'] === ['accepted' => 3], 'Everything queued is marked sent');

$run = play('no-finisher');
check($run['events'] === ['response'], 'Without a way to finish the response first (not PHP-FPM), nothing is sent during the request');
check($run['account'] === ['pending' => 1] && $run['booking'] === ['pending' => 3], 'The worker timer still sends them');

$run = play('provider-down');
check($run['exit'] === 0 && $run['events'] === ['response', 'finish:session-closed'], 'A provider failure never surfaces in the request: '.json_encode($run['events']));
check($run['account'] === ['pending' => 1] && $run['booking'] === ['pending' => 3], 'Failed emails stay queued');
$retry = $run['db']->query("SELECT MIN(next_attempt_at) - MAX(first_attempt_at) FROM (SELECT next_attempt_at, first_attempt_at FROM account_emails UNION ALL SELECT next_attempt_at, first_attempt_at FROM booking_emails)")->fetchColumn();
check((int)$retry >= 60, 'and are left for the worker to retry later, as a worker failure would be');
echo "Email dispatch tests passed.\n";
