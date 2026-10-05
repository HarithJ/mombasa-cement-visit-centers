<?php $schoolProjects = require (getenv('SCHOOL_PROJECTS_CONFIG') ?: __DIR__.'/../../config/school-projects.php'); ?>
<section class="community-section wrap" id="school-projects" aria-labelledby="school-projects-title">
    <div class="community-heading"><p class="eyebrow">BUILDING FOR EDUCATION</p><h2 id="school-projects-title">Schools we have helped</h2><p><strong>340+ classrooms built and still going</strong>. Safer schools. Brighter futures.</p></div>
    <?php if (!$schoolProjects): ?>
    <?php $schoolImages = array_map(static fn($slug) => '/assets/photos/school-placeholders/'.$slug.'.png', ['classroom-block','school-wall','classroom-interior']); ?>
    <div class="destination-photo school-feature-photo" data-gallery-name="School projects" data-images="<?=e(json_encode($schoolImages))?>" data-alts="<?=e(json_encode(['Illustrative classroom block','Illustrative school wall and gate','Illustrative classroom interior']))?>" tabindex="0" aria-label="School project photo gallery">
        <img class="photo-primary" src="<?=e($schoolImages[0])?>" alt="Illustrative classroom block" width="1536" height="1024" loading="lazy">
        <img class="photo-secondary" alt="Illustrative school project" width="1536" height="1024">
        <div class="photo-bottom"><span><?=icon('leaf')?> Building brighter futures, one school at a time</span><button class="photo-toggle" type="button" aria-label="Open School projects photo gallery"><?=icon('photo')?><svg class="photo-progress" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" pathLength="100" /></svg></button></div>
    </div>
    <?php endif; ?>
    <?php foreach ($schoolProjects as $project): ?>
    <article class="school-project" id="school-project-<?=e($project['id'])?>">
        <div class="school-project-photos"><?php foreach ($project['photos'] as $photo): ?><img src="<?=e($photo['src'])?>" alt="<?=e($photo['alt'])?>" loading="lazy" width="960" height="640"><?php endforeach; ?></div>
        <div><p class="eyebrow"><?=e($project['type'])?> · <?=e($project['location'])?></p><h3><?=e($project['school'])?></h3><p><?=e($project['description'])?></p><?php if (!empty($project['completed'])): ?><p>Completed <?=e($project['completed'])?></p><?php endif; ?><?php if (!empty($project['quantity'])): ?><p><?=e($project['quantity'])?></p><?php endif; ?></div>
    </article>
    <?php endforeach; ?>
    <div class="school-support-details">
        <div><span class="school-support-icon" aria-hidden="true"><?=icon('people')?></span><div><h3>More room to learn</h3><p>Classroom construction that gives schools space for their next chapter.</p></div></div>
        <div><span class="school-support-icon" aria-hidden="true"><?=icon('check')?></span><div><h3>Safer spaces to grow</h3><p>School walls that help create a secure, defined space for learning.</p></div></div>
        <p class="school-request-prompt">Does your school need a classroom or boundary wall?</p>
    </div>
    <a class="community-action school-request-action" href="/school-request"><span>Request school support</span><span class="school-request-arrow" aria-hidden="true">↗</span></a>
</section>
<dialog id="school-request-dialog" class="school-request-dialog community-page" aria-labelledby="school-dialog-title">
    <div class="school-dialog-toolbar"><span id="school-dialog-title">School support <span>/ VISIT NYUMBA</span></span><button type="button" data-school-close aria-label="Close school request form">×</button></div>
    <div class="school-dialog-shell">
        <aside class="school-dialog-aside"><img src="/assets/photos/school-placeholders/classroom-block.png" alt="Illustration of a classroom block"><div><p class="eyebrow">BUILDING FOR EDUCATION</p><h2>Room to learn.<br>Space to grow.</h2><p>Classrooms and school walls.<br>A stronger start for every school.</p><span>Visit Nyumba</span></div></aside>
        <main data-school-content aria-live="polite"></main>
    </div>
</dialog>
<script src="/assets/school-request-dialog.js" defer></script>
