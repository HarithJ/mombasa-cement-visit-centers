<?php
declare(strict_types=1);
require_once __DIR__.'/BookingSms.php';
require_once __DIR__.'/AfricasTalkingClient.php';
final class SmsWorker {
    private Closure $clock;
    public function __construct(private PDO $db, ?Closure $clock = null) {
        $this->clock = $clock ?? fn() => time();
    }
    public function run(AfricasTalkingClient $client, int $limit = 25): array {
        $counts = ['accepted' => 0, 'retry' => 0, 'review' => 0, 'paused' => 0];
        if (!BookingSms::enabled()) return $counts;
        // Configuration failures must not claim jobs or consume attempts.
        $client->validateConfiguration();
        for ($i = 0; $i < $limit; $i++) {
            $now = ($this->clock)();
            $this->db->exec('BEGIN IMMEDIATE');
            try {
                if ((int)$this->db->query('SELECT paused FROM sms_control WHERE id = 1')->fetchColumn() === 1) {
                    $this->db->exec('COMMIT');
                    $counts['paused'] = 1;
                    break;
                }
                // An expired claim may already have reached the provider. Never resend it.
                $expired = $this->db->prepare("UPDATE booking_sms SET status='review', last_error='expired_claim_acceptance_unknown', attempts=attempts+1 WHERE status='sending' AND lease_until <= ?");
                $expired->execute([$now]);
                $counts['review'] += $expired->rowCount();
                $query = $this->db->prepare("SELECT * FROM booking_sms WHERE status='pending' AND next_attempt_at <= ? ORDER BY id LIMIT 1");
                $query->execute([$now]);
                $job = $query->fetch(PDO::FETCH_ASSOC);
                $query->closeCursor();
                if (!$job) { $this->db->exec('COMMIT'); break; }
                $reason = null;
                if ($job['profile'] !== null && $job['profile'] !== $client->profile()) $reason = 'sending_profile_changed';
                elseif ((int)$job['attempts'] >= 5 || ($job['first_attempt_at'] !== null && $now - (int)$job['first_attempt_at'] >= 86400)) $reason = 'retry_limit';
                if ($reason !== null) {
                    $this->db->prepare("UPDATE booking_sms SET status='review',last_error=? WHERE id=?")->execute([$reason, $job['id']]);
                    $this->db->exec('COMMIT'); $counts['review']++; continue;
                }
                $lease = bin2hex(random_bytes(16));
                $this->db->prepare("UPDATE booking_sms SET status='sending',lease_token=?,lease_until=?,profile=?,first_attempt_at=COALESCE(first_attempt_at,?) WHERE id=?")
                    ->execute([$lease, $now + 60, $client->profile(), $now, $job['id']]);
                $this->db->exec('COMMIT');
            } catch (Throwable $error) {
                $this->db->exec('ROLLBACK'); throw $error;
            }
            // Only transport failures belong in this catch. A post-send database failure
            // leaves an uncertain in-flight job for recovery, never a retryable one.
            $failure = null;
            try { $id = $client->send($job['recipient'], $job['message']); }
            catch (SmsFailure $error) { $failure = $error->category; }
            catch (Throwable $error) { $failure = 'uncertain'; }
            if ($failure === 'configuration') {
                $this->db->exec('BEGIN IMMEDIATE');
                try {
                    $this->db->exec("UPDATE sms_control SET paused=1,reason='provider_configuration' WHERE id=1");
                    $this->db->prepare("UPDATE booking_sms SET status='pending',first_attempt_at=?,profile=?,lease_token=NULL,lease_until=NULL,last_error='provider_configuration' WHERE id=? AND lease_token=? AND status='sending'")
                        ->execute([$job['first_attempt_at'], $job['profile'], $job['id'], $lease]);
                    $this->db->exec('COMMIT');
                } catch (Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
                $counts['paused'] = 1; break;
            }
            $attempts = (int)$job['attempts'] + 1;
            $status = $failure === null ? 'accepted' : 'review';
            if ($failure === 'retryable' && $attempts < 5 && $now - (int)($job['first_attempt_at'] ?? $now) < 86400) $status = 'pending';
            $update = $this->db->prepare("UPDATE booking_sms SET status=?,attempts=?,next_attempt_at=?,provider_id=?,last_error=?,lease_token=NULL,lease_until=NULL WHERE id=? AND lease_token=? AND status='sending'");
            $update->execute([$status, $attempts, $now + min(3600, 60 * (2 ** min($attempts - 1, 6))), $failure === null ? $id : null, $failure, $job['id'], $lease]);
            $counts[$status === 'pending' ? 'retry' : $status] += $update->rowCount();
        }
        return $counts;
    }
}
