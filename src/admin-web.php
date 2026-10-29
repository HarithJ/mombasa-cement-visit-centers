<?php
declare(strict_types=1);
require_once __DIR__.'/AdminAuth.php';
require_once __DIR__.'/BookingStore.php';
require_once __DIR__.'/InterestRegistration.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$adminScriptPolicy = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/admin/login' ? " script-src 'self';" : '';
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none';$adminScriptPolicy");
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
// The Interest page filters registration dates over interest-only places; Bookings filters visit dates over all places.
$interestPage = in_array($path, ['/admin/interest', '/admin/interest.csv'], true);
$filterPlaces = $interestPage ? array_intersect_key($destinations, array_flip(InterestRegistration::PLACES)) : $destinations;
foreach (['q', 'destination', 'from', 'to'] as $key) {
    $filters[$key] = is_string($_GET[$key] ?? '') ? trim($_GET[$key] ?? '') : '';
    if (isset($_GET[$key]) && !is_string($_GET[$key])) $error = 'Please check the filters.';
}
if (strlen($filters['q']) > 200 || ($filters['destination'] !== '' && !isset($filterPlaces[$filters['destination']]))) $error = 'Please check the filters.';
foreach (['from', 'to'] as $key) {
    $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $filters[$key]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]) : false;
    if ($filters[$key] !== '' && (!$date || $date->format('Y-m-d') !== $filters[$key])) $error = $interestPage ? 'Enter valid registration dates.' : 'Enter valid visit dates.';
}
if ($filters['from'] && $filters['to'] && $filters['from'] > $filters['to']) $error = 'The end date must be on or after the start date.';
function adminListUrl(array $filters, int $page, string $base = '/admin'): string { return $base.'?'.http_build_query(array_filter($filters, fn($value) => $value !== '') + ['page' => $page]); }

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
        if (!$detail && !$interestPage && $path !== '/admin' && $path !== '/admin/') { http_response_code(404); echo 'Page not found.'; return; }
        $store = new BookingStore(getenv('BOOKING_DB') ?: __DIR__.'/../var/bookings.sqlite');
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
        if ($error) http_response_code(400);
        elseif ($path === '/admin/interest.csv') { adminInterestCsv($store->adminInterest($filters), $destinations, $now); return; }
        elseif ($interestPage) {
            $rows = $store->adminInterest($filters, $page);
            $place = $filters['destination'] !== '' ? $filters['destination'] : null;
            $interestTotals = $store->interestTotals($place);
            // The last twelve Nairobi months, newest first.
            $month = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'))->modify('first day of this month midnight');
            $interestMonths = [];
            for ($i = 0; $i < 12; $i++) $interestMonths[] = $month->modify("-$i months");
            $interestByMonth = $store->interestByMonth($place, end($interestMonths)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'));
        } elseif ($detail) {
            $booking = $store->adminBooking((int)$match[1]);
            if (!$booking) { http_response_code(404); echo 'Booking not found. <a href="/admin">Back to bookings</a>'; return; }
        } else $rows = $store->adminBookings($page, $filters);
    }
} catch (Throwable $exception) { http_response_code(503); echo 'Administration temporarily unavailable.'; return; }
// Values a spreadsheet would read as a formula are prefixed so they stay text. Normalised phone numbers are left alone.
function csvCell(string $value): string { return preg_match('/^[=+\-@\t\r]/', $value) && !preg_match('/^\+[0-9]+$/D', $value) ? "'".$value : $value; }
function adminInterestCsv(array $rows, array $destinations, int $now): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="interest-registrations-'.(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Registered at (Nairobi)', 'Place', 'Name', 'Organization', 'Phone', 'Email', 'Preferred visit date', 'Attendees'], ',', '"', '');
    foreach ($rows as $row) fputcsv($out, array_map('csvCell', [
        (new DateTimeImmutable($row['created_at']))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i'),
        $destinations[$row['destination']] ?? $row['destination'], $row['full_name'], $row['organization'] ?? '', $row['phone'], $row['email'] ?? '', $row['preferred_date'], (string)$row['attendees'],
    ]), ',', '"', '');
    fclose($out);
}
function adminTimestamp(string $value): string { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y, g:i A'); }
require __DIR__.'/views/admin.php';
