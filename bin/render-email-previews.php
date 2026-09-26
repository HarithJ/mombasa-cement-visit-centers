<?php
declare(strict_types=1);
// Local previews only: no database, credentials, queue or delivery client.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../src/BookingEmail.php';
require __DIR__.'/../src/FeedbackEmail.php';
$directory = __DIR__.'/../test-results/emails';
if (!is_dir($directory)) mkdir($directory, 0700, true);
$booking = ['reference'=>'NY-PREVIEW-2026','full_name'=>'Alex Mwangi','phone'=>'+254700000000','email'=>'alex@example.com','destination'=>'galana','visit_date'=>'2026-10-10','booked_time'=>'09:00','attendees'=>8,'overnight'=>0,'arrival_date'=>null,'departure_date'=>null,'overnight_guests'=>null,'transport_requested'=>0];
$items = [];
foreach (['feeding','sahajanand','galana'] as $destination) foreach ([0,1] as $overnight) foreach ([0,1] as $transport) {
    if ($overnight && $destination !== 'galana') continue;
    $sample = array_merge($booking,['destination'=>$destination,'overnight'=>$overnight,'transport_requested'=>$transport,'arrival_date'=>$overnight?'2026-10-10':null,'departure_date'=>$overnight?'2026-10-12':null,'overnight_guests'=>$overnight?5:null]);
    foreach (['visitor'=>BookingEmail::visitorPayload($sample),'manager'=>BookingEmail::payload($sample)] as $audience=>$payload) {
        $name = $destination.'-'.($overnight?'overnight':'day').'-'.($transport?'transport':'own-travel').'-'.$audience;
        file_put_contents($directory.'/'.$name.'.html',$payload['html']);
        file_put_contents($directory.'/'.$name.'.txt',$payload['text']);
        $items[$name] = $payload['subject'];
    }
}
$payload=FeedbackEmail::payload($booking,'https://example.com/feedback?token=preview-only');
file_put_contents($directory.'/feedback.html',$payload['html']);
file_put_contents($directory.'/feedback.txt',$payload['text']);
$items['feedback']=$payload['subject'];
$html='<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nyumba email previews</title><body style="font:16px/1.8 Arial;background:#f3f2eb;color:#173f35;margin:40px;max-width:900px;"><h1>Nyumba email previews</h1><p>Sample bookings only. No emails have been sent.</p><ul>';
foreach ($items as $name=>$subject) $html.='<li><a style="color:#173f35;" href="'.$name.'.html">'.EmailTemplate::escape($name).'</a> — '.EmailTemplate::escape($subject).'</li>';
file_put_contents($directory.'/index.html',$html.'</ul></body></html>');
echo "Email previews saved to ".$directory."/index.html\n";
