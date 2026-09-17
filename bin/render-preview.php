<?php
declare(strict_types=1);

// Build-time rendering only: never load web.php, sessions or booking storage.
$version = $argv[1] ?? '';
if (!in_array($version, ['v1', 'v2', 'v3'], true)) {
    fwrite(STDERR, "Expected v1, v2 or v3.\n");
    exit(1);
}
$schedule = require __DIR__.'/../config/destinations.php';
$input = []; $errors = []; $confirmation = null; $openForm = false;
$token = ''; $_SESSION = ['csrf' => ''];
$bookingPath = './';
$webState = ['input' => [], 'errors' => [], 'confirmation' => null, 'openForm' => false];
require __DIR__.'/../src/views/'.$version.'.php';
