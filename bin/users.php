<?php
declare(strict_types=1);
// Manage staff accounts from the server, with the same rules as the admin Users page.
require __DIR__.'/../src/SignIn.php';
const USAGE = <<<'TXT'
Usage:
  php bin/users.php list
  php bin/users.php add --name=NAME --email=EMAIL --phone=PHONE --role=admin|team [--by=ADMIN_EMAIL | --no-welcome]
  php bin/users.php edit EMAIL [--name=NAME] [--email=NEW_EMAIL] [--phone=PHONE] [--role=admin|team]
  php bin/users.php deactivate EMAIL
  php bin/users.php reactivate EMAIL

add emails the new user a 48-hour sign-in link. The welcome names the admin given with --by,
or else the longest-standing active admin.

TXT;
function fail(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
$command = $argv[1] ?? '';
$positional = []; $options = []; $flags = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $match)) $options[$match[1]] = $match[2];
    elseif (str_starts_with($arg, '--')) $flags[] = substr($arg, 2);
    else $positional[] = $arg;
}
$dbPath = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
$users = UserStore::open($dbPath);
$target = function () use ($positional, $users): array {
    if (count($positional) !== 1) fail(USAGE);
    return $users->findByEmail($positional[0]) ?? fail("No user has the email address {$positional[0]}.");
};
try {
    switch ($command) {
        case 'list':
            $all = $users->list();
            if (!$all) { echo "No users yet.\n"; break; }
            foreach ($all as $user) printf("%-20s  %-32s  %-16s  %-5s  %s\n", $user['name'], $user['email'], $user['phone'], ucfirst($user['role']), $user['active'] ? 'Active' : 'Deactivated');
            break;
        case 'add':
            if ($positional || array_diff($flags, ['no-welcome'])) fail(USAGE);
            $by = $options['by'] ?? null; unset($options['by']);
            if (in_array('no-welcome', $flags, true)) {
                if ($by !== null) fail(USAGE);
                $user = $users->add($options);
                echo "{$user['name']} added.\n";
                break;
            }
            $admins = $users->activeAdmins();
            usort($admins, fn($a, $b) => $a['id'] <=> $b['id']);
            if ($by !== null) $admins = array_filter($admins, fn($admin) => strcasecmp($admin['email'], trim($by)) === 0) ?: fail("$by is not an active admin.");
            $admin = reset($admins) ?: fail('No active admin to name in the welcome email. Add --no-welcome, or --by=ADMIN_EMAIL.');
            $user = SignIn::open($dbPath)->addUser($options, $admin['name'], time());
            echo "{$user['name']} added. We've sent them a welcome email.\n";
            break;
        case 'edit':
            $user = $target();
            if ($flags || array_diff(array_keys($options), ['name', 'email', 'phone', 'role'])) fail(USAGE);
            $users->edit((int)$user['id'], $options);
            echo "Changes saved.\n";
            break;
        case 'deactivate':
            $user = $users->deactivate((int)$target()['id']);
            echo "{$user['name']} deactivated.\n";
            break;
        case 'reactivate':
            $user = $users->reactivate((int)$target()['id']);
            echo "{$user['name']} reactivated.\n";
            break;
        default:
            fail(USAGE);
    }
} catch (UserError $error) {
    fail(implode("\n", $error->errors));
}
