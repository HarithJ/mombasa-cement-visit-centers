<?php
declare(strict_types=1);
final class BookingEmail {
    public static function configured(): bool {
        return getenv('BOOKING_EMAIL_ENABLED') === '1';
    }
    public static function payload(array $booking): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        $team = $contacts[$booking['destination']];
        $lines = [
            'A visit has been registered.', '',
            'Booking reference: '.$booking['reference'],
            'Destination: '.$team['name'],
            'Visitor: '.$booking['full_name'],
            'Phone: '.$booking['phone'],
            'Visit date: '.$booking['visit_date'],
            'Time: '.$booking['booked_time'].' (Africa/Nairobi)',
            'Attendees: '.$booking['attendees'],
        ];
        if ($booking['overnight']) {
            $lines[] = 'Overnight stay requested: '.$booking['arrival_date'].' to '.$booking['departure_date'];
            $lines[] = 'Staying guests: '.$booking['overnight_guests'];
        }
        $lines[] = '';
        $lines[] = 'Registration does not guarantee capacity or accommodation allocation.';
        $text = implode("\n", $lines);
        return [
            'to' => array_column($team['people'], 'email'),
            'subject' => 'New visit booking — '.$booking['reference'],
            'text' => $text,
            'html' => '<h1>New visit booking</h1><p style="white-space:pre-line">'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>',
        ];
    }
}
