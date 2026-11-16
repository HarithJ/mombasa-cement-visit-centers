<?php
declare(strict_types=1);
// Staff sign-in: passwords, sign-in links, password resets and the shared single-use token helper.
require __DIR__.'/../src/SignIn.php';
require __DIR__.'/../src/EmailWorker.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function throttled(callable $action): bool { try { $action(); return false; } catch (TooManyAttempts) { return true; } }
putenv('BOOKING_SITE_URL=https://visits.example.com');
$directory = sys_get_temp_dir().'/nyumba-sign-in-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory.'/bookings.sqlite';
try {
    $users = UserStore::open($path);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $signIn = new SignIn($db);
    $ada = $users->add(['name' => 'Ada Admin', 'email' => 'ada@example.test', 'phone' => '0712345678', 'role' => 'admin']);
    $tom = $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '0712345679', 'role' => 'team']);
    $db->prepare('UPDATE users SET password_hash = ?')->execute([password_hash('correct horse battery', PASSWORD_DEFAULT)]);
    $now = 1800000000;

    check($signIn->withPassword('10.0.0.1', 'ADA@example.test', 'correct horse battery', $now)['id'] === $ada['id'], 'An active user signs in with email and password');
    check($signIn->withPassword('10.0.0.1', 'ada@example.test', 'wrong password!!', $now) === null, 'A wrong password is refused');
    check($signIn->withPassword('10.0.0.1', 'nobody@example.test', 'correct horse battery', $now) === null, 'An unknown email is refused');
    $users->deactivate($tom['id']);
    check($signIn->withPassword('10.0.0.2', 'tom@example.test', 'correct horse battery', $now) === null, 'A deactivated user cannot sign in');
    for ($i = 0; $i < 3; $i++) $signIn->withPassword('10.0.0.1', 'ada@example.test', 'nope', $now + 10);
    check(throttled(fn() => $signIn->withPassword('10.0.0.1', 'ada@example.test', 'correct horse battery', $now + 20)), 'Five failures from one address block even correct details');
    check($signIn->withPassword('10.0.0.3', 'ada@example.test', 'correct horse battery', $now + 20)['id'] === $ada['id'], 'Other addresses are not blocked');
    check($signIn->withPassword('10.0.0.1', 'ada@example.test', 'correct horse battery', $now + 901)['id'] === $ada['id'], 'The block ends after 15 minutes');
    echo "PASS password sign-in for active users with per-address throttling\n";

    // Delivers queued account emails through the normal email worker, as the cron job would.
    $deliver = function (int $at) use ($db): array {
        $sent = [];
        (new EmailWorker($db, fn() => $at, 'account_emails'))->run(function ($payload, $key) use (&$sent) { $sent[] = $payload; return 'mock-'.count($sent); }, 'bookings@example.com');
        return $sent;
    };
    $tokenFrom = function (array $email, string $path): string {
        check(preg_match('#https://visits\.example\.com'.preg_quote($path, '#').'\?token=([a-f0-9]{64})#', $email['text'], $match) === 1, 'The email links to '.$path);
        check(str_contains($email['html'], $match[0]), 'The HTML email has the same link');
        return $match[1];
    };
    $signIn->requestLink('Ada@Example.test', $now);
    $sent = $deliver($now);
    check(count($sent) === 1 && $sent[0]['to'] === ['ada@example.test'], 'An active user is emailed a sign-in link');
    check($sent[0]['subject'] === 'Your sign-in link for Visit Nyumba admin', 'The sign-in email has the approved subject');
    check(str_contains($sent[0]['text'], "Hi Ada, here's your link to sign in. It works once and expires in 15 minutes."), 'The sign-in email greets by first name');
    check(str_contains($sent[0]['text'], "Sign in:\n") && str_contains($sent[0]['html'], EmailTemplate::escape("If you didn't ask for this, you can ignore this email.")), 'The sign-in email has its button and footer');
    $token = $tokenFrom($sent[0], '/admin/login/link');
    check($signIn->withLink($token, $now + 60)['id'] === $ada['id'], 'The link signs the user in');
    check($signIn->withLink($token, $now + 61) === null, 'The link works once');
    $signIn->requestLink('ada@example.test', $now + 100);
    $late = $tokenFrom($deliver($now + 100)[0], '/admin/login/link');
    check($signIn->withLink($late, $now + 100 + 900) === null, 'The link expires after 15 minutes');
    $signIn->requestLink('ada@example.test', $now + 200);
    check(count($deliver($now + 200)) === 1, 'A third request within 15 minutes is sent');
    $signIn->requestLink('ada@example.test', $now + 300);
    check($deliver($now + 300) === [], 'A fourth request within 15 minutes is not sent');
    $signIn->requestLink('ada@example.test', $now + 901);
    check(count($deliver($now + 901)) === 1, 'Requests are allowed again once earlier ones are 15 minutes old');
    $signIn->requestLink('tom@example.test', $now);
    $signIn->requestLink('nobody@example.test', $now);
    check($deliver($now + 1000) === [], 'Deactivated and unknown emails get nothing');
    $signIn->requestLink('ada@example.test', $now + 2000);
    $stale = $tokenFrom($deliver($now + 2000)[0], '/admin/login/link');
    $users->reactivate($tom['id']);
    $signIn->requestLink('tom@example.test', $now + 2000);
    $tomToken = $tokenFrom($deliver($now + 2000)[0], '/admin/login/link');
    $users->edit($ada['id'], ['email' => 'ada@new.example.test']);
    check($signIn->withLink($stale, $now + 2001) === null, 'Changing the email stops unused links');
    $users->deactivate($tom['id']);
    check($signIn->withLink($tomToken, $now + 2001) === null, 'Deactivation stops unused links');
    check($signIn->withLink('not-a-token', $now) === null && $signIn->withLink(str_repeat('a', 64), $now) === null, 'Unknown tokens are refused');
    echo "PASS sign-in links are single use, expire after 15 minutes, are limited and only go to active accounts\n";

    $now += 5000;
    $signIn->requestReset('ADA@new.example.test', $now);
    $sent = $deliver($now);
    check(count($sent) === 1 && $sent[0]['to'] === ['ada@new.example.test'] && $sent[0]['subject'] === 'Reset your Visit Nyumba admin password', 'An active user is emailed a reset link');
    check(str_contains($sent[0]['text'], 'Hi Ada, use this link to set a new password. It works once and expires in 1 hour.') && str_contains($sent[0]['text'], "Set a new password:\n"), 'The reset email has the approved wording');
    check(str_contains($sent[0]['html'], EmailTemplate::escape("If you didn't ask for this, you can ignore this email. Your password won't change.")), 'The reset email has the approved footer');
    $reset = $tokenFrom($sent[0], '/admin/reset');
    check($signIn->withLink($reset, $now) === null, 'A reset link does not sign in by itself');
    check($signIn->resetUser($reset, $now + 3599)['id'] === $ada['id'], 'A reset link is valid for an hour');
    check($signIn->resetUser($reset, $now + 3600) === null, 'A reset link expires after an hour');
    $rejected = function (string $password, string $confirm, string $message) use ($signIn, $reset, $now) {
        try { $signIn->resetPassword($reset, $password, $confirm, $now + 10); } catch (UserError $error) { check(($error->errors['password'] ?? null) === $message, "Expected '$message'"); return; }
        throw new RuntimeException("Expected '$message'");
    };
    $rejected('elevenchars', 'elevenchars', 'Use at least 12 characters.');
    $rejected('twelve chars', 'twelve charz', "The passwords don't match.");
    $signIn->requestLink('ada@new.example.test', $now);
    $unusedLink = $tokenFrom($deliver($now)[0], '/admin/login/link');
    $before = $users->find($ada['id']);
    $saved = $signIn->resetPassword($reset, 'twelve chars', 'twelve chars', $now + 10);
    check($saved['id'] === $ada['id'] && $saved['credential_version'] > $before['credential_version'], 'Saving a password moves the credential counter on, ending other sessions');
    check($signIn->withPassword('10.0.0.9', 'ada@new.example.test', 'twelve chars', $now + 20)['id'] === $ada['id'], 'The new password works');
    check($signIn->withPassword('10.0.0.9', 'ada@new.example.test', 'correct horse battery', $now + 20) === null, 'The old password no longer works');
    check($signIn->resetPassword($reset, 'another password', 'another password', $now + 30) === null, 'A reset link works once');
    check($signIn->withLink($unusedLink, $now + 30) === null, 'Saving a password stops other unused links');
    $signIn->requestReset('tom@example.test', $now);
    $signIn->requestReset('nobody@example.test', $now);
    check($deliver($now + 40) === [], 'Deactivated and unknown emails get no reset link');
    echo "PASS reset links last an hour, need 12 matching characters and end other sessions\n";

    $welcome = AuthTokens::issue($db, $users->find($ada['id']), 'welcome', $now);
    check(AuthTokens::link('welcome', $welcome) === 'https://visits.example.com/admin/login/link?token='.$welcome, 'Welcome links use the sign-in link page');
    check($signIn->resetUser($welcome, $now) === null, 'A welcome link cannot reset a password');
    check($signIn->withLink($welcome, $now + 172799)['id'] === $ada['id'], 'A welcome link signs in within 48 hours');
    check($signIn->withLink($welcome, $now + 172799) === null, 'A welcome link signs in once');
    $expired = AuthTokens::issue($db, $users->find($ada['id']), 'welcome', $now);
    check($signIn->withLink($expired, $now + 172800) === null, 'A welcome link expires after 48 hours');
    check(!$db->query("SELECT 1 FROM auth_tokens WHERE token_hash IN ('$welcome', '$reset')")->fetchColumn(), 'Raw tokens are never stored');
    echo "PASS welcome tokens sign in once within 48 hours\n";
} finally {
    foreach (glob($directory.'/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Sign-in tests passed.\n";
