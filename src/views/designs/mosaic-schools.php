<?php
// Mosaic design: school support as a dark panel with a 340+ count-up and two tiles for classrooms and walls.
$tiles = [
    'classroom' => ['Classrooms', 'Bright, solid rooms where every child has a place to learn.', 'Request a classroom'],
    'school_wall' => ['Boundary walls', 'Secure school grounds, so children can learn and play safely.', 'Request a wall'],
];
?>
<section class="mo-schools" id="school-projects" aria-labelledby="school-projects-title">
    <div class="wrap mo-schools-inner">
        <div class="mo-schools-copy">
            <p class="eyebrow">BUILDING FOR EDUCATION</p>
            <h2 id="school-projects-title">
                <span class="mo-counter"><span class="mo-count" data-count="340" aria-hidden="true">340</span><span aria-hidden="true">+</span><span class="sr-only">340+</span></span>
                <span class="mo-counter-label">classrooms built. And counting.</span>
            </h2>
            <?php // Opens the shared request dialog for the tiles below; not shown on its own. ?>
            <a class="mo-schools-cta" href="/school-request" hidden>Request school support</a>
        </div>
        <div class="mo-tiles">
            <?php foreach ($tiles as $type => [$title, $line, $action]): ?>
            <article class="mo-tile mo-tile-<?= e($type) ?>">
                <h3><?= e($title) ?></h3>
                <p><?= e($line) ?></p>
                <a class="mo-tile-link" href="/school-request?support=<?= e($type) ?>" data-school-support="<?= e($type) ?>"><?= e($action) ?> <span aria-hidden="true">↗</span></a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
