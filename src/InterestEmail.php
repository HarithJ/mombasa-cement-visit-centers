<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplate.php';
require_once __DIR__.'/ManagerNotifications.php';
// Interest registrations notify the group managers only, never the destination team or the visitor.
final class InterestEmail {
    public static function payload(array $interest): array {
        $contacts = require __DIR__.'/../config/contacts.php';
        $place = $contacts[$interest['destination']]['name'];
        $date = EmailTemplate::date($interest['preferred_date']);
        $people = $interest['attendees'].' '.((int)$interest['attendees'] === 1 ? 'person' : 'people');
        $content = [
            'title'=>'Someone would like to visit.', 'eyebrow'=>'New interest',
            'preheader'=>$place.' · '.$people.' · '.$date,
            'paragraphs'=>['Hi,', $interest['full_name'].' has registered interest in visiting '.$place.' for a total of '.$people.'. Visits are not open yet, so nothing has been booked.'],
            'details'=>['Destination'=>$place, 'Name'=>$interest['full_name'], 'Organization'=>$interest['organization'] ?? '' ?: 'Not provided', 'Phone'=>$interest['phone'], 'Email'=>$interest['email'] ?: 'Not provided', 'Preferred visit date'=>$date, 'Attendees'=>(string)$interest['attendees']],
            'notes'=>['The visitor has not been sent any message.'],
            'contacts'=>[], 'contactHeading'=>'',
            'footer'=>'Interest registration · '.$place,
        ];
        if ($site = self::site()) $content['action'] = ['label'=>'View interest registrations', 'url'=>$site.'/admin/interest'];
        return [
            'to'=>array_values(array_unique(ManagerNotifications::emails())),
            'subject'=>'New interest: '.$place.' — '.$people.', '.(new DateTimeImmutable($interest['preferred_date']))->format('j M'),
            'text'=>EmailTemplate::text($content), 'html'=>EmailTemplate::html($content),
        ];
    }
    // The admin link needs the canonical site address; without one the email simply omits it.
    private static function site(): ?string {
        $site = rtrim(getenv('BOOKING_SITE_URL') ?: '', '/');
        $url = parse_url($site);
        return $url && ($url['scheme'] ?? '') === 'https' && !empty($url['host']) && empty($url['path']) && !isset($url['user']) && !isset($url['query']) && !isset($url['fragment']) ? $site : null;
    }
}
