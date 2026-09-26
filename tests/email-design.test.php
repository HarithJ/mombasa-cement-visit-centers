<?php
declare(strict_types=1);
require __DIR__.'/../src/BookingEmail.php';
require __DIR__.'/../src/FeedbackEmail.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$booking=['reference'=>'NY-TEST','full_name'=>'<Alex & Test>','phone'=>'+254700000000','email'=>'alex@example.com','destination'=>'galana','visit_date'=>'2099-05-01','booked_time'=>'09:00','attendees'=>3,'overnight'=>0,'arrival_date'=>null,'departure_date'=>null,'overnight_guests'=>null,'transport_requested'=>0];
foreach (['feeding','sahajanand','galana'] as $destination) foreach ([0,1] as $overnight) foreach ([0,1] as $transport) {
    if ($overnight && $destination !== 'galana') continue;
    $sample=array_merge($booking,['destination'=>$destination,'overnight'=>$overnight,'transport_requested'=>$transport,'arrival_date'=>$overnight?'2099-05-01':null,'departure_date'=>$overnight?'2099-05-03':null,'overnight_guests'=>$overnight?2:null]);
    foreach ([true,false] as $visitor) {
        $payload=$visitor?BookingEmail::visitorPayload($sample):BookingEmail::payload($sample);
        check(str_contains($payload['html'],'&lt;Alex &amp; Test&gt;') && !str_contains($payload['html'],'<Alex'), 'Names are escaped in HTML');
        check(str_contains($payload['text'],'<Alex & Test>'), 'Plain text preserves names');
        check(str_contains($payload['html'],'NYUMBA') && str_contains($payload['html'],'role="presentation"'), 'Branded email layout');
        foreach (['NY-TEST','1 May 2099','9:00 am EAT'] as $detail) check(str_contains($payload['html'],$detail) && str_contains($payload['text'],$detail),'Details in both alternatives');
        check(str_contains($payload['text'],'Staying guests: 2')===(bool)$overnight,'Overnight details only when requested');
        check(str_contains($payload['text'],'Transport: Requested (subject to availability)')===(bool)$transport,'Transport request clear');
        check(!str_contains($payload['html'],'<script') && !str_contains($payload['html'],'<img'), 'Email needs no JavaScript or external images');
        if ($visitor) {
            check(str_contains($payload['text'],'Hi <Alex & Test>,'),'Personal greeting');
            check(!str_contains($payload['text'],'your group'),'No separation of visitor and group');
            check(str_contains($payload['html'],'mailto:') && str_contains($payload['html'],'tel:'),'Destination contacts clickable');
            check($payload['to']===['alex@example.com'],'Visitor routing retained');
        } else {
            check(str_contains($payload['text'],'a total of 3 people'),'Manager receives total');
            check(str_contains($payload['html'],'href="tel:+254700000000"'),'Manager can call visitor');
            check(in_array('harithjaved@gmail.com',$payload['to'],true),'Additional manager email retained');
        }
    }
}
$solo=BookingEmail::payload(array_merge($booking,['attendees'=>1,'email'=>null]));
check(str_contains($solo['text'],'a total of 1 person.') && str_contains($solo['text'],'Email: Not provided'),'Solo count and optional email');
$link='https://example.com/feedback?token=sample&source=email';
$feedback=FeedbackEmail::payload($booking,$link);
check(str_contains($feedback['html'],'href="https://example.com/feedback?token=sample&amp;source=email"'),'Feedback CTA preserves private link');
check(str_contains($feedback['text'],$link) && str_contains($feedback['text'],"If you didn't attend"),'Text version includes action and non-attendance');
check(str_contains($feedback['html'],'&lt;Alex &amp; Test&gt;'),'Feedback greeting escaped');
echo "PASS email design: all scenarios, conversational copy, escaped HTML, text alternatives, routing, contact links and feedback CTA\n";
