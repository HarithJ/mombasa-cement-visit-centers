<?php
declare(strict_types=1);
// The pages behind the Accept/Reject buttons in the team email. Opening a link (GET) only shows the
// booking; the decision is made by the POSTed form, as the link's user, through BookingDecisions.
require_once __DIR__.'/BookingDecisions.php';
require_once __DIR__.'/DecisionLinks.php';
require_once __DIR__.'/HttpsRequest.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
ini_set('session.use_strict_mode', '1');
session_cache_limiter(''); // keep the Cache-Control header above
if ($sessionPath = getenv('BOOKING_SESSION_PATH')) session_save_path($sessionPath);
session_set_cookie_params(['httponly' => true, 'secure' => HttpsRequest::secure(), 'samesite' => 'Lax', 'path' => '/']);
session_start();
$_SESSION['decisionCsrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['decisionCsrf'];
$now = $decisionNow ?? time();
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
// What to show: invalid | accept | reject | done | status | unavailable.
$page = 'invalid'; $link = null; $booking = null; $latest = null; $errors = []; $reason = ''; $done = null; $open = false;
try {
    $path = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
    $db = BookingStore::connect($path);
    $decisions = new BookingDecisions($db);
    $link = DecisionLinks::find($db, $token);
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); $link = null; }
    elseif (!$link) http_response_code(404);
    else {
        $booking = $link['booking'];
        $page = in_array($_GET['action'] ?? null, ['accept', 'reject'], true) ? $_GET['action'] : $link['action'];
        $flash = DecisionLinks::hash($token);
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['decisionDone'][$flash])) {
            $page = 'done'; $done = $_SESSION['decisionDone'][$flash];
            unset($_SESSION['decisionDone'][$flash]);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $decision = $_POST['decision'] ?? null;
            $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
            if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
                http_response_code(403); $errors['_form'] = 'This page has expired. Please try again.';
            } elseif (!in_array($decision, BookingStatus::DECISIONS, true)) {
                http_response_code(400); $errors['_form'] = 'Please try again.';
            } else {
                $result = $decisions->decide((int)$booking['id'], $link['user'], $decision, $reason, 'first', $now);
                $booking = $result['booking'];
                if ($result['ok']) {
                    $_SESSION['decisionDone'][$flash] = $decision;
                    header('Location: /decide?token='.$token, true, 303); exit;
                }
                if ($result['error'] === 'invalid') { http_response_code(422); $page = 'reject'; $errors = $result['errors']; }
                else http_response_code(409);
            }
        }
        $open = BookingDecisions::decidable($booking, $now);
        if ($page !== 'done' && ($booking['status'] !== 'pending' || !$open)) $page = 'status';
        if ($page === 'status') $latest = $decisions->history((int)$booking['id'])[0] ?? null;
    }
} catch (Throwable $error) {
    error_log('Nyumba decision failure: '.get_class($error));
    http_response_code(503); $page = 'unavailable';
}
require __DIR__.'/views/decide.php';
