<?php
declare(strict_types=1);
// Staff accounts: the UserStore module and its server command, bin/users.php.
require __DIR__.'/../src/UserStore.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejects(callable $action, string $field, string $message): void {
    try { $action(); } catch (UserError $error) {
        check(($error->errors[$field] ?? null) === $message, "Expected '$message' for $field, got ".json_encode($error->errors));
        return;
    }
    throw new RuntimeException("Expected '$message' for $field");
}
$directory = sys_get_temp_dir().'/nyumba-users-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory.'/bookings.sqlite';
try {
    $users = UserStore::open($path);
    $ada = $users->add(['name' => ' Ada Admin ', 'email' => 'Ada@Example.test', 'phone' => '0712 345678', 'role' => 'admin']);
    check($ada['name'] === 'Ada Admin' && $ada['email'] === 'Ada@Example.test' && $ada['phone'] === '+254712345678' && $ada['role'] === 'admin' && $ada['active'] === 1, 'A new user is saved with tidied details, active');
    check(password_get_info($ada['password_hash'])['algo'] !== null, 'A new user gets a password hash');
    $tom = $users->add(['name' => 'Tom Team', 'email' => 'tom@example.test', 'phone' => '+44 7700 900123', 'role' => 'team']);
    check($tom['password_hash'] !== $ada['password_hash'], 'Each user gets a different random password');
    check(array_column($users->list(), 'name') === ['Ada Admin', 'Tom Team'], 'All users are listed');
    check($users->findByEmail('ADA@example.TEST')['id'] === $ada['id'], 'Users are found by email regardless of case');
    check($users->findByEmail('nobody@example.test') === null, 'Unknown emails find nobody');
    echo "PASS users are added, listed and found by email\n";

    $valid = ['name' => 'Val Id', 'email' => 'val@example.test', 'phone' => '0712345678', 'role' => 'team'];
    rejects(fn() => $users->add(array_merge($valid, ['name' => '   '])), 'name', 'Please enter a name.');
    rejects(fn() => $users->add(array_merge($valid, ['name' => "Bad\nName"])), 'name', 'Please enter a name.');
    rejects(fn() => $users->add(array_merge($valid, ['email' => 'not-an-email'])), 'email', 'Please enter a valid email address.');
    rejects(fn() => $users->add(array_merge($valid, ['phone' => '12'])), 'phone', 'Please enter a valid phone number.');
    rejects(fn() => $users->add(array_merge($valid, ['role' => 'owner'])), 'role', 'Please choose Admin or Team.');
    rejects(fn() => $users->add(array_merge($valid, ['email' => ' ADA@example.test '])), 'email', 'Someone already uses that email address.');
    check(count($users->list()) === 2, 'Refused users are not saved');
    echo "PASS invalid details and reused email addresses are refused\n";

    $before = $users->find($tom['id']);
    $edited = $users->edit($tom['id'], ['name' => 'Tom T', 'phone' => '0722 000111']);
    check($edited['name'] === 'Tom T' && $edited['phone'] === '+254722000111' && $edited['email'] === 'tom@example.test' && $edited['role'] === 'team', 'Edit changes only the given fields');
    check($edited['credential_version'] === $before['credential_version'], 'Editing name or phone keeps sessions and links working');
    check($users->edit($tom['id'], ['email' => 'TOM@example.test'])['credential_version'] === $before['credential_version'], 'Changing only the case of an email is not an email change');
    $moved = $users->edit($tom['id'], ['email' => 'tom.t@example.test']);
    check($moved['email'] === 'tom.t@example.test' && $moved['credential_version'] === $before['credential_version'] + 1, 'Changing email ends sessions and links');
    rejects(fn() => $users->edit($tom['id'], ['email' => 'ada@example.test']), 'email', 'Someone already uses that email address.');
    rejects(fn() => $users->edit($tom['id'], ['name' => '']), 'name', 'Please enter a name.');
    check($users->edit($ada['id'], ['email' => 'ada@example.test'])['email'] === 'ada@example.test', 'A user may keep their own email address');
    echo "PASS users are edited, and an email change bumps their credentials\n";

    check(array_column($users->activeUsers(), 'name') === ['Ada Admin', 'Tom T'] && array_column($users->activeAdmins(), 'name') === ['Ada Admin'], 'Active users and admins are listed');
    rejects(fn() => $users->deactivate($ada['id']), 'active', 'There must be at least one active admin.');
    rejects(fn() => $users->edit($ada['id'], ['role' => 'team']), 'role', 'There must be at least one active admin.');
    check($users->find($ada['id'])['active'] === 1 && $users->find($ada['id'])['role'] === 'admin', 'The last active admin stays an active admin');
    $bea = $users->add(['name' => 'Bea Admin', 'email' => 'bea@example.test', 'phone' => '0733111222', 'role' => 'admin']);
    rejects(fn() => $users->deactivate($bea['id'], $bea['id']), 'active', "You can't deactivate your own account.");
    $version = $users->find($tom['id'])['credential_version'];
    $off = $users->deactivate($tom['id'], $ada['id']);
    check($off['active'] === 0 && $off['credential_version'] === $version + 1, 'Deactivating ends sessions and links');
    check(array_column($users->activeUsers(), 'name') === ['Ada Admin', 'Bea Admin'], 'Deactivated users drop out of active users');
    check(in_array('Tom T', array_column($users->list(), 'name'), true), 'Deactivated users are still listed');
    $on = $users->reactivate($tom['id']);
    check($on['active'] === 1 && $on['credential_version'] === $version + 1, 'Reactivating does not revive old sessions or links');
    $users->deactivate($ada['id'], $bea['id']);
    check(array_column($users->activeAdmins(), 'name') === ['Bea Admin'], 'Another admin can be deactivated while one stays active');
    rejects(fn() => $users->edit($bea['id'], ['role' => 'team']), 'role', 'There must be at least one active admin.');
    $users->edit($ada['id'], ['role' => 'team']);
    check($users->find($ada['id'])['role'] === 'team', 'An inactive admin can be changed to team');
    $users->reactivate($ada['id']); $users->edit($ada['id'], ['role' => 'admin']);
    echo "PASS users are deactivated and reactivated, keeping one active admin\n";

    // The server command, on its own fresh database.
    $cliPath = $directory.'/cli.sqlite';
    $cli = function (string ...$args) use ($cliPath): array {
        $process = proc_open([PHP_BINARY, __DIR__.'/../bin/users.php', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['BOOKING_DB' => $cliPath, 'PATH' => getenv('PATH')]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        return [proc_close($process), $out, $err];
    };
    [$status, $out] = $cli('list');
    check($status === 0 && str_contains($out, 'No users yet.'), 'An empty list says so');
    [$status, $out, $err] = $cli('add', '--name=Cleo Admin', '--email=cleo@example.test', '--phone=0712 000001', '--role=admin', '--no-welcome');
    check($status === 0 && str_contains($out, 'Cleo Admin added.') && $err === '', 'add creates a user: '.$out.$err);
    [$status, $out] = $cli('add', '--name=Dan Team', '--email=dan@example.test', '--phone=0712000002', '--role=team');
    check($status === 0 && str_contains($out, 'Dan Team added.') && str_contains($out, 'No welcome email was sent'), 'add without --no-welcome says no welcome email was sent yet');
    $cliUsers = UserStore::open($cliPath);
    foreach ($cliUsers->list() as $user) check(!str_contains($out, $user['password_hash']), 'No password hash is printed');
    [$status, $out] = $cli('list');
    check($status === 0 && str_contains($out, 'Cleo Admin') && str_contains($out, 'cleo@example.test') && str_contains($out, '+254712000001') && str_contains($out, 'Admin') && str_contains($out, 'Active'), 'list shows each user: '.$out);
    check(!preg_match('/password|\$2y\$|\$argon/i', $out), 'list never shows passwords or hashes');
    [$status, , $err] = $cli('add', '--name=Eve', '--email=DAN@example.test', '--phone=0712000003', '--role=team', '--no-welcome');
    check($status === 1 && str_contains($err, 'Someone already uses that email address.'), 'add refuses a reused email');
    [$status, , $err] = $cli('add', '--name=', '--email=bad', '--phone=1', '--role=team', '--no-welcome');
    check($status === 1 && str_contains($err, 'Please enter a name.') && str_contains($err, 'Please enter a valid email address.') && str_contains($err, 'Please enter a valid phone number.'), 'add lists every problem');
    [$status, $out] = $cli('edit', 'dan@example.test', '--email=dan.t@example.test', '--name=Dan T');
    check($status === 0 && str_contains($out, 'Changes saved.'), 'edit saves changes');
    check($cliUsers->findByEmail('dan.t@example.test')['credential_version'] === 2, 'edit by command bumps credentials on email change');
    [$status, , $err] = $cli('edit', 'cleo@example.test', '--role=team');
    check($status === 1 && str_contains($err, 'There must be at least one active admin.'), 'edit keeps the last admin');
    [$status, , $err] = $cli('deactivate', 'cleo@example.test');
    check($status === 1 && str_contains($err, 'There must be at least one active admin.'), 'deactivate keeps the last admin');
    [$status, $out] = $cli('deactivate', 'DAN.T@example.test');
    check($status === 0 && str_contains($out, 'Dan T deactivated.') && $cliUsers->findByEmail('dan.t@example.test')['active'] === 0, 'deactivate works by email');
    [$status, $out] = $cli('list');
    check(str_contains($out, 'Deactivated'), 'list shows deactivated users');
    [$status, $out] = $cli('reactivate', 'dan.t@example.test');
    check($status === 0 && str_contains($out, 'Dan T reactivated.') && $cliUsers->findByEmail('dan.t@example.test')['active'] === 1, 'reactivate works by email');
    [$status, , $err] = $cli('deactivate', 'nobody@example.test');
    check($status === 1 && str_contains($err, 'No user has the email address nobody@example.test.'), 'Unknown emails are reported');
    [$status, , $err] = $cli('frobnicate');
    check($status === 1 && str_contains($err, 'Usage:'), 'Unknown commands show usage');
    echo "PASS bin/users.php mirrors the user rules and never prints passwords\n";
} finally { foreach (glob($directory.'/*') as $file) unlink($file); rmdir($directory); }
