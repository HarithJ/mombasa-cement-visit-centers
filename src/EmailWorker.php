<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
final class EmailWorker {
    public function __construct(private PDO $db) {}
    // A short database write lock serializes claims; network calls use a lease.
    public function run(callable $send, string $from, int $limit = 25): array {
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Set RESEND_FROM to a verified sender email address');
        $counts = ['accepted' => 0, 'retry' => 0, 'review' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $this->db->exec('BEGIN IMMEDIATE');
            try {
                $query = $this->db->prepare("SELECT * FROM booking_emails WHERE status = 'pending' AND next_attempt_at <= ? ORDER BY id LIMIT 1");
                $query->execute([time()]);
                $job = $query->fetch(PDO::FETCH_ASSOC);
                if (!$job) { $this->db->exec('COMMIT'); break; }
                $now = time();
                // Resend retains idempotency keys for 24 hours. Stop before expiry
                // instead of risking a duplicate after an ambiguous network result.
                if ($job['first_attempt_at'] !== null && $now - $job['first_attempt_at'] >= 23 * 3600) {
                    $this->db->prepare("UPDATE booking_emails SET status = 'review', last_error = 'Retry window expired; check Resend before resending' WHERE id = ?")->execute([$job['id']]);
                    $counts['review']++;
                    $this->db->exec('COMMIT'); continue;
                }
                $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
                $payload['from'] ??= $from;
                // Commit the immutable payload and start time BEFORE any network call.
                $this->db->prepare('UPDATE booking_emails SET payload = ?, first_attempt_at = COALESCE(first_attempt_at, ?), next_attempt_at = ? WHERE id = ?')->execute([json_encode($payload, JSON_THROW_ON_ERROR), $now, $now + 60, $job['id']]);
                $this->db->exec('COMMIT');
                // A lease prevents another worker from picking this job during send.
                try {
                    $id = $send($payload, $job['idempotency_key']);
                    $this->db->prepare("UPDATE booking_emails SET status = 'accepted', provider_id = ?, attempts = attempts + 1, last_error = NULL WHERE id = ?")->execute([$id, $job['id']]);
                    $counts['accepted']++;
                } catch (Throwable $error) {
                    // Store a category only: no provider response, secrets or personal data.
                    $this->db->prepare('UPDATE booking_emails SET attempts = attempts + 1, next_attempt_at = ?, last_error = ? WHERE id = ?')->execute([$now + min(3600, 60 * (2 ** min((int)$job['attempts'], 6))), 'Email API request failed', $job['id']]);
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
