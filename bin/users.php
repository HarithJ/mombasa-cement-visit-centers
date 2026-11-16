<?php
declare(strict_types=1);
// Manage staff accounts from the server, with the same rules as the admin Users page.
require __DIR__.'/../src/UserStore.php';
const USAGE = <<<'TXT'
Usage:
  php bin/users.php list
  php bin/users.php add --name=NAME --email=EMAIL --phone=PHONE --role=admin|team [--no-welcome]
  php bin/users.php edit EMAIL [--name=NAME] [--email=NEW_EMAIL] [--phone=PHONE] [--role=admin|team]
  php bin/users.php deactivate EMAIL
  php bin/users.php reactivate EMAIL

TXT;
function fail(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
$command = $argv[1] ?? '';
$positional = []; $options = []; $flags = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $match)) $options[$match[1]] = $match[2];
    elseif (str_starts_with($arg, '--')) $flags[] = substr($arg, 2);
    else $positional[] = $arg;
}
$users = UserStore::open(getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite');
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
            $user = $users->add($options);
            echo "{$user['name']} added.\n";
            // Ticket 06 queues the welcome email here.
            if (!in_array('no-welcome', $flags, true)) echo "No welcome email was sent: welcome emails are not available yet.\n";
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
