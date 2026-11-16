<?php
declare(strict_types=1);
// The Users page (admins only), included by admin-web.php once the visitor is signed in.
// Routes: /admin/users, /admin/users/new, /admin/users/{id}, /admin/users/{id}/deactivate, /admin/users/{id}/reactivate.
if (($currentUser['role'] ?? '') !== 'admin') { http_response_code(403); echo 'Only admins can manage users. <a href="/admin">Back to bookings</a>'; return; }
if (!preg_match('#^/admin/users(?:/(new|[1-9][0-9]{0,17})(?:/(deactivate|reactivate))?)?$#D', $path, $route) || (($route[1] ?? '') === 'new' && isset($route[2]))) { http_response_code(404); echo 'Page not found.'; return; }
$userAction = $route[2] ?? '';
if ($userAction === 'reactivate' && $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']))) {
    http_response_code(403); echo 'This form has expired. <a href="/admin/users">Please try again</a>.'; return;
}
$userErrors = []; $userForm = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'team']; $editing = null; $userView = 'list';
$saved = function (string $message): never { $_SESSION['notice'] = $message; adminRedirect('/admin/users'); };
try {
    $users = UserStore::open($dbPath);
    if (($route[1] ?? '') === 'new') {
        $userView = 'form';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($userForm as $key => $value) $userForm[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
            try {
                $added = $signIn->addUser($userForm, $currentUser['name'], $now);
                $saved($added['name']." added. We've sent them a welcome email.");
            } catch (UserError $invalid) { $userErrors = $invalid->errors; }
        }
    } elseif (isset($route[1])) {
        $editing = $users->find((int)$route[1]);
        if (!$editing) { http_response_code(404); echo 'User not found. <a href="/admin/users">Back to users</a>'; return; }
        $userView = $userAction === 'deactivate' && $_SERVER['REQUEST_METHOD'] === 'GET' ? 'deactivate' : 'form';
        $userForm = array_intersect_key($editing, $userForm);
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userAction === 'deactivate') {
                $saved($users->deactivate((int)$editing['id'], (int)$currentUser['id'])['name'].' deactivated.');
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $userAction === 'reactivate') {
                $saved($users->reactivate((int)$editing['id'])['name'].' reactivated.');
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                foreach ($userForm as $key => $value) $userForm[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
                $updated = $users->edit((int)$editing['id'], $userForm);
                // An admin who made themselves team can no longer see this page.
                if ((int)$updated['id'] === (int)$currentUser['id'] && $updated['role'] !== 'admin') { $_SESSION['notice'] = 'Changes saved.'; adminRedirect('/admin'); }
                $saved('Changes saved.');
            }
        } catch (UserError $invalid) { $userErrors = $invalid->errors; }
    } else {
        $allUsers = $users->list();
    }
} catch (Throwable $exception) { http_response_code(503); echo 'Administration temporarily unavailable.'; return; }
if ($userErrors) http_response_code(422);
$notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
require __DIR__.'/views/admin-users.php';
