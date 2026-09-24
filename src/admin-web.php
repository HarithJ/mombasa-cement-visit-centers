<?php
declare(strict_types=1);
require_once __DIR__.'/AdminAuth.php';
require_once __DIR__.'/BookingStore.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$trusted = array_filter(array_map('trim', explode(',', getenv('ADMIN_TRUSTED_PROXIES') ?: '')));
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)) $secure = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (!AdminAuth::configured() || (!$secure && PHP_SAPI !== 'cli-server')) { http_response_code(503); echo 'Administration unavailable.'; return; }
ini_set('session.use_strict_mode', '1');
if ($sessionPath = getenv('BOOKING_SESSION_PATH')) session_save_path($sessionPath);
session_name('nyumba_admin');
session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/admin']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$now = $adminNow ?? time();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$error = ''; $rows = []; $page = 1; $filters = []; $booking = null;
$destinations = ['sahajanand' => 'Sahajanand Special School', 'galana' => 'Galana Farm', 'feeding' => 'Kibarani Feeding Center'];
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
function adminListUrl(array $filters, int $page): string { return '/admin?'.http_build_query(array_filter($filters, fn($value) => $value !== '') + ['page' => $page]); }

function ae(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function adminRedirect(string $target): never { header('Location: '.$target, true, 303); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$authenticated = AdminAuth::authenticated($now);
if ($path !== '/admin/login' && !$authenticated) adminRedirect('/admin/login');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403); echo 'This form has expired. <a href="/admin">Please try again</a>.'; return;
        } elseif ($path === '/admin/logout') {
            $_SESSION = []; session_destroy();
            setcookie(session_name(), '', ['expires' => 1, 'path' => '/admin', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
            adminRedirect('/admin/login');
        } elseif ($path === '/admin/login') {
            $store = new BookingStore(getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite');
            $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if ($store->adminLogin($_SERVER['REMOTE_ADDR'] ?? 'unknown', $username, $password, $now)) { AdminAuth::start($now); adminRedirect('/admin'); }
            $error = 'Unable to sign in. Check your credentials or try again later.';
        } else { http_response_code(405); exit; }
    }
    if ($path === '/admin/login' && $authenticated) adminRedirect('/admin');
    if ($path !== '/admin/login') {
        $detail = preg_match('#^/admin/bookings/([1-9][0-9]{0,17})$#D', $path, $match);
        if (!$detail && $path !== '/admin' && $path !== '/admin/') { http_response_code(404); echo 'Page not found.'; return; }
        $store = new BookingStore(getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite');
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
        if ($error) http_response_code(400);
        elseif ($detail) {
            $booking = $store->adminBooking((int)$match[1]);
            if (!$booking) { http_response_code(404); echo 'Booking not found. <a href="/admin">Back to bookings</a>'; return; }
        } else $rows = $store->adminBookings($page, $filters);
    }
} catch (Throwable $exception) { http_response_code(503); echo 'Administration temporarily unavailable.'; return; }
function adminTimestamp(string $value): string { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y, g:i A'); }
require __DIR__.'/views/admin.php';
