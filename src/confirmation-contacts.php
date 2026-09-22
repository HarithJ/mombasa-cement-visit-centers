<?php
$visitContacts = require __DIR__.'/../config/contacts.php';
?>
<section class="visit-contacts" aria-labelledby="visit-contacts-title">
    <h3 id="visit-contacts-title">Questions about your visit?</h3>
    <p>Contact your destination team for help with your visit.</p>
    <?php foreach ($visitContacts as $destination => $team): ?>
    <div data-contact-destination="<?= e($destination) ?>" <?= ($confirmation['destination'] ?? '') === $destination ? '' : 'hidden' ?>>
        <h4><?= e($team['name']) ?></h4>
        <ul>
            <?php foreach ($team['people'] as $person): ?>
            <li>
                <?php if ($person['name'] !== ''): ?><strong><?= e($person['name']) ?></strong><?php endif; ?>
                <a href="tel:<?= e($person['tel']) ?>"><?= e($person['phone']) ?></a>
                <a href="mailto:<?= e($person['email']) ?>"><?= e($person['email']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</section>
