<?php
// Sunset design: a warm full-screen hero, then a scroll story where the full-screen photograph behind
// the page changes to each destination as its card scrolls past.
$total = count($destinations);
?>
<section class="ss-hero" aria-labelledby="hero-title">
    <img class="ss-hero-photo" src="assets/photos/galana/01-farm.webp" alt="Long rows of green crops stretching to the horizon at Galana Farm" width="1600" height="1065" fetchpriority="high">
    <div class="ss-hero-tint" aria-hidden="true"></div>
    <div class="ss-sun" aria-hidden="true"></div>
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
        <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
        <div class="ss-backdrop-layer ss-tint-<?= e($id) ?><?= $number === 1 ? ' is-active' : '' ?>" data-backdrop="<?= e($id) ?>"><img src="<?= e($destination['variants'][1][1024] ?? $destination['images'][1]) ?>" alt="" width="1600" height="1000" loading="lazy" decoding="async"></div>
        <?php endforeach; ?>
        <ol class="ss-dots"><?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?><li data-dot="<?= e($id) ?>"<?= $number === 1 ? ' class="is-active"' : '' ?>><span>0<?= $number ?></span></li><?php endforeach; ?></ol>
    </div>
    <div class="wrap ss-intro">
        <p class="eyebrow">THE JOURNEY</p>
        <h2>Three places.<br>One <span>warm welcome.</span></h2>
        <p>Keep scrolling and let each place introduce itself.</p>
    </div>
    <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
    <div class="ss-chapter wrap">
        <article class="ss-card" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>">
            <p class="ss-card-count"><span>0<?= $number ?></span> / 0<?= $total ?> · <?= e($destination['category']) ?></p>
            <p class="ss-card-kicker"><?= e($placeKickers[$id]) ?></p>
            <h2><?= e($destination['name']) ?></h2>
            <p class="ss-card-description"><?= e($destination['description']) ?></p>
            <?= galleryPhoto($destination, '(max-width: 700px) calc(100vw - 72px), 480px', false) ?>
            <a class="ss-button" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Plan my visit <?= icon('diagonal') ?></a>
        </article>
    </div>
    <?php endforeach; ?>
    <div class="wrap hero-footnote ss-footnote"><span><?= icon('clock') ?> Every visit starts with a hello.</span><button class="motion-toggle" id="motion-toggle" type="button" aria-pressed="false">Pause photo motion</button><span class="preview-label"><span class="status-dot"></span> Visit times · Africa/Nairobi</span></div>
</section>
