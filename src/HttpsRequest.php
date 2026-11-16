<?php
declare(strict_types=1);
// Whether this request reached us over HTTPS. Behind a proxy listed in ADMIN_TRUSTED_PROXIES, only that
// proxy's X-Forwarded-Proto counts; anyone else's header is ignored.
final class HttpsRequest {
    public static function secure(): bool {
        $trusted = array_filter(array_map('trim', explode(',', getenv('ADMIN_TRUSTED_PROXIES') ?: '')));
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)) return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
