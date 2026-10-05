<?php $schoolProjects = require (getenv('SCHOOL_PROJECTS_CONFIG') ?: __DIR__.'/../../config/school-projects.php'); ?>
<section class="community-section wrap" id="school-projects" aria-labelledby="school-projects-title">
    <div class="community-heading"><p class="eyebrow">BUILDING FOR EDUCATION</p><h2 id="school-projects-title">Schools we have helped</h2><p>Classrooms and school walls. More space to learn, and safer places to grow.</p></div>
    <?php if (!$schoolProjects): ?><p class="community-note">Project stories will be shared here as photographs and details become available.</p><?php endif; ?>
    <?php foreach ($schoolProjects as $project): ?>
    <article class="school-project" id="school-project-<?=e($project['id'])?>">
        <div class="school-project-photos"><?php foreach ($project['photos'] as $photo): ?><img src="<?=e($photo['src'])?>" alt="<?=e($photo['alt'])?>" loading="lazy" width="960" height="640"><?php endforeach; ?></div>
        <div><p class="eyebrow"><?=e($project['type'])?> · <?=e($project['location'])?></p><h3><?=e($project['school'])?></h3><p><?=e($project['description'])?></p><?php if (!empty($project['completed'])): ?><p>Completed <?=e($project['completed'])?></p><?php endif; ?><?php if (!empty($project['quantity'])): ?><p><?=e($project['quantity'])?></p><?php endif; ?></div>
    </article>
    <?php endforeach; ?>
    <a class="community-action" href="/school-request">Request a classroom or school wall ↗</a>
</section>
