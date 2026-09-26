<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplate.php';
final class BookingEmail {
    public static function configured(): bool { return getenv('BOOKING_EMAIL_ENABLED') === '1'; }
    public static function payload(array $booking): array { return self::build($booking, false); }
    public static function visitorPayload(array $booking): array { return self::build($booking, true); }

    private static function build(array $booking, bool $visitor): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        $team = $contacts[$booking['destination']];
        $date = EmailTemplate::date($booking['visit_date']);
        $time = (new DateTimeImmutable($booking['booked_time'], new DateTimeZone('Africa/Nairobi')))->format('g:i a').' EAT';
        $people = $booking['attendees'].' '.((int)$booking['attendees'] === 1 ? 'person' : 'people');
        $intro = $visitor
            ? ['Hi '.$booking['full_name'].',', 'Thanks for booking a visit to '.$team['name'].'! We look forward to welcoming you on '.$date.' at '.$time.'.']
            : ['Hi team,', $booking['full_name'].' has booked a visit to '.$team['name'].' on '.$date.' at '.$time.' for a total of '.$people.'.'];
        $scenario = (!empty($booking['overnight']) ? 'overnight' : 'day').(!empty($booking['transport_requested']) ? '_transport' : '');
        $visitorNotes = [
            'day' => 'Your visit is registered. Keep your booking reference handy, and get in touch if you have any questions before you arrive.',
            'day_transport' => "We've received your transport request. Our team will contact you to discuss arrangements and confirm availability.",
            'overnight' => "We've received your overnight stay request. Our team will contact you to confirm accommodation availability.",
            'overnight_transport' => "We've received your requests for transport and an overnight stay. Our team will contact you to discuss arrangements and confirm availability for both.",
        ];
        $managerNotes = [
            'day' => 'No company transport was requested. You can reach the visitor using the contact details below if you need to discuss the visit.',
            'day_transport' => 'They have requested company transport. Please contact the visitor to discuss arrangements and confirm availability.',
            'overnight' => 'They have requested an overnight stay. No company transport was requested. Please contact the visitor to confirm accommodation availability.',
            'overnight_transport' => 'They have requested company transport and an overnight stay. Please contact the visitor to coordinate both and confirm availability.',
        ];
        $intro[] = $visitor ? $visitorNotes[$scenario] : $managerNotes[$scenario];
        $details = ['Booking reference'=>$booking['reference'], 'Destination'=>$team['name'], 'Visit date'=>$date, 'Time'=>$time, 'Attendees'=>(string)$booking['attendees'], 'Transport'=>!empty($booking['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested'];
        if ($booking['overnight']) $details += ['Overnight stay requested'=>EmailTemplate::date($booking['arrival_date']).' to '.EmailTemplate::date($booking['departure_date']), 'Staying guests'=>(string)$booking['overnight_guests']];
        if (!$visitor) $details += ['Visitor'=>$booking['full_name'], 'Phone'=>$booking['phone'], 'Email'=>$booking['email'] ?: 'Not provided'];
        $content = [
            'title'=>$visitor ? "We look forward to welcoming you." : 'A new visit to plan.',
            'eyebrow'=>$visitor ? 'Your visit is registered' : 'New booking',
            'preheader'=>$team['name'].' · '.$date.' · '.$time.' · '.$booking['reference'],
            'paragraphs'=>$intro, 'details'=>$details,
            'notes'=>[($visitor ? 'Your booking' : 'The booking').' records the visit; capacity and any accommodation arrangements remain subject to confirmation.'],
            'contacts'=>$visitor ? $team['people'] : [], 'contactHeading'=>'Questions before your visit?',
            'footer'=>'Please keep your booking reference: '.$booking['reference'],
        ];
        if (!$visitor) $content['action'] = ['label'=>'Call the visitor', 'url'=>'tel:'.preg_replace('/[^+0-9]/', '', $booking['phone'])];
        return [
            'to'=>$visitor ? [$booking['email']] : array_values(array_unique([...array_column($team['people'], 'email'), 'harithjaved@gmail.com'])),
            'subject'=>($visitor ? 'Your visit to '.$team['name'].' is registered' : 'New visit to '.$team['name']).' — '.$booking['reference'],
            'text'=>EmailTemplate::text($content), 'html'=>EmailTemplate::html($content),
        ];
    }
}
