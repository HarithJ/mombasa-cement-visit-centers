<?php
// Departures design: a transit departure board hero and a metro-style line map of the three destinations.
$lines = ['sahajanand' => 'S1', 'galana' => 'G2', 'feeding' => 'K3'];
$flaps = function (string $text): string {
    $words = [];
    foreach (explode(' ', mb_strtoupper($text)) as $word) {
        $cells = '';
        foreach (mb_str_split($word) as $char) $cells .= '<span class="flap" data-char="'.e($char).'">'.e($char).'</span>';
        $words[] = '<span class="flap-word">'.$cells.'</span>';
    }
    return '<span class="flaps" aria-hidden="true">'.implode('', $words).'</span><span class="sr-only">'.e($text).'</span>';
};
?>
<section class="dep-hero" aria-labelledby="hero-title">
    <div class="wrap dep-hero-top">
        <p><span class="dep-live" aria-hidden="true"></span> Nairobi time <time id="dep-clock">—</time></p>
        <p>GO OUT. GET CURIOUS. <span>PLACES / PEOPLE / PURPOSE</span></p>
    </div>
    <div class="wrap dep-intro">
        <h1 id="hero-title">Visit<br>Nyumba<span class="hero-spark" aria-hidden="true">✳</span></h1>
        <div class="dep-promise"><p>A little adventure.<br>A whole new perspective.</p><a class="dep-cta" href="#destinations">Explore the places <?= icon('arrow') ?></a></div>
    </div>
    <div class="wrap">
        <div class="dep-board" role="table" aria-label="Destinations and first visit times, Africa/Nairobi">
            <div class="dep-board-title" aria-hidden="true"><span>DEPARTURES</span><span>WHERE TO?</span></div>
            <div class="dep-row dep-row-head" role="row"><span role="columnheader">First visit</span><span role="columnheader">Destination</span><span role="columnheader">Line</span><span role="columnheader">Experience</span><span role="columnheader"><span class="sr-only">Book</span></span></div>
            <?php foreach ($destinations as $id => $destination): $slots = $schedule[$id]['slots'] ?? []; ?>
            <div class="dep-row" role="row" style="--route: var(--line-<?= e($id) ?>)">
                <span role="cell" class="dep-time"><?= $slots ? $flaps($slots[0]) : '' ?></span>
                <span role="cell" class="dep-dest"><?= $flaps($destination['name']) ?></span>
                <span role="cell"><span class="dep-badge"><?= e($lines[$id]) ?></span></span>
                <span role="cell" class="dep-type"><?= e($destination['category']) ?></span>
                <span role="cell"><a class="dep-board-book" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Book <span class="sr-only"><?= e($destination['name']) ?></span><?= icon('diagonal') ?></a></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="dep-board-note"><?= icon('clock') ?> Visit times · Africa/Nairobi · Provisional schedule; times are subject to review.</p>
    </div>
    <div class="dep-ticker" aria-hidden="true"><div><?php for ($i = 0; $i < 2; $i++): foreach ($destinations as $destination): ?><span><?= icon($destination['icon']) ?> <?= e($destination['tag']) ?></span><?php endforeach; endfor; ?></div></div>
</section>
<section class="dep-lines" id="destinations" aria-label="Choose a destination">
    <div class="wrap dep-lines-head">
        <p class="eyebrow">PICK YOUR NEXT EXPERIENCE</p>
        <h2>Good days start<br>with <span>curiosity.</span></h2>
        <p>Three ways to get closer to the places, people and purpose of Nyumba.</p>
    </div>
    <div class="wrap dep-map">
        <?php $number = 0; foreach ($destinations as $id => $destination): $number++; ?>
        <article class="dep-stop" id="place-<?= e($id) ?>" data-destination="<?= e($id) ?>" style="--route: var(--line-<?= e($id) ?>)">
            <div class="dep-rail" aria-hidden="true"><span class="dep-station"></span></div>
            <div class="dep-stop-info">
                <p class="dep-stop-number"><span>0<?= $number ?></span><span class="dep-badge"><?= e($lines[$id]) ?></span></p>
                <p class="eyebrow"><?= e($destination['category']) ?></p>
                <p class="place-kicker"><?= e($placeKickers[$id]) ?></p>
                <h2><?= e($destination['name']) ?></h2>
                <p class="dep-description"><?= e($destination['description']) ?></p>
                <?php if (!empty($schedule[$id]['slots'])): ?>
                <div class="dep-times"><p>Visit times</p><ul><?php foreach ($schedule[$id]['slots'] as $time): ?><li><?= e(date('g:i a', strtotime($time))) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <a class="dep-book" data-book="<?= e($id) ?>" href="/?book=<?= e($id) ?>">Let’s plan a visit <?= icon('arrow') ?></a>
            </div>
            <div class="dep-stop-photo"><?= galleryPhoto($destination, '(max-width: 900px) calc(100vw - 40px), 55vw', $number === 1) ?></div>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="wrap hero-footnote"><span><?= icon('clock') ?> Three destinations. One curious you.</span><button class="motion-toggle" id="motion-toggle" type="button" aria-pressed="false">Pause photo motion</button><span class="preview-label"><span class="status-dot"></span> Visit times · Africa/Nairobi</span></div>
</section>
