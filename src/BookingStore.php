<?php
declare(strict_types=1);
require_once __DIR__.'/BookingEmail.php';
require_once __DIR__.'/BookingSms.php';
require_once __DIR__.'/Feedback.php';
require_once __DIR__.'/InterestEmail.php';
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
            $query = $this->db->prepare('INSERT INTO bookings(reference, submission_token, full_name, phone, destination, visit_date, booked_time, attendees, status, created_at, overnight, arrival_date, departure_date, overnight_guests, email, transport_requested, organization) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(submission_token) DO NOTHING');
            $query->execute(['NY-'.strtoupper(bin2hex(random_bytes(12))), $token, $booking['fullName'], $booking['phone'], $booking['location'], $booking['visitDate'], $booking['timeSlot'], $booking['attendees'], 'automatically_confirmed', gmdate('Y-m-d\TH:i:s\Z'), $booking['overnight'] === 'on' ? 1 : 0, $booking['arrivalDate'], $booking['departureDate'], $booking['overnightGuests'], $booking['email'] === '' ? null : $booking['email'], ($booking['transportRequested'] ?? '') === 'on' ? 1 : 0, ($booking['organization'] ?? '') === '' ? null : $booking['organization']]);
            $created = $query->rowCount() === 1;
            $saved = $this->findByToken($token);
            if (!$saved) throw new RuntimeException('Booking not saved');
            if ($created && BookingEmail::configured()) {
                $this->db->prepare('INSERT INTO booking_emails(booking_id, payload, idempotency_key) VALUES (?, ?, ?)')->execute([$saved['id'], json_encode(BookingEmail::payload($saved), JSON_THROW_ON_ERROR), 'booking/'.$saved['reference']]);
                if ($saved['email']) $this->db->prepare("INSERT INTO booking_emails(booking_id, kind, payload, idempotency_key) VALUES (?, 'visitor', ?, ?)")->execute([$saved['id'], json_encode(BookingEmail::visitorPayload($saved), JSON_THROW_ON_ERROR), 'visitor/'.$saved['reference']]);
            }
            if ($created) BookingSms::enqueue($this->db, $saved);
            if ($created) Feedback::schedule($this->db, $saved, $version, $basePath);
            $this->db->commit();
            return $saved;
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }
    public function createInterest(array $interest, string $token): array {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('INSERT INTO interest_registrations(submission_token, full_name, organization, phone, email, destination, preferred_date, attendees, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(submission_token) DO NOTHING');
            $query->execute([$token, $interest['fullName'], $interest['organization'] === '' ? null : $interest['organization'], $interest['phone'], $interest['email'] === '' ? null : $interest['email'], $interest['location'], $interest['visitDate'], $interest['attendees'], gmdate('Y-m-d\TH:i:s\Z')]);
            $created = $query->rowCount() === 1;
            $saved = $this->findInterestByToken($token);
            if (!$saved) throw new RuntimeException('Interest not saved');
            if ($created && BookingEmail::configured()) $this->db->prepare("INSERT INTO booking_emails(interest_id, kind, payload, idempotency_key) VALUES (?, 'interest', ?, ?)")->execute([$saved['id'], json_encode(InterestEmail::payload($saved), JSON_THROW_ON_ERROR), 'interest/'.$saved['id'].'/'.substr($token, 0, 16)]);
            $this->db->commit();
            return $saved;
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }
    public function findInterestByToken(string $token): ?array {
        $query = $this->db->prepare('SELECT * FROM interest_registrations WHERE submission_token = ?');
        $query->execute([$token]);
        return $query->fetch() ?: null;
    }
    // Filters: destination, from/to registration dates (Nairobi) and q across name, phone and organization.
    private function interestWhere(array $filters): array {
        $where = []; $params = [];
        if (!empty($filters['destination'])) { $where[] = 'destination = ?'; $params[] = $filters['destination']; }
        $zone = new DateTimeZone('Africa/Nairobi');
        $utc = fn(string $date, string $modify) => (new DateTimeImmutable($date, $zone))->modify($modify)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        if (!empty($filters['from'])) { $where[] = 'created_at >= ?'; $params[] = $utc($filters['from'], 'midnight'); }
        if (!empty($filters['to'])) { $where[] = 'created_at < ?'; $params[] = $utc($filters['to'], 'midnight +1 day'); }
        if (($filters['q'] ?? '') !== '') {
            $where[] = "(instr(lower(full_name), lower(?)) > 0 OR instr(phone, ?) > 0 OR instr(lower(coalesce(organization, '')), lower(?)) > 0)";
            array_push($params, $filters['q'], $filters['q'], $filters['q']);
        }
        return [$where ? ' WHERE '.implode(' AND ', $where) : '', $params];
    }
    public function adminInterest(array $filters, ?int $page = null): array {
        [$where, $params] = $this->interestWhere($filters);
        $query = $this->db->prepare('SELECT id, full_name, organization, phone, email, destination, preferred_date, attendees, created_at FROM interest_registrations'.$where.' ORDER BY created_at DESC, id DESC'.($page === null ? '' : ' LIMIT 26 OFFSET '.(($page - 1) * 25)));
        $query->execute($params);
        return $query->fetchAll();
    }
    // Registrations and people per destination, plus the preferred month most registrations ask for (earliest on a tie).
    public function interestTotals(array $filters): array {
        [$where, $params] = $this->interestWhere($filters);
        $query = $this->db->prepare('SELECT destination, COUNT(*) AS registrations, SUM(attendees) AS attendees FROM interest_registrations'.$where.' GROUP BY destination');
        $query->execute($params);
        $totals = array_column($query->fetchAll(), null, 'destination');
        $query = $this->db->prepare("SELECT destination, substr(preferred_date, 1, 7) AS month FROM interest_registrations".$where." GROUP BY destination, month ORDER BY COUNT(*) DESC, month ASC");
        $query->execute($params);
        foreach ($query->fetchAll() as $row) $totals[$row['destination']]['top_month'] ??= $row['month'];
        return $totals;
    }
    public function findByToken(string $token): ?array {
        $query = $this->db->prepare('SELECT * FROM bookings WHERE submission_token = ?');
        $query->execute([$token]);
        return $query->fetch() ?: null;
    }
    private function bookingWhere(array $filters): array {
        $where = []; $params = [];
        foreach (['destination' => 'destination = ?', 'from' => 'visit_date >= ?', 'to' => 'visit_date <= ?'] as $key => $clause) {
            if (!empty($filters[$key])) { $where[] = $clause; $params[] = $filters[$key]; }
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(instr(lower(reference), lower(?)) > 0 OR instr(lower(full_name), lower(?)) > 0)';
            $params[] = $filters['q']; $params[] = $filters['q'];
        }
        return [$where ? ' WHERE '.implode(' AND ', $where) : '', $params];
    }
    public function adminBookings(int $page, array $filters = []): array {
        [$where, $params] = $this->bookingWhere($filters);
        $query = $this->db->prepare('SELECT id, reference, full_name, destination, visit_date, booked_time, attendees, EXISTS(SELECT 1 FROM feedback_responses WHERE booking_id = bookings.id) AS has_feedback, (SELECT attendance FROM feedback_responses WHERE booking_id = bookings.id) AS feedback_attendance FROM bookings'.$where.' ORDER BY visit_date DESC, id DESC LIMIT 26 OFFSET ?');
        foreach ($params as $index => $value) $query->bindValue($index + 1, $value);
        $query->bindValue(count($params) + 1, ($page - 1) * 25, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }
    // Visits and guests per destination, plus the upcoming visits (the seven days from $today, Nairobi) and their overnight and transport requests.
    public function bookingTotals(array $filters, string $today): array {
        [$where, $params] = $this->bookingWhere($filters);
        $weekEnd = (new DateTimeImmutable($today))->modify('+6 days')->format('Y-m-d');
        $query = $this->db->prepare('SELECT destination, COUNT(*) AS visits, SUM(attendees) AS guests, SUM(visit_date BETWEEN ? AND ?) AS upcoming, SUM(visit_date BETWEEN ? AND ? AND overnight) AS overnight, SUM(visit_date BETWEEN ? AND ? AND transport_requested) AS transport FROM bookings'.$where.' GROUP BY destination');
        $query->execute([$today, $weekEnd, $today, $weekEnd, $today, $weekEnd, ...$params]);
        return array_column($query->fetchAll(), null, 'destination');
    }
    // Every booking matching the filters, with its feedback, for the CSV export.
    public function adminBookingsExport(array $filters): array {
        [$where, $params] = $this->bookingWhere($filters);
        $query = $this->db->prepare('SELECT b.reference, b.full_name, b.organization, b.phone, b.email, b.destination, b.visit_date, b.booked_time, b.attendees, b.created_at, b.overnight, b.transport_requested, f.attendance, f.rating, f.enjoyment, f.improvement, f.comments, f.submitted_at FROM bookings b LEFT JOIN feedback_responses f ON f.booking_id = b.id'.$where.' ORDER BY b.visit_date DESC, b.id DESC');
        $query->execute($params);
        return $query->fetchAll();
    }
    public function adminBooking(int $id): ?array {
        $query = $this->db->prepare('SELECT b.reference, b.full_name, b.organization, b.phone, b.email, b.destination, b.visit_date, b.booked_time, b.attendees, b.status, b.created_at, b.overnight, b.arrival_date, b.departure_date, b.overnight_guests, b.transport_requested, f.attendance, f.rating, f.enjoyment, f.improvement, f.comments, f.submitted_at FROM bookings b LEFT JOIN feedback_responses f ON f.booking_id = b.id WHERE b.id = ?');
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
