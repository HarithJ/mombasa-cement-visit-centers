<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplate.php';
// The emails sent when a visit is requested: the visitor's "request received" and each user's "please confirm".
final class BookingEmail {
    public static function configured(): bool { return getenv('BOOKING_EMAIL_ENABLED') === '1'; }

    public static function visitorPayload(array $booking): array {
        $facts = self::facts($booking);
        $scenarioLines = [
            'day' => null,
            'day_transport' => "You've also asked for transport. We'll confirm it together with your visit.",
            'overnight' => "You've also asked to stay overnight. We'll confirm it together with your visit.",
            'overnight_transport' => "You've also asked for transport and an overnight stay. We'll confirm both together with your visit.",
        ];
        $content = [
            'title' => "We've received your request.", 'eyebrow' => 'Awaiting confirmation', 'preheader' => $facts['preheader'],
            'paragraphs' => array_values(array_filter(['Hi '.$booking['full_name'].',', 'Thanks for asking to visit '.$facts['place'].' on '.$facts['date'].' at '.$facts['time'].'. The Galana team will check the date and confirm by email and SMS, usually within a day.', $scenarioLines[$facts['scenario']]])),
            'details' => $facts['details'] + ['Status' => 'Awaiting confirmation'],
            'notes' => ["Your visit isn't confirmed yet. Please don't travel until you hear from us."],
            'contacts' => $facts['team']['people'], 'contactHeading' => 'Questions before your visit?',
            'footer' => 'Please keep your booking reference: '.$booking['reference'],
        ];
        return self::payload([$booking['email']], 'Your request to visit '.$facts['place'].' — '.$booking['reference'], $content);
    }

    // One user's copy of the new-booking email, with their own Accept and Reject links.
    public static function requestPayload(array $booking, array $user, string $acceptUrl, string $rejectUrl): array {
        $facts = self::facts($booking);
        $scenarioLines = [
            'day' => 'No transport or overnight stay was requested.',
            'day_transport' => "They've asked for company transport.",
            'overnight' => "They've asked to stay overnight.",
            'overnight_transport' => "They've asked for company transport and to stay overnight.",
        ];
        $content = [
            'title' => 'A new visit to confirm.', 'eyebrow' => 'Needs a decision', 'preheader' => $facts['preheader'],
            'paragraphs' => [
                'Hi '.explode(' ', trim($user['name']))[0].',',
                $booking['full_name'].' has asked to visit '.$facts['place'].' on '.$facts['date'].' at '.$facts['time'].' for a total of '.$facts['people'].'.',
                $scenarioLines[$facts['scenario']],
                'Please accept or reject the visit. The first person to decide settles it, and everyone else is told.',
            ],
            'details' => $facts['details'] + ['Visitor' => $booking['full_name'], 'Organization' => ($booking['organization'] ?? '') ?: 'Not provided', 'Phone' => $booking['phone'], 'Email' => $booking['email'] ?: 'Not provided'],
            'notes' => ["These buttons are personal to you; please don't forward this email. They work until 23:59 on ".$facts['date'].'. You can also decide from the admin panel.'],
            'actions' => [
                ['label' => 'Accept visit', 'url' => $acceptUrl],
                ['label' => 'Reject visit', 'url' => $rejectUrl],
                ['label' => 'Call the visitor', 'url' => 'tel:'.preg_replace('/[^+0-9]/', '', $booking['phone']), 'secondary' => true],
            ],
            'footer' => 'Please keep your booking reference: '.$booking['reference'],
        ];
        return self::payload([$user['email']], 'Please confirm: visit to '.$facts['place'].' on '.$facts['date'].' — '.$booking['reference'], $content);
    }

