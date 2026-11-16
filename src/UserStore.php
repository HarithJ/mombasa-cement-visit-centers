<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
require_once __DIR__.'/PhoneNumber.php';
// Validation failures, keyed by field (name, email, phone, role, active) with the wording to show.
final class UserError extends RuntimeException {
    public function __construct(public readonly array $errors) { parent::__construct(implode(' ', $errors)); }
}
// Staff accounts: who can sign in, get booking emails and decide visits.
final class UserStore {
    public function __construct(private PDO $db) {}
    public static function open(string $path): self {
        return new self(BookingStore::connect($path));
    }
    public function list(): array {
        return $this->db->query('SELECT * FROM users ORDER BY name COLLATE NOCASE, id')->fetchAll();
    }
    public function find(int $id): ?array {
        $query = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }
    public function findByEmail(string $email): ?array {
        $query = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $query->execute([trim($email)]);
        return $query->fetch() ?: null;
    }
    // A new user gets a strong random password nobody knows; only its hash is kept.
    public function add(array $input): array {
        $user = $this->validate($input);
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $this->db->prepare('INSERT INTO users(name, email, phone, role, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$user['name'], $user['email'], $user['phone'], $user['role'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $now, $now]);
        return $this->find((int)$this->db->lastInsertId());
    }
    // Changes the given fields (name, email, phone, role); the others keep their values.
    public function edit(int $id, array $input): array {
        $current = $this->existing($id);
        $user = $this->validate(array_merge(array_intersect_key($current, array_flip(['name', 'email', 'phone', 'role'])), $input), $id);
        if ($current['role'] === 'admin' && $user['role'] !== 'admin' && $this->isLastActiveAdmin($current)) throw new UserError(['role' => self::LAST_ADMIN]);
        $emailChanged = strcasecmp($user['email'], $current['email']) !== 0;
        $this->db->prepare('UPDATE users SET name = ?, email = ?, phone = ?, role = ?, credential_version = credential_version + ?, session_version = session_version + ?, updated_at = ? WHERE id = ?')
            ->execute([$user['name'], $user['email'], $user['phone'], $user['role'], $emailChanged ? 1 : 0, $emailChanged ? 1 : 0, gmdate('Y-m-d\TH:i:s\Z'), $id]);
        return $this->find($id);
    }
    // Signs the user out everywhere and stops their unused links. $actorId is the signed-in user doing it (null from the server command).
    public function deactivate(int $id, ?int $actorId = null): array {
        $current = $this->existing($id);
        if ($actorId === $id) throw new UserError(['active' => "You can't deactivate your own account."]);
        if ($this->isLastActiveAdmin($current)) throw new UserError(['active' => self::LAST_ADMIN]);
        $this->db->prepare('UPDATE users SET active = 0, credential_version = credential_version + 1, session_version = session_version + 1, updated_at = ? WHERE id = ? AND active = 1')->execute([gmdate('Y-m-d\TH:i:s\Z'), $id]);
        return $this->find($id);
    }
    public function reactivate(int $id): array {
        $this->existing($id);
        $this->db->prepare('UPDATE users SET active = 1, updated_at = ? WHERE id = ? AND active = 0')->execute([gmdate('Y-m-d\TH:i:s\Z'), $id]);
        return $this->find($id);
    }
    public function activeUsers(): array {
        return $this->db->query('SELECT * FROM users WHERE active = 1 ORDER BY name COLLATE NOCASE, id')->fetchAll();
    }
    public function activeAdmins(): array {
        return $this->db->query("SELECT * FROM users WHERE active = 1 AND role = 'admin' ORDER BY name COLLATE NOCASE, id")->fetchAll();
    }
    private const LAST_ADMIN = 'There must be at least one active admin.';
    private function isLastActiveAdmin(array $user): bool {
        if ($user['role'] !== 'admin' || !$user['active']) return false;
        $query = $this->db->prepare("SELECT COUNT(*) FROM users WHERE active = 1 AND role = 'admin' AND id <> ?");
        $query->execute([$user['id']]);
        return (int)$query->fetchColumn() === 0;
    }
    private function existing(int $id): array {
        return $this->find($id) ?? throw new InvalidArgumentException('Unknown user');
    }
    private function validate(array $input, ?int $id = null): array {
        $user = []; $errors = [];
        foreach (['name', 'email', 'phone', 'role'] as $key) $user[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        if ($user['name'] === '' || mb_strlen($user['name']) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $user['name'])) $errors['name'] = 'Please enter a name.';
        if (strlen($user['email']) > 254 || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
        elseif (($existing = $this->findByEmail($user['email'])) && (int)$existing['id'] !== $id) $errors['email'] = 'Someone already uses that email address.';
        $phone = PhoneNumber::normalize($user['phone']);
        if ($phone === null) $errors['phone'] = 'Please enter a valid phone number.'; else $user['phone'] = $phone;
        if (!in_array($user['role'], ['admin', 'team'], true)) $errors['role'] = 'Please choose Admin or Team.';
        if ($errors) throw new UserError($errors);
        return $user;
    }
}
