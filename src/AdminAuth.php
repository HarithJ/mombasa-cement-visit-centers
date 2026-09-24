<?php
declare(strict_types=1);
final class AdminAuth {
    public static function configured(): bool {
        return (bool)getenv('ADMIN_USERNAME') && password_get_info(getenv('ADMIN_PASSWORD_HASH') ?: '')['algo'] !== null;
    }
    public static function fingerprint(): string {
        return hash('sha256', getenv('ADMIN_USERNAME').'|'.getenv('ADMIN_PASSWORD_HASH'));
    }
    public static function authenticated(int $now): bool {
        $auth = $_SESSION['admin_auth'] ?? null;
        if (!$auth || !hash_equals(self::fingerprint(), $auth['fingerprint']) || $now - $auth['seen'] >= 1800 || $now - $auth['started'] >= 28800) {
            unset($_SESSION['admin_auth']);
            return false;
        }
        $_SESSION['admin_auth']['seen'] = $now;
        return true;
    }
    public static function start(int $now): void {
        session_regenerate_id(true);
        $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'admin_auth' => ['fingerprint' => self::fingerprint(), 'started' => $now, 'seen' => $now]];
    }
}
