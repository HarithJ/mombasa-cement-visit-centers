<?php
// Adds a fictional staff user with a known password: seed-user.php <db> <name> <email> <role> <password>
declare(strict_types=1);
require __DIR__.'/../../src/UserStore.php';
[, $path, $name, $email, $role, $password] = $argv;
$user = UserStore::open($path)->add(['name' => $name, 'email' => $email, 'phone' => '0700000'.random_int(100, 999), 'role' => $role]);
(new PDO('sqlite:'.$path))->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
echo $user['id'];
