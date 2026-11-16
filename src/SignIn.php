<?php
declare(strict_types=1);
require_once __DIR__.'/UserStore.php';
require_once __DIR__.'/AuthTokens.php';
require_once __DIR__.'/AccountEmail.php';
final class TooManyAttempts extends RuntimeException {}
// How staff get into the admin panel: password, emailed sign-in link, or a password reset.
final class SignIn {
    public function __construct(private PDO $db) {}
    public static function open(string $path): self {
        new BookingStore($path);
        $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout = 5000');
        return new self($db);
    }
    // Five failures per direct peer address in 15 minutes block further attempts, right or wrong.
    public function withPassword(string $address, string $email, string $password, int $now): ?array {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->db->prepare('DELETE FROM admin_login_attempts WHERE started_at <= ?')->execute([$now - 900]);
            $query = $this->db->prepare('SELECT failures FROM admin_login_attempts WHERE address = ?');
            $query->execute([$address]);
            if ((int)$query->fetchColumn() >= 5) { $this->db->exec('COMMIT'); throw new TooManyAttempts(); }
            $user = (new UserStore($this->db))->findByEmail($email);
            // Check a hash even for unknown emails so timing doesn't reveal who has an account.
            $valid = password_verify($password, $user['password_hash'] ?? self::dummyHash()) && $user && $user['active'];
            if ($valid) $this->db->prepare('DELETE FROM admin_login_attempts WHERE address = ?')->execute([$address]);
            else $this->db->prepare('INSERT INTO admin_login_attempts(address, failures, started_at) VALUES (?, 1, ?) ON CONFLICT(address) DO UPDATE SET failures = failures + 1')->execute([$address, $now]);
            $this->db->exec('COMMIT');
            return $valid ? $user : null;
        } catch (TooManyAttempts $blocked) { throw $blocked;
        } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
    // Emails an active user a 15-minute sign-in link, at most 3 per 15 minutes. Says nothing either way.
    public function requestLink(string $email, int $now): void {
        $this->sendLink($email, 'login', $now);
    }
    // Signs in with a login or welcome link, once.
    public function withLink(string $token, int $now): ?array {
        return AuthTokens::consume($this->db, $token, ['login', 'welcome'], $now);
    }
    // Emails an active user a one-hour reset link, at most 3 per 15 minutes. Says nothing either way.
    public function requestReset(string $email, int $now): void {
        $this->sendLink($email, 'reset', $now);
    }
    // The user a reset link is for, while it is still valid.
    public function resetUser(string $token, int $now): ?array {
        return AuthTokens::find($this->db, $token, ['reset'], $now);
    }
    // Saves a new password with a reset link (once) and ends the user's other sessions and links.
    // Returns null when the link is no longer valid.
    public function resetPassword(string $token, string $password, string $confirm, int $now): ?array {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            if (!$this->resetUser($token, $now)) { $this->db->exec('COMMIT'); return null; }
            if (mb_strlen($password) < 12) throw new UserError(['password' => 'Use at least 12 characters.']);
            if (!hash_equals($password, $confirm)) throw new UserError(['password' => "The passwords don't match."]);
            $user = AuthTokens::consume($this->db, $token, ['reset'], $now);
            $this->db->prepare('UPDATE users SET password_hash = ?, credential_version = credential_version + 1, updated_at = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), gmdate('Y-m-d\TH:i:s\Z', $now), $user['id']]);
            $this->db->exec('COMMIT');
        } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
        return (new UserStore($this->db))->find($user['id']);
    }
    // Adds a user and emails them a 48-hour welcome sign-in link naming $adminName, all or nothing.
    public function addUser(array $input, string $adminName, int $now): array {
        AuthTokens::site();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $user = (new UserStore($this->db))->add($input);
            $token = AuthTokens::issue($this->db, $user, 'welcome', $now);
            AccountEmail::queue($this->db, $user, 'welcome', AccountEmail::welcome($user, AuthTokens::link('welcome', $token), $adminName), 'welcome/'.hash('sha256', $token));
            $this->db->exec('COMMIT');
            return $user;
        } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
    private function sendLink(string $email, string $purpose, int $now): void {
        AuthTokens::site();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $user = (new UserStore($this->db))->findByEmail($email);
            if ($user && $user['active'] && AuthTokens::issuedSince($this->db, $user['id'], $purpose, $now - 900) < 3) {
                $token = AuthTokens::issue($this->db, $user, $purpose, $now);
                $link = AuthTokens::link($purpose, $token);
                AccountEmail::queue($this->db, $user, $purpose, $purpose === 'login' ? AccountEmail::signInLink($user, $link) : AccountEmail::reset($user, $link), $purpose.'/'.hash('sha256', $token));
            }
            $this->db->exec('COMMIT');
        } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
    private static function dummyHash(): string {
        static $hash = null;
        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
