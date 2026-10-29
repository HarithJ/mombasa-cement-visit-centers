<?php
function privacyEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$e = 'privacyEscape';
$assets = $assets ?? '/assets/';
// Update this date whenever the notice changes.
$updated = '29 October 2026';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Privacy notice — Visit Nyumba</title><link rel="icon" href="<?= $e($assets) ?>nyumba-group.svg" type="image/svg+xml"><link rel="stylesheet" href="<?= $e($assets) ?>feedback.css"></head>
<body data-version="<?= $e($uiVersion) ?>">
<header class="feedback-header"><a href="/"><img src="<?= $e($assets) ?>nyumba-group.svg" alt="Nyumba Group home" width="60" height="82"></a><span>VISIT NYUMBA<br><strong>Privacy notice.</strong></span></header>
<main class="privacy">
<p class="eyebrow">YOUR DETAILS, YOUR RIGHTS</p>
<h1>How we use your information.</h1>
<p class="intro">This notice explains what Nyumba Group collects when you book a visit or register interest on this website, why we collect it, and the choices you have. We process personal data in line with Kenya’s Data Protection Act, 2019.</p>
<p class="visit-context">Last updated <?= $e($updated) ?></p>

<h2>Who we are</h2>
<p>Nyumba Group is responsible for (the “data controller” of) the information collected on this website. It is used to arrange visits to Sahajanand Special School, Galana Farm and the Kibarani Feeding center.</p>

<h2>What we collect</h2>
<ul>
<li><strong>Booking details:</strong> your full name, phone number, and, if you provide them, your email address and organization.</li>
<li><strong>Visit details:</strong> destination, date and time, number of attendees, whether you requested transport, and for Galana Farm whether you requested an overnight stay.</li>
<li><strong>Interest registrations:</strong> for places not yet open for booking, your name, phone number, optional email address and organization, the place, your preferred visit date and number of attendees.</li>
<li><strong>Feedback:</strong> if you answer our post-visit feedback request, whether you attended, your rating and any comments. Feedback is linked to your booking.</li>
<li><strong>Technical data:</strong> our web server records IP addresses and request details in standard logs for security and troubleshooting.</li>
</ul>
<p>We do not ask for ID numbers, payment details or sensitive personal data. Please do not include such information in free-text fields.</p>

<h2>Why we use it</h2>
<ul>
<li>To register your visit and send you a booking confirmation by email or SMS.</li>
<li>To let the destination team prepare for your visit, contact you about it, and arrange transport or accommodation you request.</li>
<li>To understand how much interest there is in places not yet open for booking, and to contact you if visits there reopen.</li>
<li>To invite you to give feedback after your visit and use it to improve future visits.</li>
<li>To keep the website secure and working.</li>
</ul>
<p>We use your information because it is needed to arrange the visit you asked for, and for our legitimate interest in improving visits and keeping the site secure. We do not sell your information or use it for marketing.</p>

<h2>Who we share it with</h2>
<ul>
<li><strong>Destination teams</strong> at the place you are visiting, who receive your booking details by email and SMS. Interest registrations are shared only with Nyumba Group managers.</li>
<li><strong>Google Cloud</strong>, which hosts this website and its database in Johannesburg, South Africa.</li>
<li><strong>Resend</strong>, which delivers our booking and feedback emails and may process email data in the United States.</li>
<li><strong>Africa’s Talking</strong>, which delivers our booking SMS messages.</li>
</ul>
<p>These providers act on our instructions and are bound by contractual data protection terms. Because some of them process data outside Kenya, we rely on those safeguards for transfers, as the Data Protection Act requires. We may also disclose information where the law requires it.</p>

<h2>Cookies</h2>
<p>This website uses only a strictly necessary session cookie, which protects the booking form from misuse. We do not use analytics, advertising or tracking cookies. The “Open in Google Maps” link takes you to Google’s website, which has its own privacy policy.</p>

<h2>How we protect it</h2>
<p>The website uses encrypted HTTPS connections. Booking data is stored in a private database that is not reachable from the web, and only authorized staff can view it.</p>

<h2>Your rights</h2>
<p>Under the Data Protection Act, 2019, you have the right to:</p>
<ul>
<li>be informed about how your data is used;</li>
<li>access the personal data we hold about you;</li>
<li>ask us to correct inaccurate data or delete data we no longer need;</li>
<li>object to our use of your data.</li>
</ul>
<p>To make a request, contact the destination team listed in your booking confirmation and quote your booking reference. We may need to confirm your identity before acting on a request.</p>
<p>If you are unhappy with how we handle your information, you can complain to the <a href="https://www.odpc.go.ke/" target="_blank" rel="noopener noreferrer">Office of the Data Protection Commissioner</a>.</p>

<h2>Children</h2>
<p>Visits should be booked by an adult. If you book for a group that includes children, we only record the number of attendees, not their names.</p>

<p class="privacy-back"><a href="/">Back to Visit Nyumba</a></p>
</main><footer>Nyumba Group · Building better visits, together.</footer></body></html>
