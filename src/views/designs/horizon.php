<?php
// Horizon design: a story-style hero that steps through the three destinations, then a journey that
// scrolls sideways through them (a swipeable carousel on phones and for reduced motion).
$total = count($destinations);
?>
<section class="hz-hero" aria-labelledby="hero-title">
    <div class="hz-story" aria-roledescription="carousel" aria-label="The three destinations">
        <div class="hz-bars">
            <?php $number = 0; foreach ($destinations as $id => $destination): ?>
            <button type="button" class="hz-bar<?= $number === 0 ? ' is-active' : '' ?>" data-slide="<?= $number ?>" aria-label="Show <?= e($destination['name']) ?>"><span></span></button>
            <?php $number++; endforeach; ?>
        </div>
        <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
        <article class="hz-slide<?= $number === 1 ? ' is-active' : '' ?>" aria-roledescription="slide" aria-label="<?= $number ?> of <?= $total ?>: <?= e($destination['name']) ?>"<?= $number === 1 ? '' : ' aria-hidden="true"' ?>>
            <img src="<?= e($destination['variants'][0][1024] ?? $destination['images'][0]) ?>" srcset="<?= e(photoSrcset($destination['variants'][0])) ?>" sizes="100vw" alt="<?= e($destination['imageAlts'][0] ?? $destination['name']) ?>" width="1600" height="1000"<?= $number === 1 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
            <div class="hz-slide-copy wrap">
                <p class="hz-slide-meta"><span>0<?= $number ?> / 0<?= $total ?></span> <?= e($destination['category']) ?></p>
                <p class="hz-slide-name"><?= e($destination['name']) ?></p>
                <p class="hz-slide-tag"><?= icon($destination['icon']) ?> <?= e($destination['tag']) ?></p>
                <a class="hz-button hz-button-light" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Let’s plan a visit <?= icon('diagonal') ?></a>
            </div>
        </article>
        <?php endforeach; ?>
        <button type="button" class="hz-step hz-step-prev" aria-label="Previous destination"><?= icon('arrow') ?></button>
        <button type="button" class="hz-step hz-step-next" aria-label="Next destination"><?= icon('arrow') ?></button>
    </div>
    <div class="hz-title wrap">
        <h1 id="hero-title">Visit<br>Nyumba<span class="hero-spark" aria-hidden="true">✳</span></h1>
        <div class="hz-promise"><p class="hz-kicker">GO OUT. GET CURIOUS. · PLACES / PEOPLE / PURPOSE</p><p>A little adventure.<br>A whole new perspective.</p><a class="hz-button" href="#destinations">Explore the places <?= icon('arrow') ?></a></div>
    </div>
</section>
<section class="hz-journey" id="destinations" aria-label="Choose a destination">
    <div class="hz-pin">
        <div class="hz-sticky">
            <div class="hz-track">
                <div class="hz-intro">
                    <p class="eyebrow">PICK YOUR NEXT EXPERIENCE</p>
                    <h2>Good days start with <span>curiosity.</span></h2>
                    <p>Three ways to get closer to the places, people and purpose of Nyumba.</p>
                    <p class="hz-hint" aria-hidden="true"><span></span> Keep scrolling</p>
                </div>
                <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
                <article class="hz-card" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>">
                    <div class="hz-card-photo"><?= galleryPhoto($destination, '(max-width: 900px) 85vw, 45vw', false) ?></div>
                    <div class="hz-card-info">
                        <p class="hz-card-count"><span>0<?= $number ?></span> / 0<?= $total ?></p>
                        <p class="eyebrow"><?= e($destination['category']) ?></p>
                        <p class="hz-card-kicker"><?= e($placeKickers[$id]) ?></p>
                        <h2><?= e($destination['name']) ?></h2>
                        <p class="hz-card-description"><?= e($destination['description']) ?></p>
                        <a class="hz-button" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Let’s plan a visit <?= icon('diagonal') ?></a>
                    </div>
                </article>
                <?php endforeach; ?>
                <div class="hz-end">
                    <p class="hz-end-line"><?= icon('clock') ?> Three destinations. One curious you.</p>
                    <button class="motion-toggle" id="motion-toggle" type="button" aria-pressed="false">Pause photo motion</button>
                    <p class="preview-label"><span class="status-dot"></span> Visit times · Africa/Nairobi</p>
                </div>
            </div>
            <div class="hz-progress" aria-hidden="true"><span></span></div>
        </div>
    </div>
</section>
