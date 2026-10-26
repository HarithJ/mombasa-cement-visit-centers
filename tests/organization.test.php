<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingStore.php';
require __DIR__.'/../src/DayBooking.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$directory = sys_get_temp_dir().'/nyumba-organization-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory.'/bookings.sqlite';
putenv('BOOKING_EMAIL_ENABLED=0');
putenv('FEEDBACK_EMAIL_ENABLED=0');
try {
    $store = new BookingStore($path);
    $schedule = require __DIR__.'/../config/destinations.php';
    $base = ['fullName'=>'Org visitor','phone'=>'0712345678','email'=>'visitor@example.com','location'=>'feeding','visitDate'=>'2099-05-01','timeSlot'=>'11:00','attendees'=>'3'];

    foreach ([[null, null], ['', null], ['   ', null], ['  Mombasa Primary School  ', 'Mombasa Primary School']] as [$choice, $expected]) {
        $input = $base;
        if ($choice !== null) $input['organization'] = $choice;
        [$booking, $errors] = DayBooking::validate($input, $schedule);
        check(!$errors, 'Organization is optional');
        $saved = $store->create($booking, 'org-'.md5(var_export($choice, true)));
        check($saved['organization'] === $expected, 'Organization saved as '.var_export($expected, true));
        check($store->adminBooking((int)$saved['id'])['organization'] === $expected, 'Organization available to admin');
        $details = BookingEmail::payload($saved)['text'];
        check(str_contains($details, 'Organization: '.($expected ?? 'Not provided')), 'Team email lists organization');
    }

    [, $errors] = DayBooking::validate($base + ['organization'=>str_repeat('a', 120)], $schedule);
    check(!isset($errors['organization']), '120 characters accepted');
    [, $errors] = DayBooking::validate($base + ['organization'=>str_repeat('a', 121)], $schedule);
    check(isset($errors['organization']), '121 characters rejected');
    [, $errors] = DayBooking::validate($base + ['organization'=>"Bad\nname"], $schedule);
    check(isset($errors['organization']), 'Control characters rejected');
    [, $errors] = DayBooking::validate(['organization'=>'Kept'] + $base, $schedule);
    check(!$errors, 'Valid organization leaves no errors');

    $store = new BookingStore($path);
    check($store->findByToken('org-'.md5(var_export('  Mombasa Primary School  ', true)))['organization'] === 'Mombasa Primary School', 'Restart and repeat migration preserve organization');
    echo "PASS organization: optional, trimmed, validated, persisted, admin and team email\n";
} finally {
    unset($store);
    array_map('unlink', glob($directory.'/*') ?: []); @rmdir($directory);
}
