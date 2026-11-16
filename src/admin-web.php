<?php
declare(strict_types=1);
require_once __DIR__.'/AdminAuth.php';
require_once __DIR__.'/SignIn.php';
require_once __DIR__.'/BookingStore.php';
require_once __DIR__.'/Schedule.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$adminScriptPolicy = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/admin/login' ? " script-src 'self';" : '';
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none';$adminScriptPolicy");
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$trusted = array_filter(array_map('trim', explode(',', getenv('ADMIN_TRUSTED_PROXIES') ?: '')));
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)) $secure = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (!$secure && PHP_SAPI !== 'cli-server') { http_response_code(503); echo 'Administration unavailable.'; return; }
ini_set('session.use_strict_mode', '1');
if ($sessionPath = getenv('BOOKING_SESSION_PATH')) session_save_path($sessionPath);
session_name('nyumba_admin');
session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/admin']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$now = $adminNow ?? time();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$error = ''; $rows = []; $page = 1; $filters = []; $booking = null;
// Set when a sign-in page (login, link sent, forgot, reset, expired) is shown instead of the workspace.
$authView = null; $sentTo = ''; $resetToken = '';
$dbPath = getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite';
$destinations = array_map(fn($destination) => $destination['name'], Schedule::load());
// Bookings kept from destinations no longer offered stay listed under a generic label.
function adminPlace(array $destinations, string $key): string { return $destinations[$key] ?? 'Retired destination'; }
foreach (['q', 'destination', 'from', 'to'] as $key) {
    $filters[$key] = is_string($_GET[$key] ?? '') ? trim($_GET[$key] ?? '') : '';
    if (isset($_GET[$key]) && !is_string($_GET[$key])) $error = 'Please check the filters.';
}
if (strlen($filters['q']) > 200 || ($filters['destination'] !== '' && !isset($destinations[$filters['destination']]))) $error = 'Please check the filters.';
foreach (['from', 'to'] as $key) {
    $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $filters[$key]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]) : false;
    if ($filters[$key] !== '' && (!$date || $date->format('Y-m-d') !== $filters[$key])) $error = 'Enter valid visit dates.';
}
if ($filters['from'] && $filters['to'] && $filters['from'] > $filters['to']) $error = 'The end date must be on or after the start date.';
function adminListUrl(array $filters, int $page, string $base = '/admin'): string { return $base.'?'.http_build_query(array_filter($filters, fn($value) => $value !== '') + ['page' => $page]); }

