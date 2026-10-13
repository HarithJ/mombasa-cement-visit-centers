<?php
// Mosaic design: a drifting wall of every destination photograph, then full-colour panels that stack as you scroll.
$wallPhotos = [];
foreach ($destinations as $destination) {
    foreach ($destination['variants'] as $index => $variant) {
        if (!$destination['placeholder']) $wallPhotos[] = $variant[360] ?? $destination['images'][$index];
    }
}
// Interleave destinations so each column mixes the school, farm and feeding centre.
$wallColumns = [[], [], [], []];
foreach ($wallPhotos as $index => $photo) $wallColumns[$index % 4][] = $photo;
$wallColumns = array_map(fn($column) => array_slice($column, 0, 8), $wallColumns);
$jumpLabels = ['sahajanand' => 'The school', 'galana' => 'The farm', 'feeding' => 'The feeding centre'];
?>
<section class="mo-hero" aria-labelledby="hero-title">
    <div class="mo-wall" aria-hidden="true">
        <?php foreach ($wallColumns as $column): ?>
        <div class="mo-col"><div class="mo-col-track"><?php for ($copy = 0; $copy < 2; $copy++): foreach ($column as $photo): ?><img src="<?= e($photo) ?>" alt="" width="360" height="450" decoding="async"><?php endforeach; endfor; ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="mo-shade" aria-hidden="true"></div>
    <div class="mo-hero-content wrap">
        <p class="mo-tag">GET OUT THERE <span>LEARN / GROW / SHARE</span></p>
        <h1 id="hero-title">Visit<br><span>Nyumba</span><span class="hero-spark" aria-hidden="true">✳</span></h1>
        <p class="mo-promise">Real places. Real people. A day you’ll keep talking about.</p>
        <nav class="mo-jumps" aria-label="Where to?">
            <?php $number = 0; foreach ($jumpLabels as $id => $label): $number++; ?><a class="mo-jump-<?= e($id) ?>" href="#place-<?= e($id) ?>"><span>0<?= $number ?></span> <?= e($label) ?> <?= icon('diagonal') ?></a><?php endforeach; ?>
        </nav>
    </div>
    <a class="mo-badge" href="#destinations" aria-label="Explore the places">
        <svg viewBox="0 0 120 120" aria-hidden="true"><defs><path id="mo-circle" d="M60 60m-46 0a46 46 0 1 1 92 0a46 46 0 1 1-92 0"/></defs><text><textPath href="#mo-circle" textLength="286" lengthAdjust="spacing">EXPLORE THE PLACES ✳ PICK YOUR DAY ✳</textPath></text></svg>
        <?= icon('arrow') ?>
    </a>
</section>
<div class="mo-marquee" aria-hidden="true"><div><?php for ($i = 0; $i < 4; $i++): ?><span>Learn</span><b>✳</b><span>Grow</span><b>✳</b><span>Share</span><b>✳</b><?php endfor; ?></div></div>
<section class="mo-stack" id="destinations" aria-label="Choose a destination">
    <div class="wrap mo-stack-head">
        <p class="eyebrow">WHERE TO NEXT?</p>
        <h2>Three days out.<br>Zero <span>ordinary.</span></h2>
        <p>Pick the one that pulls you in. Or make it all three.</p>
    </div>
    <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
    <article class="mo-panel mo-panel-<?= e($id) ?>" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>">
        <div class="wrap mo-panel-inner">
            <div class="mo-panel-text">
                <p class="mo-num" aria-hidden="true">0<?= $number ?></p>
                <p class="eyebrow"><span>0<?= $number ?> /</span> <?= e($destination['category']) ?></p>
                <p class="mo-kicker"><?= e($placeKickers[$id]) ?></p>
                <h2><?= e($destination['name']) ?></h2>
                <p class="mo-description"><?= e($destination['description']) ?></p>
                <a class="mo-book" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Count me in <span class="mo-book-arrow"><?= icon('diagonal') ?></span></a>
            </div>
            <div class="mo-panel-photo"><?= galleryPhoto($destination, '(max-width: 900px) calc(100vw - 40px), 50vw', $number === 1) ?></div>
        </div>
    </article>
    <?php endforeach; ?>
    <?php // Kept for keyboard and screen-reader users: hidden until focused, and app.js needs it. ?>
    <button class="motion-toggle mo-motion-toggle" id="motion-toggle" type="button" aria-pressed="false">Pause photo motion</button>
</section>
