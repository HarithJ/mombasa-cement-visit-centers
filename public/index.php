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
    'galana' => ['name' => 'Galana Farm', 'category' => 'NATURE & AGRICULTURE', 'description' => 'Trade the everyday for open fields. Discover agriculture at Galana Farm, with the option to request an overnight stay.'],
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
$formLocation = isset($destinations[$confirmation['destination'] ?? $input['location'] ?? '']) ? ($confirmation['destination'] ?? $input['location']) : 'galana';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Galana Farm, the Nyumba agricultural project near Malindi. Book your day visit, with an overnight stay on request.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#173f35">
    <title>Visit Nyumba — A visit that means more</title>
    <link rel="icon" href="assets/nyumba-group.svg" type="image/svg+xml">
    <link rel="preload" href="assets/manrope.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/travel.css">
    <link rel="stylesheet" href="assets/hero.css">
    <link rel="stylesheet" href="assets/scatter.css">
    <link rel="stylesheet" href="assets/sections.css">
    <script>document.documentElement.classList.add('js');</script>
    <script id="booking-state" type="application/json"><?= json_encode($webState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script id="destination-config" type="application/json"><?= json_encode($schedule, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
    <script src="assets/app.js" defer></script>
    <script src="assets/hero.js" defer></script>
    <script src="assets/scatter.js" defer></script>
    <script src="assets/sections.js" defer></script>
</head>
<body>
<a class="skip-link" href="#book">Skip to booking</a>
<header class="site-header" data-site-header>
    <div class="site-header-inner">
        <nav class="site-nav" aria-label="Primary"><a href="#day">Your day</a><a href="#getting-there">Getting there</a><a href="#questions">Questions</a><a class="site-nav-cta" href="#book">Plan a visit</a></nav>
    </div>
</header>
<main>
    <section class="hero" id="top" aria-labelledby="hero-title" data-hero>
        <div class="hero-stage" data-hero-stage>
            <img class="hero-still" data-hero-still src="assets/photos/galana/13-onion-flower.webp" srcset="assets/photos/galana/responsive/13-onion-flower-720.webp 720w, assets/photos/galana/responsive/13-onion-flower-1024.webp 1024w, assets/photos/galana/13-onion-flower.webp 1599w" sizes="(max-width: 760px) 200vw, 110vw" alt="An onion flower in full bloom at Galana Farm, with a paper wasp resting on top" width="1599" height="1066" fetchpriority="high">
            <canvas class="hero-canvas" data-hero-canvas aria-hidden="true"></canvas>
            <div class="hero-copy">
                <div class="hero-beat hero-beat-welcome" data-beat="welcome">
                    <p class="hero-kicker"><span class="hero-kicker-dot" aria-hidden="true"></span>Galana Farm · Near Malindi, Kenya</p>
                    <h1 id="hero-title"><span class="hero-title-small">Welcome to</span> <span class="hero-title-big">Galana.</span></h1>
                    <p class="hero-lead">We grow onions, maize and more on 300,000 acres along the Galana River. Come and spend a day with us, or stay the night.</p>
                    <div class="hero-actions"><a class="hero-button" href="#book">Plan your visit <?= icon('arrow') ?></a><a class="hero-link" href="#photos">See the farm</a></div>
                </div>
            </div>
        </div>
    </section>
    <?php
    // A handful of prints dropped onto the table together: the pivot-circle aerial lands on top in the middle, the rest
    // around and under it. x/y is a print's centre and w its width, as percentages of the table; r is how it
    // landed; m* is the phone layout; z is its place in the pile, bottom first.
    $prints = [
        ['14-tractor.webp', 'Ready for the day', 64.3, 57.4, 21.0, -24.7, 76.5, 56.3, 38.9, 0],
        ['10-irrigation-at-dusk.webp', 'Irrigation at dusk', 59.0, 70.0, 21.0, 61.4, 66.7, 66.9, 38.9, 1],
        ['04-farm-sprayer.webp', 'Heading out to spray', 50.2, 75.0, 21.0, 22.5, 50.4, 71.1, 38.9, 2],
        ['08-harvesting-aerial.webp', 'Harvest, straight down', 41.4, 70.4, 21.0, 79.2, 34.1, 67.3, 38.9, 3],
        ['12-maize-fields.webp', 'Maize, row after row', 35.8, 58.1, 21.0, 87.9, 23.7, 56.9, 38.9, 4],
        ['02-circular-fields-aerial.webp', 'A pivot circle under the clouds', 50.0, 50.0, 30.0, -2.0, 50.0, 50.0, 55.5, 10],
        ['05-farmland-aerial.webp', 'Fields and dams from the air', 35.7, 42.6, 21.0, 127.1, 23.5, 43.7, 38.9, 5],
        ['01-farm.webp', 'Rows to the horizon', 41.0, 30.0, 21.0, 116.5, 33.3, 33.1, 38.9, 6],
        ['09-irrigated-plots.webp', 'Irrigated plots', 49.8, 25.0, 21.0, 149.6, 49.6, 28.9, 38.9, 7],
        ['07-green-fields.webp', 'Where the green begins', 58.6, 29.6, 21.0, -174.1, 65.9, 32.7, 38.9, 8],
        ['15-tractor-in-field.webp', 'Working the soil', 64.2, 41.9, 21.0, -122.3, 76.3, 43.1, 38.9, 9],
    ];
    ?>
    <section class="scatter" id="photos" aria-labelledby="scatter-title">
        <h2 class="scatter-title" id="scatter-title">Photos from Galana</h2>
        <div class="scatter-table" data-scatter>
            <?php foreach ($prints as [$file, $caption, $x, $y, $w, $r, $mx, $my, $mw, $z]):
                $hr = -max(-6, min(6, $r * 0.35));
                $path = 'assets/photos/galana/'.$file; $variants = photoVariants($path);
                $size = @getimagesize(__DIR__.'/'.$path) ?: [1600, 900];
                // How wide this print is actually drawn, so phones fetch the small file: the table is the viewport
                // less its margins, up to 1344px. ?>
            <a class="print" href="<?= e($path) ?>" data-print data-full="<?= e($path) ?>" data-large="<?= e($variants[1024] ?? $path) ?>" data-caption="<?= e($caption) ?>" style="--x:<?= $x ?>%;--y:<?= $y ?>%;--w:<?= $w ?>%;--r:<?= $r ?>deg;--hr:<?= round($hr, 2) ?>deg;--mx:<?= $mx ?>%;--my:<?= $my ?>%;--mw:<?= $mw ?>%;--i:<?= $z ?>" aria-label="Enlarge photo: <?= e($caption) ?>">
                <span class="print-paper"><img src="<?= e($variants[720] ?? $path) ?>" srcset="<?= e(photoSrcset($variants)) ?>" sizes="(max-width: 760px) <?= round($mw * 0.94) ?>vw, min(<?= round($w * 0.93) ?>vw, <?= round($w * 13.44) ?>px)" width="<?= $size[0] ?>" height="<?= $size[1] ?>" alt="<?= e($photoDescriptions['galana'][$file] ?? $caption) ?>" loading="lazy" decoding="async" fetchpriority="low" draggable="false"></span>
            </a>
            <?php endforeach; ?>
            <?php // A pencil note beside the pile. Each layout has its own drawing, in that table's own units
            // (1750x1000 wide, 1000x1250 on phones), and the arrow stops just short of the photos. ?>
            <svg class="doodle doodle-wide" viewBox="0 0 1750 1000" aria-hidden="true" focusable="false">
                <defs>
                    <mask id="doodle-wide-words" maskUnits="userSpaceOnUse"><rect class="doodle-wipe" x="20" y="500" width="400" height="180" fill="#fff"/></mask>
                    <mask id="doodle-wide-trail" maskUnits="userSpaceOnUse"><path class="doodle-ink" style="--d:1.1s;--t:.7s" pathLength="1" fill="none" stroke="#fff" stroke-width="16" stroke-linecap="round" d="M250 545 C 220 450, 330 385, 450 440"/></mask>
                </defs>
                <text class="doodle-words" mask="url(#doodle-wide-words)" x="40" y="640" transform="rotate(-4 40 640)" font-size="86">Have a look</text>
                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path class="doodle-ink" style="--d:.75s;--t:.35s" pathLength="1" stroke-width="3.5" d="M48 670 C 140 658, 240 656, 362 648"/>
                    <path class="doodle-ink" style="--d:.95s;--t:.3s" pathLength="1" stroke-width="3.5" d="M80 690 C 170 682, 250 680, 330 676"/>
                    <path mask="url(#doodle-wide-trail)" stroke-width="4" stroke-dasharray="2 14" d="M250 545 C 220 450, 330 385, 450 440"/>
                    <path class="doodle-ink" style="--d:1.75s;--t:.2s" pathLength="1" stroke-width="4" d="M418 442 C 429 441, 440 440, 450 440 C 444 431, 438 422, 432 413"/>
                </g>
            </svg>
            <svg class="doodle doodle-tall" viewBox="0 0 1000 1250" aria-hidden="true" focusable="false">
                <defs>
                    <mask id="doodle-tall-words" maskUnits="userSpaceOnUse"><rect class="doodle-wipe" x="0" y="-30" width="460" height="200" fill="#fff"/></mask>
                    <mask id="doodle-tall-trail" maskUnits="userSpaceOnUse"><path class="doodle-ink" style="--d:1.1s;--t:.7s" pathLength="1" fill="none" stroke="#fff" stroke-width="28" stroke-linecap="round" d="M40 200 C 0 290, 10 420, 70 470"/></mask>
                </defs>
                <text class="doodle-words" mask="url(#doodle-tall-words)" x="24" y="118" transform="rotate(-4 24 118)" font-size="100">Have a look</text>
                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path class="doodle-ink" style="--d:.75s;--t:.35s" pathLength="1" stroke-width="6" d="M30 150 C 120 140, 220 136, 330 128"/>
                    <path class="doodle-ink" style="--d:.95s;--t:.3s" pathLength="1" stroke-width="6" d="M60 172 C 140 165, 220 162, 290 158"/>
                    <path mask="url(#doodle-tall-trail)" stroke-width="7" stroke-dasharray="3 24" d="M40 200 C 0 290, 10 420, 70 470"/>
                    <path class="doodle-ink" style="--d:1.75s;--t:.2s" pathLength="1" stroke-width="7" d="M25 460 C 40 463, 55 467, 70 470 C 64 456, 58 442, 52 428"/>
                </g>
            </svg>
        </div>
        <dialog class="print-viewer" data-print-viewer aria-label="Photo viewer">
            <div class="print-viewer-shade" data-viewer-close></div>
            <figure class="print-viewer-frame" data-viewer-frame>
                <span class="print-paper"><img data-viewer-img alt=""></span>
                <figcaption data-viewer-caption></figcaption>
            </figure>
            <button class="print-viewer-btn print-viewer-close" type="button" data-viewer-close aria-label="Close photo"><?= icon('close') ?></button>
            <button class="print-viewer-btn print-viewer-prev" type="button" data-viewer-step="-1" aria-label="Previous photo"><?= icon('arrow') ?></button>
            <button class="print-viewer-btn print-viewer-next" type="button" data-viewer-step="1" aria-label="Next photo"><?= icon('arrow') ?></button>
        </dialog>
    </section>
    <?php
    // A visit, start to finish. Only what the farm offers: start times from the schedule, lunch, sunset,
    // and the one-night stay that is requested with the booking.
    $day = [
        ['Arrive', 'Meet the team', 'Pick a start time between 9 am and 4 pm when you book. The Galana team meets you when you arrive.', '14-tractor.webp', 'A tractor on the farm, ready for the day'],
        ['Explore', 'Tour the farm', 'See onions, maize and more growing across 300,000 acres along the Galana River, and the pivots that water them.', '06-field-irrigation.webp', 'Irrigation running across a field at Galana'],
        ['Lunch', 'Lunch on the farm', 'Sit down for lunch at Galana, then carry on exploring in the afternoon.', null, null],
        ['Sunset', 'Stay for sunset', 'As the day cools, the light turns gold over the irrigated fields.', '11-irrigation-at-sunset.webp', 'Irrigation over the fields at sunset'],
        ['Overnight', 'Stay the night', 'Tick “Stay overnight” when you book. It’s one night for your whole group, subject to availability, and the team will confirm.', null, null],
    ];
    $visitContacts = require __DIR__.'/../config/contacts.php';
    $team = $visitContacts['galana']['people'];
    ?>
    <section class="day" id="day" aria-labelledby="day-title">
        <div class="day-inner">
            <div class="day-intro">
                <p class="section-kicker"><span aria-hidden="true"></span>A day at Galana</p>
                <h2 class="section-title" id="day-title">Your day at Galana.</h2>
                <p class="section-lead">From the first hello to the last light over the fields, here’s how a visit goes.</p>
            </div>
            <ol class="day-steps" data-day>
                <li class="day-line" aria-hidden="true"><i data-day-fill></i></li>
                <?php foreach ($day as $n => [$tag, $title, $text, $photo, $alt]): ?>
                <li class="day-step<?= $photo ? ' has-photo' : '' ?>" data-day-step>
                    <span class="day-dot" aria-hidden="true"><?= $tag === 'Lunch' ? icon('sun') : ($tag === 'Overnight' ? icon('moon') : '') ?></span>
                    <p class="day-tag"><span><?= str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT) ?></span> <?= e($tag) ?><?php if ($tag === 'Overnight'): ?> <em>Optional</em><?php endif; ?></p>
                    <h3><?= e($title) ?></h3>
                    <p class="day-text"><?= e($text) ?></p>
                    <?php if ($photo): $path = 'assets/photos/galana/'.$photo; $v = photoVariants($path); ?>
                    <img class="day-photo" src="<?= e($v[720] ?? $path) ?>" srcset="<?= e(photoSrcset($v)) ?>" sizes="(max-width: 760px) calc(100vw - 72px), 520px" alt="<?= e($alt) ?>" width="1600" height="900" loading="lazy" decoding="async">
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <section class="getting-there" id="getting-there" aria-labelledby="getting-there-title" data-map>
        <div class="getting-there-inner">
            <div class="getting-there-copy">
                <p class="section-kicker"><span aria-hidden="true"></span>Getting there</p>
                <h2 class="section-title" id="getting-there-title">Inland from Malindi, on the Galana River.</h2>
                <p class="section-lead">The farm is on the Malindi–Sala Gate Road, before Sala Gate.</p>
                <dl class="routes">
                    <div><dt>From Malindi</dt><dd><strong>About 2 hours</strong><span>About 90 km along the Malindi–Sala Gate Road</span></dd></div>
                    <div><dt>From Mombasa</dt><dd><strong>About 4 hours</strong><span>About 200 km, up the coast road to Malindi first</span></dd></div>
                </dl>
                <p class="routes-note">Times are estimates and depend on the road. Not driving? Ask for transport when you book.</p>
                <div class="getting-there-links">
                    <a class="pill-link pill-link-solid" href="<?= e($mapLinks['galana']) ?>" target="_blank" rel="noopener noreferrer">Open in Google Maps <?= icon('diagonal') ?></a>
                    <a class="pill-link" href="<?= e($routeIllustrations['galana']['image']) ?>" target="_blank" rel="noopener noreferrer">See the route drawing <?= icon('diagonal') ?></a>
                </div>
            </div>
            <figure class="map-card">
                <?= file_get_contents(__DIR__.'/../src/views/galana-map.svg') ?>
                <figcaption>Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></figcaption>
            </figure>
        </div>
    </section>
    <section class="questions" id="questions" aria-labelledby="questions-title">
        <div class="questions-inner">
            <div class="questions-intro">
                <p class="section-kicker"><span aria-hidden="true"></span>Before you come</p>
                <h2 class="section-title" id="questions-title">Questions people ask.</h2>
                <p class="section-lead">Anything else? Call or email the Galana team.</p>
                <ul class="team">
                    <?php foreach ($team as $person): ?>
                    <li><strong><?= e($person['name']) ?></strong><a href="tel:<?= e($person['tel']) ?>"><?= e($person['phone']) ?></a><a href="mailto:<?= e($person['email']) ?>"><?= e($person['email']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="faq">
                <?php
                $names = implode(' or ', array_map(fn($p) => $p['name'], $team));
                $faq = [
                    ['Does a visit cost anything?', 'No. Visiting Galana Farm is free.'],
                    ['When can we come?', 'Choose a start time between 9 am and 4 pm when you book. The schedule is provisional, so times may change.'],
                    ['Can children come?', 'Yes. Children are welcome with an adult.'],
                    ['Can we stay the night?', 'Yes, for one night. Tick “Stay overnight at Galana Farm” when you book. The stay is for your whole group and is subject to availability; the team will confirm.'],
                    ['Can you arrange transport?', 'Tick the transport box when you book. Transport is subject to availability, and the team will contact you to confirm arrangements.'],
                    ['What should we bring?', 'It’s a working farm in a warm part of Kenya, so bring a hat, sunscreen, water and comfortable closed shoes.'],
                    ['How do I cancel or change my booking?', 'Call or email '.$names.' on the Galana team, using the details on this page, and have your booking reference ready.'],
                    ['Do I need ID or an email address?', 'No. Your name and a phone number are enough; an email address is optional.'],
                ];
                foreach ($faq as [$q, $a]): ?>
                <details class="faq-item"><summary><?= e($q) ?><span class="faq-sign" aria-hidden="true"></span></summary><div class="faq-answer"><p><?= e($a) ?></p></div></details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <section class="book" id="book" aria-labelledby="<?= $confirmation ? 'confirmation-title' : 'booking-title' ?>">
        <div class="book-inner">
            <div class="book-intro">
                <p class="section-kicker"><span aria-hidden="true"></span>Plan your visit</p>
                <h2 class="section-title" id="booking-title">Book your day at Galana.</h2>
                <p class="section-lead">It takes a minute, and you’ll get a booking reference straight away.</p>
                <ul class="book-facts">
                    <li><?= icon('check') ?> Free to visit</li>
                    <li><?= icon('clock') ?> Start between 9 am and 4 pm</li>
                    <li><?= icon('people') ?> Groups and families welcome</li>
                    <li><?= icon('moon') ?> Transport and overnight stays on request</li>
                </ul>
            </div>
            <div class="book-card">
            <div id="form-view" <?= $confirmation ? 'hidden' : '' ?>>
                <form id="booking-form" method="post" action="/#book" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="submissionToken" value="<?= e($token) ?>">
                    <div id="error-summary" class="error-summary" role="alert" tabindex="-1" <?= $errors ? '' : 'hidden' ?>><?= e(implode(' ', $errors)) ?></div>
                    <div class="form-grid">
                        <div class="field"><label for="full-name">Full name <span aria-hidden="true">*</span></label><input id="full-name" name="fullName" value="<?= e($input['fullName'] ?? '') ?>" type="text" autocomplete="name" maxlength="120" required placeholder="e.g. Alex Mwangi" aria-describedby="full-name-error"><span class="field-error" id="full-name-error"><?= e($errors['fullName'] ?? '') ?></span></div>
                        <div class="field"><label for="organization">Organization (optional)</label><input id="organization" name="organization" value="<?= e($input['organization'] ?? '') ?>" type="text" autocomplete="organization" maxlength="120" placeholder="e.g. Mombasa Primary School" aria-describedby="organization-error"><span class="field-error" id="organization-error"><?= e($errors['organization'] ?? '') ?></span></div>
                        <div class="field"><label for="phone">Phone number <span aria-hidden="true">*</span></label><input id="phone" name="phone" value="<?= e($input['phone'] ?? '') ?>" type="tel" autocomplete="tel" maxlength="24" required placeholder="e.g. +254 712 345 678" aria-describedby="phone-error"><span class="field-error" id="phone-error"><?= e($errors['phone'] ?? '') ?></span></div>
                        <div class="field"><label for="email">Email address (optional)</label><input id="email" name="email" value="<?= e($input['email'] ?? '') ?>" type="email" autocomplete="email" maxlength="254" placeholder="e.g. alex@example.com" aria-describedby="email-error"><span class="field-error" id="email-error"><?= e($errors['email'] ?? '') ?></span></div>
                        <input id="location" name="location" type="hidden" value="<?= e($formLocation) ?>">
                        <span class="field-error" id="location-error" hidden><?= e($errors['location'] ?? '') ?></span>
                        <div class="field"><label for="visit-date">Visit date <span aria-hidden="true">*</span></label><input id="visit-date" name="visitDate" value="<?= e($input['visitDate'] ?? '') ?>" type="date" required aria-describedby="visit-date-error"><span class="field-error" id="visit-date-error"><?= e($errors['visitDate'] ?? '') ?></span></div>
                        <div class="field"><label for="time-slot">Start time <span aria-hidden="true">*</span></label><select id="time-slot" name="timeSlot" required aria-describedby="time-slot-error schedule-note"><option value="">Select a time</option><?php foreach ($schedule[$formLocation]['slots'] ?? [] as $time): ?><option value="<?= e($time) ?>" <?= ($input['timeSlot'] ?? '') === $time ? 'selected' : '' ?>><?= e(date('g:i a', strtotime($time))) ?></option><?php endforeach; ?></select><span class="field-error" id="time-slot-error"><?= e($errors['timeSlot'] ?? '') ?></span></div>
                        <p class="schedule-note full" id="schedule-note"><?= icon('clock') ?> Kenya time · The schedule is provisional and may change.</p>
                        <div class="field full attendee-field"><div><label for="attendees">Number of people <span aria-hidden="true">*</span></label><p class="field-hint" id="attendee-hint">Include yourself in the total.</p></div><div><input id="attendees" name="attendees" type="number" min="1" step="1" value="<?= e($input['attendees'] ?? '1') ?>" required aria-describedby="attendee-hint attendees-error"><span class="field-error" id="attendees-error"><?= e($errors['attendees'] ?? '') ?></span></div></div>
                    </div>
                    <label class="checkbox-label"><input id="transport-requested" name="transportRequested" type="checkbox" aria-labelledby="transport-label" aria-describedby="transport-hint transport-requested-error" <?= ($input['transportRequested'] ?? '') === 'on' ? 'checked' : '' ?>><span class="checkbox-copy"><strong id="transport-label">I would like the company to arrange transport for my visit.</strong><span id="transport-hint">Transport is subject to availability. Our team will contact you to confirm arrangements.</span></span></label>
                    <span class="field-error" id="transport-requested-error"><?= e($errors['transportRequested'] ?? '') ?></span>
                    <div id="overnight-option"><label class="checkbox-label"><input id="overnight" aria-describedby="overnight-error overnight-hint" name="overnight" type="checkbox" <?= ($input['overnight'] ?? '') === 'on' ? 'checked' : '' ?>><span class="checkbox-copy"><strong><?= icon('moon') ?> Stay overnight at Galana Farm</strong><span id="overnight-hint">One night for your whole group, subject to availability.</span></span></label></div>
                    <span class="field-error" id="overnight-error"><?= e($errors['overnight'] ?? '') ?></span>
                    <button class="submit-button" type="submit"><span id="submit-label">Book my visit</span><span class="spinner" hidden></span><?= icon('arrow', 'submit-arrow') ?></button>
                    <p class="privacy-note">Your name, email address, phone number and visit details are stored to record your visit. Booking emails and SMS may be sent to you and the Galana Farm team to coordinate your visit. <a href="/privacy" target="_blank">Privacy notice</a></p>
                    <p class="required-note">* Required. No ID needed, and email is optional.</p>
                </form>
            </div>
            <div id="confirmation-view" <?= $confirmation ? '' : 'hidden' ?>><div class="confirmation-icon"><?= icon('check') ?></div><p class="eyebrow">YOUR VISIT, AT A GLANCE</p><h2 id="confirmation-title" tabindex="-1">Visit registered.</h2><p class="confirmation-subtitle">Your visit has been recorded. Please keep your booking reference.</p><div class="confirmation-preview">Registration acknowledges your visit; it does not imply a capacity check.</div><div class="reference-label">BOOKING REFERENCE<strong><?= e($confirmation['reference'] ?? '') ?></strong></div><dl id="confirmation-details"><?php if ($confirmation): $rows = ['Name' => $confirmation['full_name'], 'Organization' => ($confirmation['organization'] ?? '') !== '' ? $confirmation['organization'] : 'Not provided', 'Email' => $confirmation['email'] ?? 'Not provided', 'Destination' => $schedule[$confirmation['destination']]['name'], 'Visit date' => $confirmation['visit_date'], 'Time' => $confirmation['booked_time'].' (Africa/Nairobi)', 'Attendees' => (string)$confirmation['attendees'], 'Transport' => !empty($confirmation['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested', 'Phone' => '••• ••• '.substr($confirmation['phone'], -3)]; if ($confirmation['overnight']) $rows['Overnight stay'] = 'Requested'; foreach ($rows as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; endif; ?></dl><?php require __DIR__.'/../src/confirmation-contacts.php'; ?><a class="confirmation-close" href="/#book">Book another visit</a></div>
            </div>
        </div>
    </section>
</main>
<footer class="footer wrap">
    <div class="footer-top"><div class="footer-brand"><img src="assets/nyumba-group.svg" alt="Nyumba Group" width="60" height="82"><div><strong>Visit Nyumba</strong><span>Places. People. Purpose.</span></div></div><div class="footer-links"><a href="#book">Plan a visit</a><a href="/privacy">Privacy notice</a><a href="https://www.nyumba.com/nyumba-foundation/" target="_blank" rel="noopener noreferrer">Nyumba Foundation <?= icon('diagonal') ?></a></div></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Nyumba Group</span><span>Visit Nyumba · Day bookings</span><a href="https://www.nyumba.com/" target="_blank" rel="noopener noreferrer">Visit nyumba.com <?= icon('diagonal') ?></a></div>
</footer>
</body>
</html>
