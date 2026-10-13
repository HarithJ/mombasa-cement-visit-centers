<?php
// Mosaic design: school support as a bold panel, with three layouts to compare (big number, two tiles, both).
$layouts = ['number' => 'Big number', 'tiles' => 'Two tiles', 'both' => 'Both'];
$tiles = [
    'classroom' => ['Classrooms', 'Bright, solid rooms where every child has a place to learn.', 'Request a classroom'],
    'school_wall' => ['Boundary walls', 'Secure school grounds, so children can learn and play safely.', 'Request a wall'],
];
?>
<section class="mo-schools" id="school-projects" aria-labelledby="school-projects-title" data-layout="both">
    <div class="mo-layout-switch" role="group" aria-label="Preview a layout for this section">
        <span>Layout</span>
        <?php $n = 0; foreach ($layouts as $key => $label): $n++; ?><button type="button" data-layout-option="<?= e($key) ?>" aria-pressed="<?= $key === 'both' ? 'true' : 'false' ?>"><?= $n ?> · <?= e($label) ?></button><?php endforeach; ?>
    </div>
    <div class="wrap mo-schools-inner">
        <div class="mo-schools-copy">
            <p class="eyebrow">BUILDING FOR EDUCATION</p>
            <h2 id="school-projects-title">
                <span class="mo-counter"><span class="mo-count" data-count="340" aria-hidden="true">340</span><span aria-hidden="true">+</span><span class="sr-only">340+</span></span>
                <span class="mo-counter-label">classrooms built. And counting.</span>
                <span class="mo-tiles-title">We build what schools need most.</span>
            </h2>
            <p class="mo-schools-line">We help schools build the classrooms and boundary walls every child needs to learn, and feel safe.</p>
            <a class="mo-book mo-schools-cta school-request-action" href="/school-request">Request school support <span class="mo-book-arrow"><?= icon('diagonal') ?></span></a>
        </div>
        <figure class="mo-schools-photo">
            <img src="/assets/photos/school-placeholders/classroom-interior.png" alt="Illustration of a bright classroom with wooden desks and a blackboard" width="1536" height="1024" loading="lazy" decoding="async">
            <figcaption>Illustrative image</figcaption>
        </figure>
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
