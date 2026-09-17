<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
require_once __DIR__.'/DayBooking.php';
require_once __DIR__.'/Schedule.php';
$schedule = Schedule::load();
ini_set('session.use_strict_mode', '1');
if ($sessionPath = getenv('BOOKING_SESSION_PATH')) session_save_path($sessionPath);
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$errors = []; $input = []; $confirmation = null; $openForm = false;
$dbPath = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
$newToken = fn() => bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $openForm = true;
    [$input, $errors] = DayBooking::validate($_POST, $schedule);
    $csrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $token = is_string($_POST['submissionToken'] ?? null) ? $_POST['submissionToken'] : '';
    if (!hash_equals($_SESSION['csrf'], $csrf) || !isset($_SESSION['tokens'][$token])) {
        http_response_code(403); $errors['_form'] = 'This form has expired. Please review your details and submit again.';
        $token = $newToken(); $_SESSION['tokens'][$token] = true;
    } elseif (!$errors) {
        try {
            $store = new BookingStore($dbPath);
            $saved = $store->create($input, hash('sha256', $token));
            $_SESSION['confirmationToken'] = hash('sha256', $token);
            header('Location: '.$bookingPath.'?confirmation=1', true, 303); exit;
        } catch (Throwable $error) {
            error_log('Nyumba booking storage failure: '.get_class($error));
            http_response_code(503); $errors['_form'] = 'We could not save your visit. Your details are still here; please try again. No confirmation has been issued.';
        }
    } else http_response_code(422);
} else {
    $token = $newToken(); $_SESSION['tokens'][$token] = true;
    if (is_string($_GET['book'] ?? null) && isset($schedule[$_GET['book']])) {
        $input['location'] = $_GET['book']; $openForm = true;
    }
    if (isset($_GET['confirmation'], $_SESSION['confirmationToken'])) {
        try { $confirmation = (new BookingStore($dbPath))->findByToken($_SESSION['confirmationToken']); }
        catch (Throwable $error) { http_response_code(503); $errors['_form'] = 'Your confirmation is temporarily unavailable. Please try refreshing.'; }
    }
}
$webState = ['input' => $input, 'errors' => $errors, 'confirmation' => $confirmation, 'openForm' => $openForm];
