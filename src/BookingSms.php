<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
require_once __DIR__.'/UserStore.php';
require_once __DIR__.'/VisitScenario.php';
final class BookingSms {
    public static function enabled(): bool { return getenv('BOOKING_SMS_ENABLED') === '1'; }

    private static function formatDate(string $date): string {
        return (new DateTimeImmutable($date, new DateTimeZone('Africa/Nairobi')))->format('j M Y');
    }
    private static function peopleLabel(int $count, string $singular, string $plural): string {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
    private static function values(array $booking): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        return [
            '{name}' => $booking['full_name'], '{destination}' => $contacts[$booking['destination']]['name'],
            '{date}' => self::formatDate($booking['visit_date']),
            '{time}' => (new DateTimeImmutable($booking['booked_time'], new DateTimeZone('Africa/Nairobi')))->format('g:i a').' EAT',
            '{people}' => self::peopleLabel((int)$booking['attendees'], 'person', 'people'),
            '{phone}' => $booking['phone'], '{reference}' => $booking['reference'],
        ];
    }
    public static function visitorMessage(array $booking): string {
        $requests = ['day' => '', 'day_transport' => ', with transport', 'overnight' => ' and stay overnight', 'overnight_transport' => ', with transport and an overnight stay'];
        return strtr("Hi {name}, thanks for your request to visit {destination} on {date} at {time}{requests}. It isn't confirmed yet; we'll let you know shortly. Ref {reference}.", self::values($booking) + ['{requests}' => $requests[VisitScenario::key($booking)]]);
    }
    public static function adminMessage(array $booking): string {
        $requests = (!empty($booking['transport_requested']) ? ', transport' : '').(!empty($booking['overnight']) ? ', overnight' : '');
        return strtr('New visit request: {name}, {destination}, {date} at {time}, {people}{requests}. Awaiting confirmation; see your email to accept or reject. Ref {reference}.', self::values($booking) + ['{requests}' => $requests]);
    }
    // The visitor's SMS, plus one to each of $admins (user rows) on the phone number on their account.
    public static function messagesFor(array $booking, array $admins): array {
        $messages = [['audience' => 'visitor', 'recipient' => PhoneNumber::normalize($booking['phone']) ?? $booking['phone'], 'message' => self::visitorMessage($booking)]];
        $message = self::adminMessage($booking);
        foreach (array_unique(array_column($admins, 'phone')) as $number) $messages[] = ['audience' => 'manager', 'recipient' => (string)$number, 'message' => $message];
        return $messages;
    }
    // The visitor's SMS after a decision. A rejection gives the decider's contact details, never the reason.
    // $changed: this reverses an earlier decision.
    public static function decisionMessage(array $booking, array $decider, string $decision, bool $changed): string {
        if ($decision === 'confirmed') {
            $requests = ['day' => '', 'day_transport' => ', including transport', 'overnight' => ', including your overnight stay', 'overnight_transport' => ', including transport and your overnight stay'];
            return strtr('Hi {name}, your visit to {destination} on {date} at {time} is confirmed{requests}. We look forward to welcoming you! Ref {reference}.', self::values($booking) + ['{requests}' => $requests[VisitScenario::key($booking)]]);
        }
        return strtr('Hi {name}, sorry, we {cannot} your visit to {destination} on {date}. Please contact {decider} on {deciderPhone} or {deciderEmail}. Ref {reference}.', self::values($booking) + ['{cannot}' => $changed ? 'can no longer host' : "can't confirm", '{decider}' => $decider['name'], '{deciderPhone}' => $decider['phone'], '{deciderEmail}' => $decider['email']]);
    }
    public static function enqueueDecision(PDO $db, array $booking, array $decider, string $decision, bool $changed): void {
        if (!self::enabled()) return;
        $recipient = PhoneNumber::normalize($booking['phone']) ?? $booking['phone'];
        $routable = PhoneNumber::smsRoutable($recipient);
        $db->prepare("INSERT INTO booking_sms(booking_id,audience,recipient,message,status,last_error) VALUES (?,'decision',?,?,?,?)")
            ->execute([$booking['id'], $recipient, self::decisionMessage($booking, $decider, $decision, $changed), $routable ? 'pending' : 'review', $routable ? null : 'unroutable_recipient']);
    }
    public static function enqueue(PDO $db, array $booking): void {
        if (!self::enabled()) return;
        $query = $db->prepare('INSERT INTO booking_sms(booking_id,audience,recipient,message,status,last_error) VALUES (?,?,?,?,?,?)');
        foreach (self::messagesFor($booking, (new UserStore($db))->activeAdmins()) as $job) {
            $routable = PhoneNumber::smsRoutable($job['recipient']);
            $query->execute([$booking['id'], $job['audience'], $job['recipient'], $job['message'], $routable ? 'pending' : 'review', $routable ? null : 'unroutable_recipient']);
        }
    }
}