    // The visitor's email after a decision. A rejection names the decider as the contact and never gives the reason.
    // $changed: the booking was decided before, so this reverses an earlier decision.
    public static function decisionVisitorPayload(array $booking, array $decider, string $decision, bool $changed): array {
        $facts = self::facts($booking);
        $ref = $booking['reference'];
        $visit = $facts['place'].' on '.$facts['date'].' at '.$facts['time'];
        if ($decision === 'confirmed') {
            $scenarioLines = [
                'day' => 'Keep your booking reference handy, and get in touch if you have any questions before you arrive.',
                'day_transport' => 'Your transport is confirmed too. Our team will contact you about pickup details.',
                'overnight' => "Your overnight stay is confirmed too. Our team will contact you if there's anything to arrange.",
                'overnight_transport' => 'Your transport and overnight stay are confirmed too. Our team will contact you about pickup details.',
            ];
            $details = $facts['details'];
            if (!empty($booking['transport_requested'])) $details['Transport'] = 'Confirmed';
            if (!empty($booking['overnight'])) $details['Overnight stay'] = 'Confirmed';
            $content = [
                'title' => 'We look forward to welcoming you.', 'eyebrow' => 'Your visit is confirmed', 'preheader' => $facts['preheader'],
                'paragraphs' => ['Hi '.$booking['full_name'].',', $changed ? "Good news: we've been able to confirm your visit to $visit after all." : "Good news: your visit to $visit is confirmed.", $scenarioLines[$facts['scenario']]],
                'details' => $details + ['Status' => 'Confirmed'], 'notes' => [],
                'contacts' => $facts['team']['people'], 'contactHeading' => 'Questions before your visit?',
                'footer' => 'Please keep your booking reference: '.$ref,
            ];
            return self::payload([$booking['email']], 'Your visit to '.$facts['place'].' is confirmed — '.$ref, $content);
        }
        $content = [
            'title' => $changed ? 'We can no longer host your visit on this date.' : "We can't host your visit on this date.", 'eyebrow' => 'Visit not confirmed', 'preheader' => $facts['preheader'],
            'paragraphs' => ['Hi '.$booking['full_name'].',', $changed ? "Sorry, we're no longer able to host your visit to $visit, which we'd confirmed earlier." : "Sorry, we're unable to confirm your visit to $visit.", 'Please contact '.$decider['name'].' to talk about it or find another date:'],
            'contacts' => [['name' => $decider['name'], 'phone' => $decider['phone'], 'tel' => preg_replace('/[^+0-9]/', '', $decider['phone']), 'email' => $decider['email']]],
            'contactsFirst' => true,
            'details' => ['Booking reference' => $ref, 'Visit date' => $facts['date'], 'Time' => $facts['time'], 'Attendees' => (string)$booking['attendees'], 'Status' => 'Not confirmed'], 'notes' => [],
            'footer' => 'Please quote your booking reference: '.$ref,
        ];
        return self::payload([$booking['email']], ($changed ? 'Change to your visit to ' : 'About your visit request to ').$facts['place'].' — '.$ref, $content);
    }

    // One other user's "who decided what" email: no buttons, just a link to the booking in the admin panel.
    public static function decisionTeamPayload(array $booking, array $recipient, array $decider, string $decision, ?string $reason, bool $changed, string $adminUrl): array {
        $facts = self::facts($booking);
        $who = $decider['name']; $visitor = $booking['full_name'];
        $confirmed = $decision === 'confirmed';
        $visit = $visitor."'s visit to ".$facts['place'].' on '.$facts['date'].' at '.$facts['time'].' ('.$facts['people'].')';
        $first = $changed ? "$who changed $visit from ".($confirmed ? 'rejected to confirmed' : 'confirmed to rejected').'.' : "$who ".($confirmed ? 'confirmed' : 'rejected')." $visit.";
        $by = ($changed ? 'Changed' : ($confirmed ? 'Confirmed' : 'Rejected')).' by '.$who;
        $content = [
            'title' => $by.'.', 'eyebrow' => $confirmed ? 'Visit confirmed' : 'Visit rejected', 'preheader' => $facts['preheader'],
            'paragraphs' => [$first.' '.($confirmed ? "$visitor has been told by email and SMS. No action needed." : "The visitor has been asked to contact $who.")],
            'details' => $facts['details'] + ['Visitor' => $visitor, 'Organization' => ($booking['organization'] ?? '') ?: 'Not provided', 'Phone' => $booking['phone'], 'Email' => $booking['email'] ?: 'Not provided'],
            'notes' => $confirmed ? [] : ['Reason: '.$reason],
            'actions' => [['label' => 'View in admin panel', 'url' => $adminUrl, 'secondary' => true]],
            'footer' => 'Please keep your booking reference: '.$booking['reference'],
        ];
        return self::payload([$recipient['email']], $by.': visit on '.$facts['date'].' — '.$booking['reference'], $content);
    }

    private static function facts(array $booking): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        $team = $contacts[$booking['destination']];
        $date = EmailTemplate::date($booking['visit_date']);
        $time = (new DateTimeImmutable($booking['booked_time'], new DateTimeZone('Africa/Nairobi')))->format('g:i a').' EAT';
        $details = ['Booking reference' => $booking['reference'], 'Destination' => $team['name'], 'Visit date' => $date, 'Time' => $time, 'Attendees' => (string)$booking['attendees'], 'Transport' => !empty($booking['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested'];
        if ($booking['overnight']) $details['Overnight stay'] = 'Requested (subject to availability)';
        return [
            'team' => $team, 'place' => $team['name'], 'date' => $date, 'time' => $time, 'details' => $details,
            'people' => $booking['attendees'].' '.((int)$booking['attendees'] === 1 ? 'person' : 'people'),
            'scenario' => (!empty($booking['overnight']) ? 'overnight' : 'day').(!empty($booking['transport_requested']) ? '_transport' : ''),
            'preheader' => $team['name'].' · '.$date.' · '.$time.' · '.$booking['reference'],
        ];
    }

    private static function payload(array $to, string $subject, array $content): array {
        return ['to' => $to, 'subject' => $subject, 'text' => EmailTemplate::text($content), 'html' => EmailTemplate::html($content)];
    }
}
