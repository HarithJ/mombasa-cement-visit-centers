<?php
declare(strict_types=1);
require_once __DIR__.'/BookingEmail.php';
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
    public function create(array $booking, string $token): array {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('INSERT INTO bookings(reference, submission_token, full_name, phone, destination, visit_date, booked_time, attendees, status, created_at, overnight, arrival_date, departure_date, overnight_guests) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(submission_token) DO NOTHING');
            $query->execute(['NY-'.strtoupper(bin2hex(random_bytes(12))), $token, $booking['fullName'], $booking['phone'], $booking['location'], $booking['visitDate'], $booking['timeSlot'], $booking['attendees'], 'automatically_confirmed', gmdate('Y-m-d\TH:i:s\Z'), $booking['overnight'] === 'on' ? 1 : 0, $booking['arrivalDate'], $booking['departureDate'], $booking['overnightGuests']]);
            $created = $query->rowCount() === 1;
            $saved = $this->findByToken($token);
            if (!$saved) throw new RuntimeException('Booking not saved');
            if ($created && BookingEmail::configured()) {
                $this->db->prepare('INSERT INTO booking_emails(booking_id, payload, idempotency_key) VALUES (?, ?, ?)')->execute([$saved['id'], json_encode(BookingEmail::payload($saved), JSON_THROW_ON_ERROR), 'booking/'.$saved['reference']]);
            }
            $this->db->commit();
            return $saved;
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }
    public function findByToken(string $token): ?array {
        $query = $this->db->prepare('SELECT * FROM bookings WHERE submission_token = ?');
        $query->execute([$token]);
        return $query->fetch() ?: null;
    }
}
