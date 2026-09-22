<?php
function feedbackEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$e = 'feedbackEscape';
$assets = $assets ?? '/assets/';
$contacts = require __DIR__.'/../../config/contacts.php';
$destination = $invitation ? $contacts[$invitation['destination']]['name'] : '';
$done = !empty($invitation['submitted_at']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Visit feedback — Nyumba Group</title><link rel="stylesheet" href="<?= $e($assets) ?>feedback.css"><script defer src="<?= $e($assets) ?>feedback.js"></script></head>
<body data-version="<?= $e($uiVersion) ?>" <?= $preview ? 'data-feedback-preview' : '' ?>>
<header class="feedback-header"><img src="<?= $e($assets) ?>nyumba-group.svg" alt="Nyumba Group" width="60" height="82"><span>VISIT NYUMBA<br><strong>A moment to reflect.</strong></span></header>
<main>
<?php if ($preview): ?><aside class="preview-notice">Design preview · Sample visit only. Nothing is sent or saved. <a href="../">Back to the visit preview</a><a href="<?= $invitation['overnight'] ? './' : 'overnight.html' ?>"><?= $invitation['overnight'] ? 'Try a day visit' : 'Try an overnight stay' ?></a></aside><noscript><p>Enable JavaScript to try this sample feedback form. Nothing can be submitted here.</p></noscript><?php endif; ?>
<?php if ($unavailable): ?>
<h1>Feedback unavailable.</h1><p><?= $e($errors['_form'] ?? 'This link is not available. Please use the private link in your feedback email.') ?></p>
<?php elseif ($done): ?>
<p class="eyebrow">YOUR VISIT, YOUR VOICE</p><h1>Thank you for your feedback.</h1><p>Your response has been received. It helps us make future visits better.</p><p class="visit-context"><?= $e($destination) ?></p>
<?php else: ?>
<div id="feedback-form-view"><p class="eyebrow">YOUR VISIT, YOUR VOICE</p><h1>How was your visit?</h1><p class="intro">A few words from you can make the next visit even better.</p>
<p class="visit-context"><strong><?= $e($destination) ?></strong><br><?= $e($invitation['overnight'] ? $invitation['arrival_date'].' – '.$invitation['departure_date'] : $invitation['visit_date']) ?></p>
<?php if ($errors): ?><div class="error-summary" role="alert" tabindex="-1"><strong>Please review your answers.</strong><ul><?php foreach ($errors as $message): ?><li><?= $e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" <?= $preview ? 'data-preview-form' : '' ?> novalidate>
<?php if (!$preview): ?><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><?php endif; ?>
<fieldset><legend>Did you attend? <span class="required">Required</span></legend><div class="attendance-options">
<?php foreach (['attended'=>'I attended','not_attended'=>'I didn’t attend'] as $value=>$label): ?><label><input type="radio" name="attendance" value="<?= $e($value) ?>" <?= ($input['attendance'] ?? '') === $value ? 'checked' : '' ?> required aria-describedby="attendance-error"> <?= $e($label) ?></label><?php endforeach; ?>
</div><p id="attendance-error" class="field-error"><?= $e($errors['attendance'] ?? '') ?></p></fieldset>
<fieldset id="rating-field"><legend>Overall experience</legend><p class="hint">Choose 1–5 stars if you attended.</p><div class="rating-options"><?php for ($star=1;$star<=5;$star++): ?><label><input type="radio" name="rating" aria-label="<?= $star ?> <?= $star === 1 ? 'star' : 'stars' ?>" value="<?= $star ?>" <?= ($input['rating'] ?? '') === (string)$star ? 'checked' : '' ?> aria-describedby="rating-error"><span aria-hidden="true">★</span><span><?= $star ?> <?= $star === 1 ? 'star' : 'stars' ?></span></label><?php endfor; ?></div><p id="rating-error" class="field-error"><?= $e($errors['rating'] ?? '') ?></p></fieldset>
<?php foreach (['enjoyment'=>'What did you enjoy?','improvement'=>'What could we improve?','comments'=>'Anything else to share?'] as $name=>$label): ?>
<div class="feedback-field"><label for="<?= $e($name) ?>"><?= $e($label) ?></label><p class="hint" id="<?= $e($name) ?>-hint">Optional · Up to 2,000 characters</p><textarea id="<?= $e($name) ?>" name="<?= $e($name) ?>" rows="3" maxlength="2000" aria-describedby="<?= $e($name) ?>-hint <?= $e($name) ?>-error" <?= isset($errors[$name]) ? 'aria-invalid="true"' : '' ?>><?= $e($input[$name] ?? '') ?></textarea><p id="<?= $e($name) ?>-error" class="field-error"><?= $e($errors[$name] ?? '') ?></p></div>
<?php endforeach; ?>
<p class="privacy-note"><?= $preview ? 'Use sample answers. This preview does not send or save feedback.' : 'Your feedback is linked to your booking and is not published publicly.' ?></p>
<button type="submit" <?= $preview ? 'disabled' : '' ?>><?= $preview ? 'Preview feedback' : 'Send feedback' ?> <span aria-hidden="true">↗</span></button>
</form></div>
<?php if ($preview): ?><div id="feedback-preview-done" hidden tabindex="-1"><h1>Thank you for trying it.</h1><p>This was a sample response. No feedback was sent or saved.</p><a href="">Try the sample again</a></div><?php endif; ?>
<?php endif; ?>
</main><footer>Nyumba Group · Building better visits, together.</footer></body></html>
