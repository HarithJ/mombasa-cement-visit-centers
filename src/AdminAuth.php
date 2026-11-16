<?php
declare(strict_types=1);
require_once __DIR__.'/UserStore.php';
// The signed-in staff member for this admin session.
final class AdminAuth {
    // The session's user, or null once it is idle 30 minutes, 8 hours old, or the user was
    // deactivated or had their credentials change (email change, password reset).
    public static function user(int $now, UserStore $users): ?array {
        $auth = $_SESSION['admin_auth'] ?? null;
        $user = is_array($auth) && isset($auth['user'], $auth['version'], $auth['started'], $auth['seen']) ? $users->find((int)$auth['user']) : null;
        if (!$user || !$user['active'] || (int)$user['credential_version'] !== $auth['version'] || $now - $auth['seen'] >= 1800 || $now - $auth['started'] >= 28800) {
            unset($_SESSION['admin_auth']);
            return null;
        }
        $_SESSION['admin_auth']['seen'] = $now;
        return $user;
    }
    public static function start(int $now, array $user): void {
        session_regenerate_id(true);
        $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'admin_auth' => ['user' => (int)$user['id'], 'version' => (int)$user['credential_version'], 'started' => $now, 'seen' => $now]];
    }
}
