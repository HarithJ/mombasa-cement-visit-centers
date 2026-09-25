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
            'Email: '.($booking['email'] ?: 'Not provided'),
            'Visit date: '.$booking['visit_date'],
            'Time: '.$booking['booked_time'].' (Africa/Nairobi)',
            'Attendees: '.$booking['attendees'],
            'Transport: '.(!empty($booking['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested'),
        ];
        if ($booking['overnight']) {
            $lines[] = 'Overnight stay requested: '.$booking['arrival_date'].' to '.$booking['departure_date'];
            $lines[] = 'Staying guests: '.$booking['overnight_guests'];
        }
        $lines[] = '';
        $lines[] = 'Registration does not guarantee capacity or accommodation allocation.';
        $text = implode("\n", $lines);
        return [
            'to' => array_values(array_unique([...array_column($team['people'], 'email'), 'harithjaved@gmail.com'])),
            'subject' => 'New visit booking — '.$booking['reference'],
            'text' => $text,
            'html' => '<h1>New visit booking</h1><p style="white-space:pre-line">'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>',
        ];
    }
    public static function visitorPayload(array $booking): array {
        $payload = self::payload($booking);
        $contacts = require __DIR__.'/../config/contacts.php';
        $team = $contacts[$booking['destination']];
        $payload['to'] = [$booking['email']];
        $payload['subject'] = 'Your visit is registered — '.$booking['reference'];
        $payload['text'] = str_replace('A visit has been registered.', 'Thank you for booking your visit with Nyumba Group.', $payload['text']);
        $payload['text'] .= "\n\nQuestions about your visit? Contact ".$team['name'].":\n";
        foreach ($team['people'] as $person) {
            $payload['text'] .= ($person['name'] !== '' ? $person['name'].' — ' : '').$person['phone'].' · '.$person['email']."\n";
        }
        $payload['html'] = '<h1>Your visit is registered</h1><p style="white-space:pre-line">'.htmlspecialchars($payload['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        return $payload;
    }
}
