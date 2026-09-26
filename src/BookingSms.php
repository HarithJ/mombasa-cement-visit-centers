<?php
declare(strict_types=1);
require_once __DIR__.'/PhoneNumber.php';
require_once __DIR__.'/ManagerNotifications.php';
final class BookingSms {
    public static function enabled(): bool { return getenv('BOOKING_SMS_ENABLED') === '1'; }

    public static function scenario(array $booking): string {
        return (!empty($booking['overnight']) ? 'overnight' : 'day').(!empty($booking['transport_requested']) ? '_transport' : '');
    }
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
            '{guests}' => self::peopleLabel((int)($booking['overnight_guests'] ?? 0), 'guest', 'guests'),
            '{arrival}' => $booking['arrival_date'] ? self::formatDate($booking['arrival_date']) : '',
            '{departure}' => $booking['departure_date'] ? self::formatDate($booking['departure_date']) : '',
            '{phone}' => $booking['phone'], '{reference}' => $booking['reference'],
        ];
    }
    public static function visitorMessage(array $booking): string {
        $templates = [
            'day' => "Hi {name}, thanks for booking a visit to {destination}! We look forward to welcoming you on {date} at {time}. Your booking reference is {reference}.",
            'day_transport' => "Hi {name}, thanks for booking a visit to {destination} on {date} at {time}! We've received your transport request, and our team will contact you to discuss arrangements and confirm availability. Your booking reference is {reference}.",
            'overnight' => "Hi {name}, thanks for booking a visit to Galana Farm on {date} at {time}! We've received your request to stay from {arrival} to {departure}. Our team will contact you to confirm accommodation availability. Your booking reference is {reference}.",
            'overnight_transport' => "Hi {name}, thanks for booking a visit to Galana Farm on {date} at {time}! We've received your requests for transport and a stay from {arrival} to {departure}. Our team will contact you to discuss arrangements and confirm availability. Your booking reference is {reference}.",
        ];
        return strtr($templates[self::scenario($booking)], self::values($booking));
    }
    public static function managerMessage(array $booking): string {
        $templates = [
            'day' => "Hi, {name} has booked a visit to {destination} on {date} at {time} for a total of {people}. No company transport was requested. You can reach them on {phone}. Booking reference: {reference}.",
            'day_transport' => "Hi, {name} has booked a visit to {destination} on {date} at {time} for a total of {people} and has requested company transport. Please contact them on {phone} to discuss arrangements and confirm availability. Booking reference: {reference}.",
            'overnight' => "Hi, {name} has booked a visit to Galana Farm on {date} at {time} for a total of {people}, with {guests} staying overnight from {arrival} to {departure}, subject to availability. No company transport was requested. Please contact them on {phone} to confirm accommodation arrangements. Booking reference: {reference}.",
            'overnight_transport' => "Hi, {name} has booked a visit to Galana Farm on {date} at {time} for a total of {people}. They've requested company transport and accommodation for {guests} from {arrival} to {departure}. Please contact them on {phone} to coordinate transport and confirm accommodation availability. Booking reference: {reference}.",
        ];
        return strtr($templates[self::scenario($booking)], self::values($booking));
    }
    public static function messagesFor(array $booking, ?array $contacts = null): array {
        $contacts ??= require __DIR__.'/../config/contacts.php';
        $messages = [['audience' => 'visitor', 'recipient' => PhoneNumber::normalize($booking['phone']) ?? $booking['phone'], 'message' => self::visitorMessage($booking)]];
        $recipients = [];
        foreach ($contacts[$booking['destination']]['people'] as $person) {
            $number = PhoneNumber::normalize($person['tel'] ?? $person['phone']) ?? ($person['tel'] ?? $person['phone']);
            $recipients[$number] = true;
        }
        if (ManagerNotifications::overridden()) $recipients = [ManagerNotifications::phone() => true];
        $message = self::managerMessage($booking);
        foreach (array_keys($recipients) as $number) $messages[] = ['audience' => 'manager', 'recipient' => (string)$number, 'message' => $message];
        return $messages;
    }
    public static function enqueue(PDO $db, array $booking): void {
        if (!self::enabled()) return;
        $query = $db->prepare('INSERT INTO booking_sms(booking_id,audience,recipient,message,status,last_error) VALUES (?,?,?,?,?,?)');
        foreach (self::messagesFor($booking) as $job) {
            $routable = PhoneNumber::smsRoutable($job['recipient']);
            $query->execute([$booking['id'], $job['audience'], $job['recipient'], $job['message'], $routable ? 'pending' : 'review', $routable ? null : 'unroutable_recipient']);
        }
    }
}