function ae(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function adminRedirect(string $target): never { header('Location: '.$target, true, 303); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
try {
    $signIn = SignIn::open($dbPath);
    $currentUser = AdminAuth::user($now, UserStore::open($dbPath));
} catch (Throwable $exception) { http_response_code(503); echo 'Administration temporarily unavailable.'; return; }
$authenticated = $currentUser !== null;
$signInPath = in_array($path, ['/admin/login', '/admin/login/link', '/admin/forgot', '/admin/reset'], true);
if (!$signInPath && !$authenticated) adminRedirect('/admin/login');
$postedEmail = is_string($_POST['email'] ?? null) ? trim(substr($_POST['email'], 0, 254)) : '';
// An unknown, used or expired link gets one page that never says which.
function adminExpiredLink(): string { http_response_code(410); return 'expired'; }
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403); echo 'This form has expired. <a href="/admin">Please try again</a>.'; return;
        } elseif ($path === '/admin/logout') {
            $_SESSION = []; session_destroy();
            setcookie(session_name(), '', ['expires' => 1, 'path' => '/admin', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
            adminRedirect('/admin/login');
        } elseif ($path === '/admin/login') {
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            try {
                if ($user = $signIn->withPassword($_SERVER['REMOTE_ADDR'] ?? 'unknown', $postedEmail, $password, $now)) { AdminAuth::start($now, $user); adminRedirect('/admin'); }
                $error = "That email and password don't match.";
            } catch (TooManyAttempts) { $error = 'Too many attempts. Please wait 15 minutes and try again.'; }
            $authView = 'login';
        } elseif ($path === '/admin/login/link') {
            $signIn->requestLink($postedEmail, $now);
            [$authView, $sentTo] = ['link-sent', $postedEmail];
        } elseif ($path === '/admin/forgot') {
            $signIn->requestReset($postedEmail, $now);
            [$authView, $sentTo] = ['reset-sent', $postedEmail];
        } elseif ($path === '/admin/reset') {
            $resetToken = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
            try {
                $user = $signIn->resetPassword($resetToken, $password, $confirm, $now);
                if (!$user) $authView = adminExpiredLink();
                else {
                    AdminAuth::start($now, $user);
                    $_SESSION['notice'] = "Password saved. You're signed in, and any other sessions have been signed out.";
                    adminRedirect('/admin');
                }
            } catch (UserError $invalid) { http_response_code(422); [$authView, $error] = ['reset', $invalid->errors['password']]; }
        } else { http_response_code(405); exit; }
    } elseif ($path === '/admin/login') {
        if ($authenticated) adminRedirect('/admin');
        $authView = 'login';
    } elseif ($path === '/admin/login/link') {
        $user = $signIn->withLink(is_string($_GET['token'] ?? null) ? $_GET['token'] : '', $now);
        if ($user) { AdminAuth::start($now, $user); adminRedirect('/admin'); }
        $authView = adminExpiredLink();
    } elseif ($path === '/admin/forgot') {
        $authView = 'forgot';
    } elseif ($path === '/admin/reset') {
        $resetToken = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        $authView = $signIn->resetUser($resetToken, $now) ? 'reset' : adminExpiredLink();
    }
    if (!$authView) {
        $notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
        $detail = preg_match('#^/admin/bookings/([1-9][0-9]{0,17})$#D', $path, $match);
        if (!$detail && !in_array($path, ['/admin', '/admin/', '/admin/bookings.csv'], true)) { http_response_code(404); echo 'Page not found.'; return; }
        $store = new BookingStore($dbPath);
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
        if ($error) http_response_code(400);
        elseif ($path === '/admin/bookings.csv') { adminBookingsCsv($store->adminBookingsExport($filters), $destinations, $now); return; }
        elseif ($detail) {
            $booking = $store->adminBooking((int)$match[1]);
            if (!$booking) { http_response_code(404); echo 'Booking not found. <a href="/admin">Back to bookings</a>'; return; }
        } else {
            $rows = $store->adminBookings($page, $filters);
            $bookingTotals = $store->bookingTotals($filters, (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d'));
        }
    }
} catch (Throwable $exception) { http_response_code(503); echo 'Administration temporarily unavailable.'; return; }
// Values a spreadsheet would read as a formula are prefixed so they stay text. Normalised phone numbers are left alone.
function csvCell(string $value): string { return preg_match('/^[=+\-@\t\r]/', $value) && !preg_match('/^\+[0-9]+$/D', $value) ? "'".$value : $value; }
function adminBookingsCsv(array $rows, array $destinations, int $now): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings-'.(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d').'.csv"');
    $nairobi = fn(?string $value) => $value ? (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i') : '';
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Booked at (Nairobi)', 'Destination', 'Visit date', 'Time', 'Visitor', 'Organization', 'Phone', 'Email', 'Guests', 'Overnight stay', 'Transport', 'Feedback attendance', 'Rating', 'What they enjoyed', 'Suggested improvements', 'Comments', 'Feedback submitted (Nairobi)'], ',', '"', '');
    foreach ($rows as $row) fputcsv($out, array_map('csvCell', [
        $row['reference'], $nairobi($row['created_at']), adminPlace($destinations, $row['destination']), $row['visit_date'], date('H:i', strtotime($row['booked_time'])),
        $row['full_name'], $row['organization'] ?? '', $row['phone'], $row['email'] ?? '', (string)$row['attendees'],
        $row['overnight'] ? 'Requested' : '', !empty($row['transport_requested']) ? 'Requested' : '',
        match ($row['attendance']) { 'attended' => 'Attended', 'not_attended' => 'Did not attend', default => '' },
        (string)($row['rating'] ?? ''), $row['enjoyment'] ?? '', $row['improvement'] ?? '', $row['comments'] ?? '', $nairobi($row['submitted_at']),
    ]), ',', '"', '');
    fclose($out);
}
function adminCount(int $count, string $one, string $many): string { return '<strong>'.$count.'</strong> '.($count === 1 ? $one : $many); }
function adminTimestamp(string $value): string { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y, g:i A'); }
require __DIR__.'/views/admin.php';
