<?php
declare(strict_types=1);
require_once __DIR__.'/SiteUrl.php';
// Single-use, expiring links tied to a user. Only token hashes are stored, and a token is
// refused once the user is inactive or their credential_version has moved on.
final class AuthTokens {
    public const LIFETIMES = ['login' => 900, 'reset' => 3600, 'welcome' => 172800];
    // Where each purpose's link lands: welcome links sign in just like login links.
    private const PATHS = ['login' => '/admin/login/link', 'reset' => '/admin/reset', 'welcome' => '/admin/login/link'];

    // Returns the raw token; it is never stored.
    public static function issue(PDO $db, array $user, string $purpose, int $now): string {
        if (!isset(self::LIFETIMES[$purpose])) throw new InvalidArgumentException('Unknown token purpose');
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO auth_tokens(user_id, purpose, token_hash, credential_version, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$user['id'], $purpose, hash('sha256', $token), $user['credential_version'], $now, $now + self::LIFETIMES[$purpose]]);
        return $token;
    }
    // The absolute link for a token, on the configured canonical HTTPS site.
    public static function link(string $purpose, string $token): string {
        return self::site().self::PATHS[$purpose].'?token='.$token;
    }
    public static function site(): string {
        return SiteUrl::canonical();
    }
    // How many links of this purpose the user was sent after $since.
    public static function issuedSince(PDO $db, int $userId, string $purpose, int $since): int {
        $query = $db->prepare('SELECT COUNT(*) FROM auth_tokens WHERE user_id = ? AND purpose = ? AND created_at > ?');
        $query->execute([$userId, $purpose, $since]);
        return (int)$query->fetchColumn();
    }
    // The user a still-valid token belongs to, without using it up.
    public static function find(PDO $db, string $token, array $purposes, int $now): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $marks = implode(', ', array_fill(0, count($purposes), '?'));
        $query = $db->prepare("SELECT u.*, t.id AS token_id FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.purpose IN ($marks) AND t.used_at IS NULL AND t.expires_at > ? AND u.active = 1 AND u.credential_version = t.credential_version");
        $query->execute([hash('sha256', $token), ...$purposes, $now]);
        $user = $query->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
    // Uses the token up and returns its user, or null if it was not (or is no longer) valid.
    public static function consume(PDO $db, string $token, array $purposes, int $now): ?array {
        $user = self::find($db, $token, $purposes, $now);
        if (!$user) return null;
        $used = $db->prepare('UPDATE auth_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $used->execute([$now, $user['token_id']]);
        if ($used->rowCount() !== 1) return null;
        unset($user['token_id']);
        return $user;
    }
}
