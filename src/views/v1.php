<?php
declare(strict_types=1);
// Rendered by public/index.php after the shared booking controller.
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function photoVariants(string $path): array {
    $variants = [];
    foreach ([360, 720, 1024] as $width) {
        $variant = dirname($path).'/responsive/'.pathinfo($path, PATHINFO_FILENAME).'-'.$width.'.webp';
        if (is_file(dirname(__DIR__, 2).'/public/'.$variant)) $variants[$width] = $variant;
    }
    return $variants;
}
function photoSrcset(array $variants): string {
    $sources = [];
    foreach ($variants as $width => $path) $sources[] = $path.' '.$width.'w';
    return implode(', ', $sources);
}
$photoSizes = '(max-width: 600px) calc(100vw - 40px), (max-width: 1100px) 32vw, 408px';
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
$mapLinks = require dirname(__DIR__).'/../config/locations.php';
$mapEmbeds = require dirname(__DIR__).'/../config/map-embeds.php';
$destinations = [
    'sahajanand' => ['name' => 'Sahajanand Special School', 'category' => 'EDUCATION & COMMUNITY', 'description' => 'Make time for connection. Meet our special stars and discover the school community at its heart.', 'icon' => 'people', 'tag' => 'Discover how learning happens at this special school'],
    'galana' => ['name' => 'Galana Farm', 'category' => 'NATURE & AGRICULTURE', 'description' => 'Take a different pace. Plan a day at Galana Farm, or make room for an overnight stay.', 'icon' => 'leaf', 'tag' => 'Experience modern agriculture and farm tourism'],
    'feeding' => ['name' => 'Kibarani Feeding center', 'category' => 'CARE & COMMUNITY', 'description' => 'Come closer to the work of care. Arrange a visit to the Kibarani Feeding center.', 'icon' => 'sun', 'tag' => 'See large-scale daily feeding for the community'],
];
foreach ($destinations as $id => &$destination) {
    $files = glob(dirname(__DIR__, 2).'/public/assets/photos/'.$id.'/*.{webp,jpg,jpeg,png}', GLOB_BRACE) ?: [];
    sort($files);
    $destination['images'] = array_map(fn($path) => '/assets/photos/'.$id.'/'.basename($path), $files);
    $destination['placeholder'] = !$files;
    if (!$files) $destination['images'] = ['/assets/galana-placeholder.svg', '/assets/galana-placeholder.svg'];
    if (count($destination['images']) === 1) $destination['images'][] = $destination['images'][0];
    $destination['variants'] = array_map('photoVariants', $destination['images']);
}
unset($destination);
$destinations['sahajanand']['imageAlts'] = ['Aerial view of the entrance and green-roofed buildings at Sahajanand Special School', 'Visitors and pupils gathered in the school courtyard'];
$destinations['feeding']['imageAlts'] = ['Prepared meals laid out on a long table at the Kibarani Feeding center', 'A volunteer handing out meals beneath the Kibarani Feeding center canopy'];
$destinations['galana']['imageAlts'] = ['Rows of crops stretching across the fields at Galana Farm', 'Irrigation equipment over Galana Farm fields at sunset', 'Excavators lined up at Galana Farm', 'Green cultivated fields at Galana Farm', 'Aerial view of a circular irrigated field at Galana Farm', 'Cultivated fields beneath a cloudy sky at Galana Farm', 'Close view of green crop rows at Galana Farm', 'Irrigation machinery at Galana Farm', 'A tractor with farm equipment at Galana Farm', 'Cattle resting at Galana Farm', 'A red tractor at Galana Farm', 'A green tractor sheltered in a farm building at Galana Farm', 'Irrigated fields at sunset at Galana Farm', 'Close-up of an onion flower'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Sahajanand Special School, Galana Farm, and the Kibarani Feeding center. Register your Nyumba day visit.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f7f8f3">
    <title>Visit Nyumba — A visit that means more</title>
    <link rel="icon" href="/assets/nyumba-group.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <noscript><style>.destination-photo > .photo-secondary { display: none; }</style></noscript>
    <script id="booking-state" type="application/json"><?= json_encode($webState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script id="destination-config" type="application/json"><?= json_encode($schedule, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
    <script src="/assets/versions/v1/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#destinations">Skip to destinations</a>
<header class="header">
    <div class="header-inner">
        <div class="brand-lockup" aria-label="Nyumba Group, Nyumba Foundation and Nyumba Agri">
            <img class="group-logo" src="/assets/nyumba-group.svg" alt="Nyumba Group" width="48" height="60">
            <img class="foundation-logo" src="/assets/nyumba-foundation.svg" alt="Nyumba Foundation" width="146" height="58">
            <img class="agri-logo" src="/assets/nyumba-agri.svg" alt="Nyumba Agri" width="99" height="58">
        </div>
    </div>
</header>
<main>
    <section class="visit-hero" aria-labelledby="hero-title">
        <div class="hero-intro wrap">
            <div class="hero-title-block">
                <p class="eyebrow"><span class="eyebrow-line"></span> VISIT NYUMBA</p>
                <h1 id="hero-title">A visit that<br>means <span>more.</span></h1>
            </div>
            <div class="hero-support">
                <p>See the places. Meet the purpose.<br>Discover a different side of Nyumba.</p>
                <a class="text-link" href="#destinations">Find your next visit <?= icon('arrow') ?></a>
            </div>
        </div>
        <div class="destinations wrap" id="destinations" aria-label="Choose a destination">
            <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
            <article class="destination" data-destination="<?= e($id) ?>">
                <div class="photo-stack">
                <div class="destination-photo <?= $destination['placeholder'] ? 'placeholder' : '' ?>" data-images="<?= e(json_encode($destination['images'], JSON_THROW_ON_ERROR)) ?>" data-variants="<?= e(json_encode($destination['variants'], JSON_THROW_ON_ERROR)) ?>" data-alts="<?= e(json_encode($destination['imageAlts'] ?? [], JSON_THROW_ON_ERROR)) ?>" tabindex="0" aria-label="<?= e($destination['name']) ?> image gallery. Photos change automatically; use the photo button for the next view.">
                    <img class="photo-primary" src="<?= e($destination['variants'][0][720] ?? $destination['images'][0]) ?>" srcset="<?= e(photoSrcset($destination['variants'][0])) ?>" sizes="<?= e($photoSizes) ?>" decoding="async" alt="<?= $destination['placeholder'] ? 'Illustrated landscape placeholder; destination photography not yet supplied' : e($destination['imageAlts'][0] ?? $destination['name']) ?>" width="800" height="900" fetchpriority="<?= $number === 1 ? 'high' : 'auto' ?>">
                    <img class="photo-secondary" decoding="async" alt="<?= $destination['placeholder'] ? 'Alternate crop of an illustrated placeholder, not a destination photograph' : e($destination['imageAlts'][1] ?? $destination['name']) ?>" width="800" height="900">
                    <noscript>
                        <img class="photo-secondary" src="<?= e($destination['variants'][1][720] ?? $destination['images'][1]) ?>" srcset="<?= e(photoSrcset($destination['variants'][1])) ?>" sizes="<?= e($photoSizes) ?>" alt="<?= $destination['placeholder'] ? 'Alternate crop of an illustrated placeholder, not a destination photograph' : e($destination['imageAlts'][1] ?? $destination['name']) ?>" width="800" height="900">
                    </noscript>

                    <?php if ($destination['placeholder']): ?><span class="placeholder-label">Illustration · Photo coming soon</span><?php endif; ?>
                    <div class="photo-bottom"><span><?= icon($destination['icon']) ?> <?= e($destination['tag']) ?></span><button class="photo-toggle" type="button" aria-label="Show alternate view of <?= e($destination['name']) ?>" aria-pressed="false"><?= icon('photo') ?><?php if (!$destination['placeholder']): ?><svg class="photo-progress" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" pathLength="100" /></svg><?php endif; ?></button></div>
                </div>
                </div>
                <div class="destination-info">
                    <p class="eyebrow category"><?= e($destination['category']) ?></p>
                    <h2><?= e($destination['name']) ?></h2>
                    <p class="destination-description"><?= e($destination['description']) ?></p>
                    <a class="book-button" data-book="<?= e($id) ?>" href="<?= e($bookingPath) ?>?book=<?= e($id) ?>">Book a visit <?= icon('diagonal') ?></a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="hero-footnote wrap"><span><?= icon('clock') ?> Your visit, thoughtfully planned.</span><span class="preview-label"><span class="status-dot"></span> Visit times · Africa/Nairobi</span></div>
    </section>
    <section class="planning wrap reveal" id="planning" aria-labelledby="planning-title">
        <div class="planning-heading"><p class="eyebrow">A LITTLE PLANNING. A MEANINGFUL VISIT.</p><h2 id="planning-title">Come curious.<br>We’ll keep it simple.</h2><p>From choosing a place to planning your day,<br>your next visit starts here.</p></div>
        <ol class="steps">
            <li><span class="step-number">01</span><div><h3>Find your place</h3><p>Choose the destination you’d like to explore.</p></div></li>
            <li><span class="step-number">02</span><div><h3>Make a plan</h3><p>Pick a date and time, and let us know how many are coming.</p></div></li>
            <li><span class="step-number">03</span><div><h3>You’re ready to visit</h3><p>See your visit details together in one clear confirmation.</p></div></li>
        </ol>
    </section>
    <section class="final-cta reveal" aria-labelledby="cta-title"><div class="wrap final-cta-inner"><div><p class="eyebrow">THERE’S MORE TO DISCOVER</p><h2 id="cta-title">Make time for a visit.</h2></div><a class="button dark-button" href="#destinations">Explore the destinations <?= icon('diagonal') ?></a></div></section>
</main>
<footer class="footer wrap">
    <div class="footer-top"><div class="footer-brand"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="60" height="82"><div><strong>Visit Nyumba</strong><span>Places. People. Purpose.</span></div></div><div class="footer-links"><a href="#destinations">Places to visit</a><a href="#planning">Plan your visit</a><a href="https://www.nyumba.com/nyumba-foundation/" target="_blank" rel="noopener noreferrer">Nyumba Foundation <?= icon('diagonal') ?></a></div></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Nyumba Group</span><span>Visit Nyumba · Day bookings</span><a href="https://www.nyumba.com/" target="_blank" rel="noopener noreferrer">Visit nyumba.com <?= icon('diagonal') ?></a></div>
</footer>
<dialog id="booking-dialog" aria-labelledby="<?= $confirmation ? 'confirmation-title' : 'booking-title' ?>" <?= $openForm || $confirmation ? 'open' : '' ?>>
    <div class="dialog-toolbar"><a role="button" href="<?= e($bookingPath) ?>" class="close-button" aria-label="Close booking form"><?= icon('close') ?></a></div>
    <div class="dialog-shell">
        <aside class="booking-aside"><img id="booking-image" src="/assets/galana-placeholder.svg" alt="" width="500" height="800"><div class="aside-overlay"><p class="eyebrow">YOUR NEXT VISIT</p><h2 id="aside-destination">Sahajanand Special School</h2><p>A little time.<br>A different perspective.</p><span><?= icon('leaf') ?> Visit Nyumba</span></div></aside>
        <div class="booking-main">
            <div id="form-view" <?= $confirmation ? 'hidden' : '' ?>>
                <p class="eyebrow dialog-eyebrow">LET’S PLAN YOUR VISIT</p><h2 id="booking-title">You’re invited.</h2><p class="dialog-subtitle">Tell us a little about your visit.</p>
                <div class="prototype-notice"><span class="status-dot"></span><span><strong>Visit bookings</strong> · Your visit is registered after submission.</span></div>
                <form id="booking-form" method="post" action="<?= e($bookingPath) ?>" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="submissionToken" value="<?= e($token) ?>">
                    <div id="error-summary" class="error-summary" role="alert" tabindex="-1" <?= $errors ? '' : 'hidden' ?>><?= e(implode(' ', $errors)) ?></div>
                    <div class="form-grid">
                        <div class="field"><label for="full-name">Full name <span aria-hidden="true">*</span></label><input id="full-name" name="fullName" value="<?= e($input['fullName'] ?? '') ?>" type="text" maxlength="120" required placeholder="e.g. Alex Mwangi" aria-describedby="full-name-error"><span class="field-error" id="full-name-error"><?= e($errors['fullName'] ?? '') ?></span></div>
                        <div class="field"><label for="phone">Phone number <span aria-hidden="true">*</span></label><input id="phone" name="phone" value="<?= e($input['phone'] ?? '') ?>" type="tel" maxlength="24" required placeholder="e.g. +254 712 345 678" aria-describedby="phone-error"><span class="field-error" id="phone-error"><?= e($errors['phone'] ?? '') ?></span></div>
                        <div class="field"><label for="email">Email address <span aria-hidden="true">*</span></label><input id="email" name="email" value="<?= e($input['email'] ?? '') ?>" type="email" autocomplete="email" maxlength="254" required placeholder="e.g. alex@example.com" aria-describedby="email-error"><span class="field-error" id="email-error"><?= e($errors['email'] ?? '') ?></span></div>
                        <div class="field full"><label for="location">Where would you like to visit? <span aria-hidden="true">*</span></label><select id="location" name="location" required aria-describedby="location-error"><?php foreach ($destinations as $id => $destination): ?><option value="<?= e($id) ?>" data-map-url="<?= e($mapLinks[$id]) ?>" data-map-embed="<?= e($mapEmbeds[$id]) ?>" <?= ($input['location'] ?? 'sahajanand') === $id ? 'selected' : '' ?>><?= e($destination['name']) ?></option><?php endforeach; ?></select><span class="field-error" id="location-error"><?= e($errors['location'] ?? '') ?></span></div>

                        <div class="field"><label for="visit-date">Visit date <span aria-hidden="true">*</span></label><input id="visit-date" name="visitDate" value="<?= e($input['visitDate'] ?? '') ?>" type="date" required aria-describedby="visit-date-error"><span class="field-error" id="visit-date-error"><?= e($errors['visitDate'] ?? '') ?></span></div>
                        <div class="field"><label for="time-slot">Time slot <span aria-hidden="true">*</span></label><select id="time-slot" name="timeSlot" required aria-describedby="time-slot-error schedule-note"><option value="">Select a time</option><?php foreach ($schedule[$input['location'] ?? 'sahajanand']['slots'] ?? [] as $time): ?><option value="<?= e($time) ?>" <?= ($input['timeSlot'] ?? '') === $time ? 'selected' : '' ?>><?= e($time) ?> (Africa/Nairobi)</option><?php endforeach; ?></select><span class="field-error" id="time-slot-error"><?= e($errors['timeSlot'] ?? '') ?></span></div>
                        <p class="schedule-note full" id="schedule-note"><?= icon('clock') ?> Africa/Nairobi · Provisional schedule; times are subject to review.</p>
                        <div class="field full attendee-field"><div><label for="attendees">Number of attendees <span aria-hidden="true">*</span></label><p class="field-hint" id="attendee-hint">Include yourself in the total.</p></div><div><input id="attendees" name="attendees" type="number" min="1" step="1" value="<?= e($input['attendees'] ?? '1') ?>" required aria-describedby="attendee-hint attendees-error"><span class="field-error" id="attendees-error"><?= e($errors['attendees'] ?? '') ?></span></div></div>
                    </div>
                    <div id="overnight-option" <?= ($input['location'] ?? '') === 'galana' ? '' : 'hidden' ?>><label class="checkbox-label"><input id="overnight" aria-describedby="overnight-error" name="overnight" type="checkbox" <?= ($input['overnight'] ?? '') === 'on' ? 'checked' : '' ?>><span class="checkbox-copy"><strong><?= icon('moon') ?> Stay overnight at Galana Farm</strong><span>Add your preferred dates and number of staying guests.</span></span></label></div>
                    <span class="field-error" id="overnight-error"><?= e($errors['overnight'] ?? '') ?></span><div id="overnight-fields" class="overnight-fields" <?= ($input['location'] ?? '') === 'galana' ? '' : 'hidden' ?>><div class="form-grid"><div class="field"><label for="arrival-date">Arrival date <span aria-hidden="true">*</span></label><input id="arrival-date" name="arrivalDate" type="date" value="<?= e($input['arrivalDate'] ?? '') ?>" aria-describedby="arrival-date-error arrival-note"><span class="field-error" id="arrival-date-error"><?= e($errors['arrivalDate'] ?? '') ?></span></div><div class="field"><label for="departure-date">Departure date <span aria-hidden="true">*</span></label><input id="departure-date" name="departureDate" type="date" value="<?= e($input['departureDate'] ?? '') ?>" aria-describedby="departure-date-error"><span class="field-error" id="departure-date-error"><?= e($errors['departureDate'] ?? '') ?></span></div><p class="field-hint full" id="arrival-note">Arrival matches your visit date. Accommodation is a request, not a room allocation.</p><div class="field full"><label for="overnight-guests">Overnight guests <span aria-hidden="true">*</span></label><input id="overnight-guests" name="overnightGuests" type="number" min="1" step="1" value="<?= e($input['overnightGuests'] ?? '1') ?>" aria-describedby="overnight-guests-error"><span class="field-error" id="overnight-guests-error"><?= e($errors['overnightGuests'] ?? '') ?></span></div></div></div>
                    <p class="privacy-note">Your name, email address, phone number and visit details are stored to record your visit. Booking emails may be sent to you and the destination team to coordinate your visit.</p>
                        <div class="destination-location">
                            <details id="inline-map" hidden>
                                <summary>View location on map <span aria-hidden="true">+</span></summary>
                                <div id="inline-map-frame" class="inline-map-frame"></div>
                            </details>
                            <a id="destination-map" href="<?= e($mapLinks[$input['location'] ?? 'sahajanand'] ?? $mapLinks['sahajanand']) ?>" target="_blank" rel="noopener noreferrer"><span><strong id="map-destination-name"><?= e($destinations[$input['location'] ?? 'sahajanand']['name'] ?? $destinations['sahajanand']['name']) ?></strong><span class="map-action">Open in Google Maps · new tab</span></span><?= icon('diagonal') ?></a>
                        </div>
                    <button class="button submit-button" type="submit"><span id="submit-label">Book my visit</span><span class="spinner" hidden></span><?= icon('arrow', 'submit-arrow') ?></button>
                    <p class="required-note">* Required fields. No ID or email address needed.</p>

                </form>
            </div>
            <div id="confirmation-view" <?= $confirmation ? '' : 'hidden' ?>><div class="confirmation-icon"><?= icon('check') ?></div><p class="eyebrow">YOUR VISIT, AT A GLANCE</p><h2 id="confirmation-title" tabindex="-1">Visit registered.</h2><p class="confirmation-subtitle">Your visit has been recorded. Please keep your booking reference.</p><div class="confirmation-preview">Registration acknowledges your visit; it does not imply a capacity check.</div><div class="reference-label">BOOKING REFERENCE<strong><?= e($confirmation['reference'] ?? '') ?></strong></div><dl id="confirmation-details"><?php if ($confirmation): $rows = ['Name' => $confirmation['full_name'], 'Email' => $confirmation['email'] ?? 'Not provided', 'Destination' => $schedule[$confirmation['destination']]['name'], 'Visit date' => $confirmation['visit_date'], 'Time' => $confirmation['booked_time'].' (Africa/Nairobi)', 'Attendees' => (string)$confirmation['attendees'], 'Phone' => '••• ••• '.substr($confirmation['phone'], -3)]; if ($confirmation['overnight']) { $rows['Overnight stay'] = $confirmation['arrival_date'].' – '.$confirmation['departure_date']; $rows['Staying guests'] = (string)$confirmation['overnight_guests']; } foreach ($rows as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; endif; ?></dl><?php require dirname(__DIR__).'/confirmation-contacts.php'; ?><button type="button" class="confirmation-close">Back to exploring</button></div>
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
