<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplate.php';
final class FeedbackEmail {
    public static function payload(array $booking, string $link): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        $destination = $contacts[$booking['destination']]['name'];
        $dates = $booking['overnight'] ? EmailTemplate::date($booking['arrival_date']).' to '.EmailTemplate::date($booking['departure_date']) : EmailTemplate::date($booking['visit_date']);
        $content = [
            'eyebrow'=>'A little feedback goes a long way', 'title'=>'How did your visit go?',
            'preheader'=>"We'd love to hear about your visit to ".$destination.'.',
            'paragraphs'=>['Hi '.$booking['full_name'].',', 'You recently booked a visit to '.$destination.'. If you made it, we hope you enjoyed your time with us!', "We'd love to hear what you enjoyed and what we could do better. Your feedback helps us make future visits more welcoming."],
            'details'=>['Destination'=>$destination, 'Visit date'=>$dates],
            'notes'=>["If you didn't attend, you can let us know in the form."],
            'action'=>['label'=>'Share your feedback','url'=>$link],
            'footer'=>'This feedback link is personal to your booking. Please keep it private.',
        ];
        return ['to'=>[$booking['email']], 'subject'=>'How was your visit to '.$destination.'?', 'text'=>EmailTemplate::text($content), 'html'=>EmailTemplate::html($content)];
    }
}
