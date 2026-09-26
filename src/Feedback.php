<?php
declare(strict_types=1);
require_once __DIR__.'/FeedbackEmail.php';
final class Feedback {
    public static function enabled(): bool { return getenv('FEEDBACK_EMAIL_ENABLED') === '1'; }
    public static function schedule(PDO $db, array $booking, string $version, string $basePath): void {
        if (!self::enabled() || !filter_var($booking['email'] ?? '', FILTER_VALIDATE_EMAIL)) return;
        $site = rtrim(getenv('BOOKING_SITE_URL') ?: '', '/');
        $url = parse_url($site);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])) throw new RuntimeException('Configure a canonical HTTPS BOOKING_SITE_URL');
        $hour = getenv('FEEDBACK_SEND_HOUR');
        $hour = $hour === false ? '9' : $hour;
        if (!preg_match('/^(?:[0-9]|1[0-9]|2[0-3])$/D', $hour)) throw new RuntimeException('FEEDBACK_SEND_HOUR must be 0 to 23');
        $due = (new DateTimeImmutable($booking['overnight'] ? $booking['departure_date'] : $booking['visit_date'], new DateTimeZone('Africa/Nairobi')))->modify('+1 day')->setTime((int)$hour, 0)->getTimestamp();
        $token = bin2hex(random_bytes(32));
        $link = $site.rtrim($basePath, '/').'/feedback?token='.$token;
        $payload = FeedbackEmail::payload($booking, $link);
        $db->prepare('INSERT INTO feedback_invitations(booking_id, token_hash, version) VALUES (?, ?, ?)')->execute([$booking['id'], hash('sha256', $token), $version]);
        $db->prepare("INSERT INTO booking_emails(booking_id, kind, payload, idempotency_key, due_at) VALUES (?, 'feedback', ?, ?, ?)")->execute([$booking['id'], json_encode($payload, JSON_THROW_ON_ERROR), 'feedback/'.$booking['reference'], $due]);
    }
    public function __construct(private PDO $db) {}
    public function find(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $query = $this->db->prepare('SELECT i.booking_id, i.version, b.destination, b.visit_date, b.overnight, b.arrival_date, b.departure_date, r.submitted_at FROM feedback_invitations i JOIN bookings b ON b.id = i.booking_id LEFT JOIN feedback_responses r ON r.booking_id = i.booking_id WHERE i.token_hash = ?');
        $query->execute([hash('sha256', $token)]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function submit(string $token, array $input): array {
        $invitation = $this->find($token);
        if (!$invitation) throw new RuntimeException('Feedback unavailable');
        if ($invitation['submitted_at']) return [];
        $errors = [];
        $attendance = is_string($input['attendance'] ?? null) ? $input['attendance'] : '';
        if (!in_array($attendance, ['attended','not_attended'], true)) $errors['attendance'] = 'Please tell us whether you attended.';
        $rating = null;
        if ($attendance === 'attended') {
            $value = $input['rating'] ?? '';
            if (!is_string($value) || !preg_match('/^[1-5]$/D', $value)) $errors['rating'] = 'Choose a rating from 1 to 5.';
            else $rating = (int)$value;
        }
        $comments = [];
        foreach (['enjoyment','improvement','comments'] as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || mb_strlen($value) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) $errors[$field] = 'Use plain text, up to 2,000 characters.';
            $comments[] = is_string($value) ? trim($value) : '';
        }
        if ($errors) return $errors;
        $this->db->prepare('INSERT INTO feedback_responses(booking_id, attendance, rating, enjoyment, improvement, comments, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT(booking_id) DO NOTHING')->execute([$invitation['booking_id'], $attendance, $rating, ...$comments, gmdate('Y-m-d\TH:i:s\Z')]);
        return [];
    }
}
