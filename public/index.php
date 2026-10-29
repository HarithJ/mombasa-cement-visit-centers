<?php
declare(strict_types=1);
if (preg_match('#^/admin(?:/|$)#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '')) { require __DIR__.'/../src/admin-web.php'; exit; }
$uiVersion = 'v3';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/feedback') { require __DIR__.'/../src/feedback-web.php'; exit; }
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/privacy') { require __DIR__.'/../src/privacy-web.php'; exit; }
require __DIR__.'/../src/web.php';
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function photoVariants(string $path): array {
    $variants = [];
    foreach ([360, 720, 1024] as $width) {
        $variant = dirname($path).'/responsive/'.pathinfo($path, PATHINFO_FILENAME).'-'.$width.'.webp';
        if (is_file(__DIR__.'/'.$variant)) $variants[$width] = $variant;
    }
    return $variants;
}
function photoSrcset(array $variants): string {
    $sources = [];
    foreach ($variants as $width => $path) $sources[] = $path.' '.$width.'w';
    return implode(', ', $sources);
}
function icon(string $name, string $class = ''): string {
    $paths = [
        'arrow' => '<path d="M4 12h15M13 5l7 7-7 7"/>',
        'diagonal' => '<path d="M6 18 18 6M6 6h12v12"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'leaf' => '<path d="M19 4c-8-1-14 3-14 9a6 6 0 0 0 6 6c6 0 9-7 8-15ZM5 20 15 10"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
        'people' => '<circle cx="9" cy="7" r="3"/><path d="M3 20v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M21 20v-3a6 6 0 0 0-4-5"/>',
        'photo' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 16 5-5 5 5 3-3 5 5"/>',
        'moon' => '<path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
}
$mapLinks = require __DIR__.'/../config/locations.php';
$routeIllustrations = require __DIR__.'/../config/route-illustrations.php';
$photoDescriptions = require __DIR__.'/../config/photo-descriptions.php';
$destinations = [
    'sahajanand' => ['name' => 'Sahajanand Special School', 'category' => 'EDUCATION & COMMUNITY', 'description' => 'Meet the people behind a school built around care, connection and different ways of learning.', 'photos' => ['01-entrance.webp']],
    'galana' => ['name' => 'Galana Farm', 'category' => 'NATURE & AGRICULTURE', 'description' => 'Trade the everyday for open fields. Discover agriculture at Galana Farm, with the option to request an overnight stay.'],
    'feeding' => ['name' => 'Kibarani Feeding center', 'category' => 'CARE & COMMUNITY', 'description' => 'Discover the people and daily work behind community feeding at Kibarani. A visit with care at its heart.', 'photos' => ['01-meals.webp', '02-centre-aerial.webp', '03-centre-building.webp', '06-dining-shelter.webp', '08-food-service.webp', '09-meal-collection.webp', '10-meal-queue-aerial.webp', '11-meal-service.webp', '12-meal-tables.webp', '17-serving-station.webp', '20-visitors-collecting-meals.webp']],
];
foreach ($destinations as $id => &$destination) {
    $files = glob(__DIR__.'/assets/photos/'.$id.'/*.{webp,jpg,jpeg,png}', GLOB_BRACE) ?: [];
    sort($files);
    // A destination can list the photographs to show; listing one shows it still, without a gallery.
    if (isset($destination['photos'])) $files = array_values(array_filter($files, fn($path) => in_array(basename($path), $destination['photos'], true)));
    $destination['still'] = count($files) === 1;
    $destination['images'] = array_map(fn($path) => 'assets/photos/'.$id.'/'.basename($path), $files);
    $destination['placeholder'] = !$files;
    if (!$files) $destination['images'] = ['assets/galana-placeholder.svg', 'assets/galana-placeholder.svg'];
    if (count($destination['images']) === 1) $destination['images'][] = $destination['images'][0];
    $destination['variants'] = array_map('photoVariants', $destination['images']);
}
unset($destination);
foreach ($destinations as $id => &$destination) {
    $destination['imageAlts'] = array_map(fn($path) => $photoDescriptions[$id][basename($path)] ?? $destination['name'], $destination['images']);
}
unset($destination);
$formLocation = $interestConfirmation['destination'] ?? $confirmation['destination'] ?? $input['location'] ?? 'galana';
$interestMode = InterestRegistration::interestOnly($formLocation);
// Booking-only and interest-only parts of the dialog; scripts switch them when the destination changes.
$mode = fn(string $name) => 'data-mode="'.$name.'"'.($name === ($interestMode ? 'interest' : 'booking') ? '' : ' hidden');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Sahajanand Special School, Galana Farm, and the Kibarani Feeding center. Register your Nyumba day visit.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#173f35">
    <title>Visit Nyumba — A visit that means more</title>
    <link rel="icon" href="assets/nyumba-group.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/travel.css">
    <noscript><style>.destination-photo > .photo-secondary { display: none; }</style></noscript>
    <script id="booking-state" type="application/json"><?= json_encode($webState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script id="destination-config" type="application/json"><?= json_encode($schedule, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
    <script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#destinations">Skip to destinations</a>
<header class="header">
    <div class="header-inner">
        <div class="brand-lockup" aria-label="Nyumba Group">
            <img class="group-logo" src="assets/nyumba-group.svg" alt="Nyumba Group" width="56" height="76">


        </div>
    </div>
</header>
<main>
    <section class="visit-hero" aria-labelledby="hero-title">
        <div class="explorer-hero">
            <img class="explorer-landscape" src="assets/photos/galana/02-circular-fields-aerial.webp" srcset="assets/photos/galana/responsive/02-circular-fields-aerial-720.webp 720w, assets/photos/galana/responsive/02-circular-fields-aerial-1024.webp 1024w, assets/photos/galana/02-circular-fields-aerial.webp 1600w" sizes="100vw" alt="Aerial view of a large green irrigated field under a cloudy sky at Galana Farm" width="1600" height="900" fetchpriority="high">
            <div class="explorer-shade"></div>
            <div class="explorer-intro wrap"><h1 id="hero-title">Visit<br>Nyumba<span class="hero-spark" aria-hidden="true">✳</span></h1><div class="explorer-promise"><p>A little adventure.<br>A whole new perspective.</p><a class="explorer-button" href="#destinations">Explore the places <?= icon('diagonal') ?></a></div></div>
        </div>
        <div class="discovery-heading wrap"><p class="eyebrow">PICK YOUR NEXT EXPERIENCE</p><h2>Good days start<br>with <span>curiosity.</span></h2></div>
        <div class="destinations wrap" id="destinations" aria-label="Choose a destination">
            <?php $number = 0; foreach (['galana', 'sahajanand', 'feeding'] as $id): $destination = $destinations[$id]; $number++; $featured = $number === 1; $photoSizes = $featured ? '(max-width: 1000px) calc(100vw - 40px), 62vw' : '(max-width: 760px) calc(100vw - 40px), 46vw'; ?>
            <article class="destination <?= $featured ? 'destination-featured' : 'destination-card' ?>" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>">
                <div class="photo-stack">
                <div class="destination-photo <?= $destination['placeholder'] ? 'placeholder' : '' ?>"<?php if ($destination['still']): ?> data-still<?php endif; ?> data-images="<?= e(json_encode($destination['images'], JSON_THROW_ON_ERROR)) ?>" data-variants="<?= e(json_encode($destination['variants'], JSON_THROW_ON_ERROR)) ?>" data-alts="<?= e(json_encode($destination['imageAlts'] ?? [], JSON_THROW_ON_ERROR)) ?>" tabindex="0" aria-label="<?= e($destination['name']) ?> image gallery. Photos change automatically; use the photo button for the next view.">
                    <img class="photo-primary" src="<?= e($destination['variants'][0][720] ?? $destination['images'][0]) ?>" srcset="<?= e(photoSrcset($destination['variants'][0])) ?>" sizes="<?= e($photoSizes) ?>" decoding="async" alt="<?= $destination['placeholder'] ? 'Illustrated landscape placeholder; destination photography not yet supplied' : e($destination['imageAlts'][0] ?? $destination['name']) ?>" width="800" height="900" fetchpriority="<?= $number === 1 ? 'high' : 'auto' ?>">
                    <?php if (!$destination['still']): ?>
                    <img class="photo-secondary" decoding="async" alt="<?= $destination['placeholder'] ? 'Alternate crop of an illustrated placeholder, not a destination photograph' : e($destination['imageAlts'][1] ?? $destination['name']) ?>" width="800" height="900">
                    <noscript>
                        <img class="photo-secondary" src="<?= e($destination['variants'][1][720] ?? $destination['images'][1]) ?>" srcset="<?= e(photoSrcset($destination['variants'][1])) ?>" sizes="<?= e($photoSizes) ?>" alt="<?= $destination['placeholder'] ? 'Alternate crop of an illustrated placeholder, not a destination photograph' : e($destination['imageAlts'][1] ?? $destination['name']) ?>" width="800" height="900">
                    </noscript>

                    <?php if ($destination['placeholder']): ?><span class="placeholder-label">Illustration · Photo coming soon</span><?php endif; ?>
                    <?php endif; ?>
                    <div class="photo-bottom"><button class="photo-toggle" type="button" aria-label="Show alternate view of <?= e($destination['name']) ?>" aria-pressed="false"><?= icon('photo') ?><?php if (!$destination['placeholder'] && !$destination['still']): ?><svg class="photo-progress" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" pathLength="100" /></svg><?php endif; ?></button></div>
                </div>
                </div>
                <div class="destination-info">
                    <p class="eyebrow category"><span>0<?= $number ?> /</span> <?= e($destination['category']) ?></p>
                    <h2><?= e($destination['name']) ?></h2>
                    <p class="destination-description"><?= e($destination['description']) ?></p>
                    <?php if ($featured): ?><p class="destination-badge"><?= icon('moon') ?> Overnight stays on request</p><?php endif; ?>
                    <?php if (InterestRegistration::interestOnly($id)): ?>
                    <div class="card-actions"><a class="book-button" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Tell us you’re interested <?= icon('diagonal') ?></a><p class="destination-badge"><?= icon('clock') ?> Visits opening soon — register your interest</p></div>
                    <?php else: ?>
                    <a class="book-button" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Let’s plan a visit <?= icon('diagonal') ?></a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="final-cta reveal" aria-labelledby="cta-title"><div class="wrap final-cta-inner"><div><p class="eyebrow">MAKE ROOM FOR SOMETHING DIFFERENT</p><h2 id="cta-title">See you<br>out there<span aria-hidden="true">↗</span></h2></div><a class="button dark-button" href="#destinations">Explore the destinations <?= icon('diagonal') ?></a></div></section>
</main>
<footer class="footer wrap">
    <div class="footer-top"><div class="footer-brand"><img src="assets/nyumba-group.svg" alt="Nyumba Group" width="60" height="82"><div><strong>Visit Nyumba</strong><span>Places. People. Purpose.</span></div></div><div class="footer-links"><a href="#destinations">Places to visit</a><a href="/privacy">Privacy notice</a><a href="https://www.nyumba.com/nyumba-foundation/" target="_blank" rel="noopener noreferrer">Nyumba Foundation <?= icon('diagonal') ?></a></div></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Nyumba Group</span><span>Visit Nyumba · Day bookings</span><a href="https://www.nyumba.com/" target="_blank" rel="noopener noreferrer">Visit nyumba.com <?= icon('diagonal') ?></a></div>
</footer>
<dialog id="booking-dialog" aria-labelledby="<?= $confirmation ? 'confirmation-title' : ($interestConfirmation ? 'interest-confirmation-title' : 'booking-title') ?>" <?= $openForm || $confirmation || $interestConfirmation ? 'open' : '' ?>>
    <div class="dialog-toolbar"><span class="itinerary-label">YOUR NEXT ADVENTURE <span>/ VISIT NYUMBA</span></span><a role="button" href="/" class="close-button" aria-label="Close booking form"><?= icon('close') ?></a></div>
    <div class="dialog-shell">
        <aside class="booking-aside"><img id="booking-image" src="assets/galana-placeholder.svg" alt="" width="500" height="800"><div class="aside-overlay"><p class="eyebrow">THE DESTINATION</p><h2 id="aside-destination"><?= e($destinations[$formLocation]['name'] ?? $destinations['galana']['name']) ?></h2><p>Something different<br>to look forward to.</p><span><?= icon('leaf') ?> Visit Nyumba</span></div></aside>
        <div class="booking-main">
            <div id="form-view" <?= $confirmation || $interestConfirmation ? 'hidden' : '' ?>>
                <p class="eyebrow dialog-eyebrow"><span <?= $mode('booking') ?>>LET’S PLAN YOUR VISIT</span><span <?= $mode('interest') ?>>VISITS OPENING SOON</span></p><h2 id="booking-title"><span <?= $mode('booking') ?>>Make it a date.</span><span <?= $mode('interest') ?>>Tell us you’re interested.</span></h2><p class="dialog-subtitle"><span <?= $mode('booking') ?>>Your people. Your place. Your next day out.</span><span <?= $mode('interest') ?>>Let us know when you’d like to come.</span></p>
                <div class="prototype-notice"><span class="status-dot"></span><span <?= $mode('booking') ?>><strong>Visit bookings</strong> · Your visit is registered after submission.</span><span <?= $mode('interest') ?>><strong>Not a booking</strong> · Visits here aren’t open yet. We’ll contact you if they reopen.</span></div>
                <form id="booking-form" method="post" action="/" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="submissionToken" value="<?= e($token) ?>"><input type="hidden" id="intent" name="intent" value="<?= $interestMode ? 'interest' : '' ?>">
                    <div id="error-summary" class="error-summary" role="alert" tabindex="-1" <?= $errors ? '' : 'hidden' ?>><?= e(implode(' ', $errors)) ?></div>
                    <div class="form-grid">
                        <div class="field"><label for="full-name">Full name <span aria-hidden="true">*</span></label><input id="full-name" name="fullName" value="<?= e($input['fullName'] ?? '') ?>" type="text" autocomplete="name" maxlength="120" required placeholder="e.g. Alex Mwangi" aria-describedby="full-name-error"><span class="field-error" id="full-name-error"><?= e($errors['fullName'] ?? '') ?></span></div>
                        <div class="field"><label for="organization">Organization (optional)</label><input id="organization" name="organization" value="<?= e($input['organization'] ?? '') ?>" type="text" autocomplete="organization" maxlength="120" placeholder="e.g. Mombasa Primary School" aria-describedby="organization-error"><span class="field-error" id="organization-error"><?= e($errors['organization'] ?? '') ?></span></div>
                        <div class="field"><label for="phone">Phone number <span aria-hidden="true">*</span></label><input id="phone" name="phone" value="<?= e($input['phone'] ?? '') ?>" type="tel" autocomplete="tel" maxlength="24" required placeholder="e.g. +254 712 345 678" aria-describedby="phone-error"><span class="field-error" id="phone-error"><?= e($errors['phone'] ?? '') ?></span></div>
                        <div class="field"><label for="email">Email address (optional)</label><input id="email" name="email" value="<?= e($input['email'] ?? '') ?>" type="email" autocomplete="email" maxlength="254" placeholder="e.g. alex@example.com" aria-describedby="email-error"><span class="field-error" id="email-error"><?= e($errors['email'] ?? '') ?></span></div>
                        <input id="location" name="location" type="hidden" value="<?= e($formLocation) ?>">
                        <?php foreach ($destinations as $id => $destination): ?><span hidden data-location-meta="<?= e($id) ?>" data-map-url="<?= e($mapLinks[$id]) ?>" data-route-image="<?= e($routeIllustrations[$id]['image']) ?>" data-route-alt="<?= e($routeIllustrations[$id]['alt']) ?>" data-name="<?= e($destination['name']) ?>"></span><?php endforeach; ?>
                        <span class="field-error" id="location-error" hidden><?= e($errors['location'] ?? '') ?></span>

                        <div class="field"><label for="visit-date"><span <?= $mode('booking') ?>>Visit date</span><span <?= $mode('interest') ?>>Preferred visit date</span> <span aria-hidden="true">*</span></label><input id="visit-date" name="visitDate" value="<?= e($input['visitDate'] ?? '') ?>" type="date" required <?= $interestMode ? 'min="'.e($webState['interestDates'][0]).'" max="'.e($webState['interestDates'][1]).'"' : '' ?> aria-describedby="visit-date-error"><span class="field-error" id="visit-date-error"><?= e($errors['visitDate'] ?? '') ?></span></div>
                        <div class="field" <?= $mode('booking') ?>><label for="time-slot">Time slot <span aria-hidden="true">*</span></label><select id="time-slot" name="timeSlot" required <?= $interestMode ? 'disabled' : '' ?> aria-describedby="time-slot-error schedule-note"><option value="">Select a time</option><?php foreach ($schedule[$formLocation]['slots'] ?? [] as $time): ?><option value="<?= e($time) ?>" <?= ($input['timeSlot'] ?? '') === $time ? 'selected' : '' ?>><?= e(date('g:i a', strtotime($time))) ?></option><?php endforeach; ?></select><span class="field-error" id="time-slot-error"><?= e($errors['timeSlot'] ?? '') ?></span></div>
                        <p class="schedule-note full" id="schedule-note" <?= $mode('booking') ?>><?= icon('clock') ?> Africa/Nairobi · Provisional schedule; times are subject to review.</p>
                        <div class="field full attendee-field"><div><label for="attendees">Number of attendees <span aria-hidden="true">*</span></label><p class="field-hint" id="attendee-hint">Include yourself in the total.</p></div><div><input id="attendees" name="attendees" type="number" min="1" step="1" value="<?= e($input['attendees'] ?? '1') ?>" required aria-describedby="attendee-hint attendees-error"><span class="field-error" id="attendees-error"><?= e($errors['attendees'] ?? '') ?></span></div></div>
                    </div>
                    <label class="checkbox-label" <?= $mode('booking') ?>><input id="transport-requested" name="transportRequested" type="checkbox" aria-labelledby="transport-label" aria-describedby="transport-hint transport-requested-error" <?= ($input['transportRequested'] ?? '') === 'on' ? 'checked' : '' ?> <?= $interestMode ? 'disabled' : '' ?>><span class="checkbox-copy"><strong id="transport-label">I would like the company to arrange transport for my visit.</strong><span id="transport-hint">Transport is subject to availability. Our team will contact you to confirm arrangements.</span></span></label>
                    <span class="field-error" id="transport-requested-error"><?= e($errors['transportRequested'] ?? '') ?></span>
                    <div id="overnight-option" <?= ($input['location'] ?? '') === 'galana' ? '' : 'hidden' ?>><label class="checkbox-label"><input id="overnight" aria-describedby="overnight-error" name="overnight" type="checkbox" <?= ($input['overnight'] ?? '') === 'on' ? 'checked' : '' ?>><span class="checkbox-copy"><strong><?= icon('moon') ?> Stay overnight at Galana Farm</strong><span>Add your preferred dates and number of staying guests.</span></span></label></div>
                    <span class="field-error" id="overnight-error"><?= e($errors['overnight'] ?? '') ?></span><div id="overnight-fields" class="overnight-fields" <?= ($input['location'] ?? '') === 'galana' ? '' : 'hidden' ?>><div class="form-grid"><div class="field"><label for="arrival-date">Arrival date <span aria-hidden="true">*</span></label><input id="arrival-date" name="arrivalDate" type="date" value="<?= e($input['arrivalDate'] ?? '') ?>" aria-describedby="arrival-date-error arrival-note"><span class="field-error" id="arrival-date-error"><?= e($errors['arrivalDate'] ?? '') ?></span></div><div class="field"><label for="departure-date">Departure date <span aria-hidden="true">*</span></label><input id="departure-date" name="departureDate" type="date" value="<?= e($input['departureDate'] ?? '') ?>" aria-describedby="departure-date-error"><span class="field-error" id="departure-date-error"><?= e($errors['departureDate'] ?? '') ?></span></div><p class="field-hint full" id="arrival-note">Arrival matches your visit date. Accommodation is a request, not a room allocation.</p><div class="field full"><label for="overnight-guests">Overnight guests <span aria-hidden="true">*</span></label><input id="overnight-guests" name="overnightGuests" type="number" min="1" step="1" value="<?= e($input['overnightGuests'] ?? '1') ?>" aria-describedby="overnight-guests-error"><span class="field-error" id="overnight-guests-error"><?= e($errors['overnightGuests'] ?? '') ?></span></div></div></div>
                    <p class="privacy-note"><span <?= $mode('booking') ?>>Your name, email address, phone number and visit details are stored to record your visit. Booking emails and SMS may be sent to you and the destination team to coordinate your visit.</span><span <?= $mode('interest') ?>>Your details are stored to understand interest in visits and to contact you if visits reopen. They are shared only with the Nyumba Group team.</span> <a href="/privacy" target="_blank">Privacy notice</a></p>
                        <div class="destination-location">
                            <details id="inline-map">
                                <summary>View illustrated route <span aria-hidden="true">+</span></summary>
                                <?php $route = $routeIllustrations[$formLocation] ?? $routeIllustrations['galana']; ?>
                                <div id="inline-map-frame" class="inline-map-frame"><a id="route-image-link" href="<?= e($route['image']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open full-size route illustration in a new tab"><img id="route-image" src="<?= e($route['image']) ?>" alt="<?= e($route['alt']) ?>" width="1774" height="887" loading="lazy"></a><span class="route-image-hint">Illustrated route · Not to scale. Tap the image to enlarge.</span></div>
                            </details>
                            <a id="destination-map" href="<?= e($mapLinks[$formLocation] ?? $mapLinks['galana']) ?>" target="_blank" rel="noopener noreferrer"><span><strong id="map-destination-name"><?= e($destinations[$formLocation]['name'] ?? $destinations['galana']['name']) ?></strong><span class="map-action">Open in Google Maps · new tab</span></span><?= icon('diagonal') ?></a>
                        </div>
                    <button class="button submit-button" type="submit"><span id="submit-label"><?= $interestMode ? 'Register my interest' : 'Book my visit' ?></span><span class="spinner" hidden></span><?= icon('arrow', 'submit-arrow') ?></button>
                    <p class="required-note">* Required fields. No ID or email address needed.</p>

                </form>
            </div>
            <div id="confirmation-view" <?= $confirmation ? '' : 'hidden' ?>><div class="confirmation-icon"><?= icon('check') ?></div><p class="eyebrow">YOUR VISIT, AT A GLANCE</p><h2 id="confirmation-title" tabindex="-1">Visit registered.</h2><p class="confirmation-subtitle">Your visit has been recorded. Please keep your booking reference.</p><div class="confirmation-preview">Registration acknowledges your visit; it does not imply a capacity check.</div><div class="reference-label">BOOKING REFERENCE<strong><?= e($confirmation['reference'] ?? '') ?></strong></div><dl id="confirmation-details"><?php if ($confirmation): $rows = ['Name' => $confirmation['full_name'], 'Organization' => ($confirmation['organization'] ?? '') !== '' ? $confirmation['organization'] : 'Not provided', 'Email' => $confirmation['email'] ?? 'Not provided', 'Destination' => $schedule[$confirmation['destination']]['name'], 'Visit date' => $confirmation['visit_date'], 'Time' => $confirmation['booked_time'].' (Africa/Nairobi)', 'Attendees' => (string)$confirmation['attendees'], 'Transport' => !empty($confirmation['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested', 'Phone' => '••• ••• '.substr($confirmation['phone'], -3)]; if ($confirmation['overnight']) { $rows['Overnight stay'] = $confirmation['arrival_date'].' – '.$confirmation['departure_date']; $rows['Staying guests'] = (string)$confirmation['overnight_guests']; } foreach ($rows as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; endif; ?></dl><?php require __DIR__.'/../src/confirmation-contacts.php'; ?><button type="button" class="confirmation-close">Back to exploring</button></div>
            <div id="interest-confirmation-view" <?= $interestConfirmation ? '' : 'hidden' ?>><div class="confirmation-icon"><?= icon('check') ?></div><p class="eyebrow">YOUR INTEREST, NOTED</p><h2 id="interest-confirmation-title" tabindex="-1">Thanks — we’ve noted your interest.</h2><?php if ($interestConfirmation): $place = $schedule[$interestConfirmation['destination']]['name']; ?><p class="confirmation-subtitle">Visits to <?= e($place) ?> aren’t open yet. We’ll contact you if they reopen.</p><div class="confirmation-preview">This is not a booking. Nothing has been reserved.</div><dl id="interest-details"><?php $rows = ['Name' => $interestConfirmation['full_name'], 'Organization' => $interestConfirmation['organization'] ?? '' ?: 'Not provided', 'Email' => $interestConfirmation['email'] ?? '' ?: 'Not provided', 'Destination' => $place, 'Preferred visit date' => (new DateTimeImmutable($interestConfirmation['preferred_date']))->format('j F Y'), 'Attendees' => (string)$interestConfirmation['attendees'], 'Phone' => '••• ••• '.substr($interestConfirmation['phone'], -3)]; foreach ($rows as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?></dl><?php endif; ?><a role="button" href="/" class="confirmation-close">Back to exploring</a></div>
        </div>
    </div>
</dialog>
<dialog class="photo-lightbox" id="photo-lightbox" aria-labelledby="gallery-title">
    <div class="gallery-header"><div><p class="eyebrow">MOMENTS FROM NYUMBA</p><h2 id="gallery-title"></h2></div><button type="button" id="gallery-close" aria-label="Close photo gallery"><?= icon('close') ?></button></div>
    <div class="gallery-stage"><button type="button" id="gallery-previous" aria-label="Previous photograph">‹</button><img id="gallery-image" alt=""><button type="button" id="gallery-next" aria-label="Next photograph">›</button></div>
    <p id="gallery-count" aria-live="polite"></p>
    <div id="gallery-thumbnails" class="gallery-thumbnails" aria-label="Choose a photograph"></div>
</dialog>
<noscript><div class="noscript-notice">Bookings work without JavaScript. For Galana Farm stays, enter an arrival date matching your visit date. Photo cycling requires JavaScript.</div></noscript>
</body>
</html>
