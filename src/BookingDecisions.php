<?php
declare(strict_types=1);
require_once __DIR__.'/BookingStore.php';
require_once __DIR__.'/BookingEmail.php';
require_once __DIR__.'/BookingSms.php';
require_once __DIR__.'/Feedback.php';
require_once __DIR__.'/SiteUrl.php';
require_once __DIR__.'/UserStore.php';
// The one way a booking gets decided, from an email link or the admin panel.
//
// decide($bookingId, $user, $decision, $reason, $mode, $now) settles or changes a booking as $user
// (a users row; the caller has already checked they may act) at unix time $now:
//   $decision  'confirmed' or 'rejected'; a reason is required for 'rejected' and ignored otherwise.
//   $mode      'first'  — only from pending (email links; the admin panel while pending).
//              'change' — only confirmed <-> rejected (the admin panel once decided).
// A booking never goes back to pending. In one transaction it records the history row, updates the
// status, queues the visitor email (if they gave one) and SMS, the team email to every other active
// user, and schedules or cancels the feedback invitation.
// Returns ['ok' => bool, 'error' => null|'closed'|'conflict'|'invalid', 'errors' => [field => message],
//          'booking' => the booking row as it now stands, 'decision' => the new history row or null]:
//   closed    it is past 23:59 Africa/Nairobi on the visit date;
//   conflict  the booking's status doesn't allow this mode/decision (e.g. someone else decided first);
//   invalid   the reason failed validation; 'errors' has ['reason' => message].
// Throws InvalidArgumentException for an unknown booking, decision or mode.
final class BookingDecisions {
    public const REASON_MISSING = 'Please give a reason for rejecting this visit.';
    public const REASON_TOO_LONG = 'Please keep the reason to 500 characters or fewer.';

    public function __construct(private PDO $db) {}

    // Decisions are allowed until the end of the visit day (23:59 Nairobi), inclusive.
    public static function open(array $booking, int $now): bool {
        return $now < (new DateTimeImmutable($booking['visit_date'].' 00:00:00', new DateTimeZone('Africa/Nairobi')))->modify('+1 day')->getTimestamp();
    }

    public function decide(int $bookingId, array $user, string $decision, ?string $reason, string $mode, int $now): array {
        if (!in_array($decision, ['confirmed', 'rejected'], true)) throw new InvalidArgumentException('Unknown decision');
        if (!in_array($mode, ['first', 'change'], true)) throw new InvalidArgumentException('Unknown mode');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $booking = $this->booking($bookingId) ?? throw new InvalidArgumentException('Unknown booking');
            $previous = $booking['status'];
            $error = null; $errors = [];
            if (!self::open($booking, $now)) $error = 'closed';
            elseif ($mode === 'first' ? $previous !== 'pending' : ($previous === 'pending' || $previous === $decision)) $error = 'conflict';
            elseif ($decision === 'rejected') {
                $reason = trim($reason ?? '');
                if ($reason === '') $errors['reason'] = self::REASON_MISSING;
                elseif (mb_strlen($reason) > 500) $errors['reason'] = self::REASON_TOO_LONG;
                if ($errors) $error = 'invalid';
            } else $reason = null;
            if ($error) { $this->db->exec('ROLLBACK'); return ['ok' => false, 'error' => $error, 'errors' => $errors, 'booking' => $booking, 'decision' => null]; }

            $this->db->prepare('INSERT INTO booking_decisions(booking_id, user_id, decision, reason, previous_status, decided_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$bookingId, $user['id'], $decision, $reason, $previous, gmdate('Y-m-d\TH:i:s\Z', $now)]);
            $decisionId = (int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$decision, $bookingId]);
            $booking = $this->booking($bookingId);
            $changed = $previous !== 'pending';
            $this->queueMessages($booking, $user, $decision, $reason, $changed, $decisionId);
            $this->feedback($booking, $decision);
            $this->db->exec('COMMIT');
            return ['ok' => true, 'error' => null, 'errors' => [], 'booking' => $booking, 'decision' => $this->history($bookingId)[0]];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    // Every decision on the booking, newest first, with the decider's name, email and phone.
    public function history(int $bookingId): array {
        $query = $this->db->prepare('SELECT d.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone FROM booking_decisions d JOIN users u ON u.id = d.user_id WHERE d.booking_id = ? ORDER BY d.id DESC');
        $query->execute([$bookingId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private function booking(int $id): ?array {
        $query = $this->db->prepare('SELECT * FROM bookings WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function queueMessages(array $booking, array $user, string $decision, ?string $reason, bool $changed, int $decisionId): void {
        $ref = $booking['reference'];
        if (BookingEmail::configured()) {
            $email = $this->db->prepare('INSERT INTO booking_emails(booking_id, user_id, kind, payload, idempotency_key) VALUES (?, ?, ?, ?, ?)');
            if ($booking['email']) $email->execute([$booking['id'], null, 'decision_visitor', json_encode(BookingEmail::decisionVisitorPayload($booking, $user, $decision, $changed), JSON_THROW_ON_ERROR), "decision-visitor/$ref/$decisionId"]);
            $adminUrl = SiteUrl::canonical().'/admin/bookings/'.$booking['id'];
            foreach ((new UserStore($this->db))->activeUsers() as $recipient) {
                if ((int)$recipient['id'] === (int)$user['id']) continue;
                $email->execute([$booking['id'], $recipient['id'], 'decision_team', json_encode(BookingEmail::decisionTeamPayload($booking, $recipient, $user, $decision, $reason, $changed, $adminUrl), JSON_THROW_ON_ERROR), "decision-team/$ref/$decisionId/".$recipient['id']]);
            }
        }
        BookingSms::enqueueDecision($this->db, $booking, $user, $decision, $changed);
    }

    // Only confirmed visits get a feedback invitation; rejecting cancels one still waiting to send.
    private function feedback(array $booking, string $decision): void {
        if ($decision === 'rejected') {
            $this->db->prepare("UPDATE booking_emails SET status = 'suppressed', lease_token = NULL WHERE booking_id = ? AND kind = 'feedback' AND status = 'pending'")->execute([$booking['id']]);
            return;
        }
        $query = $this->db->prepare('SELECT COUNT(*) FROM feedback_invitations WHERE booking_id = ?');
        $query->execute([$booking['id']]);
        if ((int)$query->fetchColumn() > 0) $this->db->prepare("UPDATE booking_emails SET status = 'pending' WHERE booking_id = ? AND kind = 'feedback' AND status = 'suppressed'")->execute([$booking['id']]);
        else Feedback::schedule($this->db, $booking, 'v3', '/');
    }
}
