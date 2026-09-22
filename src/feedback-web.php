<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
ini_set('session.use_strict_mode', '1');
if ($sessionPath = getenv('BOOKING_SESSION_PATH')) session_save_path($sessionPath);
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax','path'=>'/']);
session_start();
$_SESSION['feedbackCsrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['feedbackCsrf'];
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$input = []; $errors = []; $invitation = null; $unavailable = false; $preview = false;
try {
    $path = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
    new BookingStore($path);
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $feedback = new Feedback($db);
    $invitation = $feedback->find($token);
    if (!$invitation) { http_response_code(404); $unavailable = true; }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['attendance','rating','enjoyment','improvement','comments'] as $key) $input[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
            http_response_code(403); $errors['_form'] = 'Your form expired. Please review your answers and try again.';
        } else {
            $errors = $feedback->submit($token, $_POST);
            if (!$errors) {
                $route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                header('Location: '.$route.'?token='.$token, true, 303); exit;
            }
            http_response_code(422);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); $unavailable = true; }
} catch (Throwable $error) {
    error_log('Nyumba feedback failure: '.get_class($error));
    http_response_code(503); $errors['_form'] = 'Feedback is temporarily unavailable. Please try again shortly.';
    if (!$invitation) $unavailable = true;
}
require __DIR__.'/views/feedback.php';
