<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
final class EmailWorker {
    private Closure $clock;
    public function __construct(private PDO $db, ?Closure $clock = null) { $this->clock = $clock ?? fn() => time(); }
    // A short database write lock serializes claims; network calls use a lease.
    public function run(callable $send, string $from, int $limit = 25): array {
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Set RESEND_FROM to a verified sender email address');
        $counts = ['accepted' => 0, 'retry' => 0, 'review' => 0, 'suppressed' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $this->db->exec('BEGIN IMMEDIATE');
            try {
                $query = $this->db->prepare("SELECT * FROM booking_emails WHERE status = 'pending' AND next_attempt_at <= ? AND due_at <= ? AND ((kind = 'feedback' AND CAST(? AS INTEGER) = 1) OR (kind <> 'feedback' AND CAST(? AS INTEGER) = 1)) ORDER BY id LIMIT 1");
                $now = ($this->clock)();
                $query->execute([$now, $now, Feedback::enabled() ? 1 : 0, BookingEmail::configured() ? 1 : 0]);
                $job = $query->fetch(PDO::FETCH_ASSOC);
                $query->closeCursor();
                if (!$job) { $this->db->exec('COMMIT'); break; }
                $now = ($this->clock)();
                if ($job['kind'] === 'feedback' && $job['first_attempt_at'] === null) {
                    $response = $this->db->prepare('SELECT 1 FROM feedback_responses WHERE booking_id = ?');
                    $response->execute([$job['booking_id']]);
                    if ($response->fetchColumn()) {
                        $this->db->prepare("UPDATE booking_emails SET status = 'suppressed' WHERE id = ?")->execute([$job['id']]);
                        $counts['suppressed']++;
                        $this->db->exec('COMMIT'); continue;
                    }
                }
                // Resend retains idempotency keys for 24 hours. Stop before expiry
                // instead of risking a duplicate after an ambiguous network result.
                if ($job['first_attempt_at'] !== null && $now - $job['first_attempt_at'] >= 23 * 3600) {
                    $this->db->prepare("UPDATE booking_emails SET status = 'review', last_error = 'Retry window expired; check Resend before resending' WHERE id = ?")->execute([$job['id']]);
                    $counts['review']++;
                    $this->db->exec('COMMIT'); continue;
                }
                $lease = bin2hex(random_bytes(16));
                $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
                $payload['from'] ??= $from;
                // Commit the immutable payload and start time BEFORE any network call.
                $this->db->prepare('UPDATE booking_emails SET payload = ?, first_attempt_at = COALESCE(first_attempt_at, ?), next_attempt_at = ?, lease_token = ? WHERE id = ?')->execute([json_encode($payload, JSON_THROW_ON_ERROR), $now, $now + 60, $lease, $job['id']]);
                $this->db->exec('COMMIT');
                // A lease prevents another worker from picking this job during send.
                try {
                    $id = $send($payload, $job['idempotency_key']);
                    $this->db->prepare("UPDATE booking_emails SET status = 'accepted', provider_id = ?, attempts = attempts + 1, last_error = NULL WHERE id = ? AND lease_token = ? AND status = 'pending'")->execute([$id, $job['id'], $lease]);
                    $counts['accepted']++;
                } catch (Throwable $error) {
                    // Store a category only: no provider response, secrets or personal data.
                    $this->db->prepare('UPDATE booking_emails SET attempts = attempts + 1, next_attempt_at = ?, last_error = ? WHERE id = ? AND lease_token = ? AND status = \'pending\'')->execute([$now + min(3600, 60 * (2 ** min((int)$job['attempts'], 6))), 'Email API request failed', $job['id'], $lease]);
                    $counts['retry']++;
                }
            } catch (Throwable $error) {
                try { $this->db->exec('ROLLBACK'); } catch (Throwable $ignored) {}
                throw $error;
            }
        }
        return $counts;
    }
}
