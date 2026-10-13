<?php
// Sunset design: a warm full-screen hero whose sun sets as you scroll, then a scroll story where the
// full-screen photograph behind the page cycles through each destination's photos as its card passes.
$total = count($destinations);
?>
<section class="ss-hero" aria-labelledby="hero-title">
    <img class="ss-hero-photo" src="assets/photos/galana/01-farm.webp" alt="Long rows of green crops stretching to the horizon at Galana Farm" width="1600" height="1065" fetchpriority="high">
    <div class="ss-hero-tint" aria-hidden="true"></div>
    <div class="ss-sun" aria-hidden="true"></div>
    <div class="ss-hero-dusk" aria-hidden="true"></div>
    <div class="ss-hero-content wrap">
        <p class="ss-pill">A DAY THAT STAYS WITH YOU <span>PLACES / PEOPLE / PURPOSE</span></p>
        <h1 id="hero-title">Visit<br>Nyumba<span class="hero-spark" aria-hidden="true">✳</span></h1>
        <div class="ss-promise">
            <p>Open fields, busy classrooms, shared meals.<br>Come and see what Nyumba is made of.</p>
            <a class="ss-button" href="#destinations">Begin the journey <?= icon('arrow') ?></a>
        </div>
    </div>
    <p class="ss-hero-foot wrap"><span><?= icon('leaf') ?> Out in the fields · Galana Farm</span><span aria-hidden="true">Scroll ↓</span></p>
</section>
<section class="ss-story" id="destinations" aria-label="Choose a destination">
    <div class="ss-backdrop" aria-hidden="true">
        <div class="ss-backdrop-layer ss-backdrop-intro is-active" data-backdrop="intro"><span class="ss-backdrop-sun"></span></div>
        <?php foreach ($destinations as $id => $destination):
            $photos = array_map(fn($index) => $destination['variants'][$index][1024] ?? $destination['images'][$index], array_keys($destination['images'])); ?>
        <div class="ss-backdrop-layer ss-tint-<?= e($id) ?>" data-backdrop="<?= e($id) ?>" data-photos="<?= e(json_encode($photos, JSON_THROW_ON_ERROR)) ?>"><img src="<?= e($photos[1] ?? $photos[0]) ?>" alt="" width="1600" height="1000" loading="lazy" decoding="async"></div>
        <?php endforeach; ?>
        <ol class="ss-dots"><?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?><li data-dot="<?= e($id) ?>"><span>0<?= $number ?></span></li><?php endforeach; ?></ol>
    </div>
    <div class="wrap ss-intro" data-story="intro">
        <p class="eyebrow">THE JOURNEY</p>
        <h2>Three places.<br>One <span>warm welcome.</span></h2>
        <p>Keep scrolling and let each place introduce itself.</p>
    </div>
    <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
    <div class="ss-chapter wrap">
        <article class="ss-card" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>" data-story="<?= e($id) ?>">
            <?php // The booking form shows this destination's photo; app.js reads it from .photo-primary. ?>
            <img class="photo-primary" src="<?= e($destination['variants'][0][720] ?? $destination['images'][0]) ?>" alt="" hidden loading="lazy">
            <p class="ss-card-count"><span>0<?= $number ?></span> / 0<?= $total ?> · <?= e($destination['category']) ?></p>
            <p class="ss-card-kicker"><?= e($placeKickers[$id]) ?></p>
            <h2><?= e($destination['name']) ?></h2>
            <p class="ss-card-description"><?= e($destination['description']) ?></p>
            <p class="ss-card-tag"><?= icon($destination['icon']) ?> <?= e($destination['tag']) ?></p>
            <a class="ss-button" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Plan my visit <?= icon('diagonal') ?></a>
        </article>
    </div>
    <?php endforeach; ?>
    <?php // Kept for keyboard and screen-reader users: hidden until focused, and app.js needs it. ?>
    <button class="motion-toggle ss-motion-toggle" id="motion-toggle" type="button" aria-pressed="false">Pause photo motion</button>
</section>
