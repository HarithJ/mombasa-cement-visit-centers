<?php
declare(strict_types=1);
require_once __DIR__.'/BookingEmail.php';
require_once __DIR__.'/Feedback.php';
final class BookingStore {
    private PDO $db;
    public function __construct(string $path) {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Storage unavailable');
        $public = realpath(__DIR__.'/../public');
        $resolved = realpath($directory);
        if ($resolved === $public || str_starts_with($resolved.'/', $public.'/')) throw new RuntimeException('Database must be private');
        $this->db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        chmod($path, 0600);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY)');
        foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
            $version = basename($file);
            $query = $this->db->prepare('SELECT version FROM schema_migrations WHERE version = ?');
            $query->execute([$version]);
            if ($query->fetch()) continue;
            $this->db->beginTransaction();
            try {
                $this->db->exec(file_get_contents($file));
                $this->db->prepare('INSERT OR IGNORE INTO schema_migrations(version) VALUES (?)')->execute([$version]);
                $this->db->commit();
            } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
        }
    }
    public function create(array $booking, string $token, string $version = 'v1', string $basePath = '/v1'): array {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('INSERT INTO bookings(reference, submission_token, full_name, phone, destination, visit_date, booked_time, attendees, status, created_at, overnight, arrival_date, departure_date, overnight_guests, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(submission_token) DO NOTHING');
            $query->execute(['NY-'.strtoupper(bin2hex(random_bytes(12))), $token, $booking['fullName'], $booking['phone'], $booking['location'], $booking['visitDate'], $booking['timeSlot'], $booking['attendees'], 'automatically_confirmed', gmdate('Y-m-d\TH:i:s\Z'), $booking['overnight'] === 'on' ? 1 : 0, $booking['arrivalDate'], $booking['departureDate'], $booking['overnightGuests'], $booking['email'] === '' ? null : $booking['email']]);
            $created = $query->rowCount() === 1;
            $saved = $this->findByToken($token);
            if (!$saved) throw new RuntimeException('Booking not saved');
            if ($created && BookingEmail::configured()) {
                $this->db->prepare('INSERT INTO booking_emails(booking_id, payload, idempotency_key) VALUES (?, ?, ?)')->execute([$saved['id'], json_encode(BookingEmail::payload($saved), JSON_THROW_ON_ERROR), 'booking/'.$saved['reference']]);
                if ($saved['email']) $this->db->prepare("INSERT INTO booking_emails(booking_id, kind, payload, idempotency_key) VALUES (?, 'visitor', ?, ?)")->execute([$saved['id'], json_encode(BookingEmail::visitorPayload($saved), JSON_THROW_ON_ERROR), 'visitor/'.$saved['reference']]);
            }
            if ($created) Feedback::schedule($this->db, $saved, $version, $basePath);
            $this->db->commit();
            return $saved;
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }
    public function findByToken(string $token): ?array {
        $query = $this->db->prepare('SELECT * FROM bookings WHERE submission_token = ?');
        $query->execute([$token]);
        return $query->fetch() ?: null;
    }
    public function adminBookings(int $page, array $filters = []): array {
        $where = []; $params = [];
        foreach (['destination' => 'destination = ?', 'from' => 'visit_date >= ?', 'to' => 'visit_date <= ?'] as $key => $clause) {
            if (!empty($filters[$key])) { $where[] = $clause; $params[] = $filters[$key]; }
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(instr(lower(reference), lower(?)) > 0 OR instr(lower(full_name), lower(?)) > 0)';
            $params[] = $filters['q']; $params[] = $filters['q'];
        }
        $query = $this->db->prepare('SELECT id, reference, full_name, destination, visit_date, booked_time, attendees, EXISTS(SELECT 1 FROM feedback_responses WHERE booking_id = bookings.id) AS has_feedback FROM bookings'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY visit_date DESC, id DESC LIMIT 26 OFFSET ?');
        foreach ($params as $index => $value) $query->bindValue($index + 1, $value);
        $query->bindValue(count($params) + 1, ($page - 1) * 25, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }
    public function adminBooking(int $id): ?array {
        $query = $this->db->prepare('SELECT b.reference, b.full_name, b.phone, b.email, b.destination, b.visit_date, b.booked_time, b.attendees, b.status, b.created_at, b.overnight, b.arrival_date, b.departure_date, b.overnight_guests, f.attendance, f.rating, f.enjoyment, f.improvement, f.comments, f.submitted_at FROM bookings b LEFT JOIN feedback_responses f ON f.booking_id = b.id WHERE b.id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }
    public function adminLogin(string $address, string $username, string $password, int $now): bool {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->db->prepare('DELETE FROM admin_login_attempts WHERE started_at <= ?')->execute([$now - 900]);
            $query = $this->db->prepare('SELECT failures FROM admin_login_attempts WHERE address = ?');
            $query->execute([$address]);
            $failures = (int)$query->fetchColumn();
            $valid = $failures < 5 && password_verify($password, getenv('ADMIN_PASSWORD_HASH')) && hash_equals(getenv('ADMIN_USERNAME'), $username);
            if ($valid) $this->db->prepare('DELETE FROM admin_login_attempts WHERE address = ?')->execute([$address]);
            elseif ($failures < 5) $this->db->prepare('INSERT INTO admin_login_attempts(address, failures, started_at) VALUES (?, 1, ?) ON CONFLICT(address) DO UPDATE SET failures = failures + 1')->execute([$address, $now]);
            $this->db->exec('COMMIT');
            return $valid;
        } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
}
