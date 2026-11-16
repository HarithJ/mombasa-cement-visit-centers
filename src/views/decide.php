<?php
function decideEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$e = 'decideEscape';
$nairobi = new DateTimeZone('Africa/Nairobi');
$title = $booking ? 'Visit '.$booking['reference'].' · Galana Farm' : 'Visit · Galana Farm';
$visitor = $booking['full_name'] ?? '';
$user = $link['user'] ?? null;
$self = '/decide?token='.rawurlencode($token);
$adminLink = $booking ? '/admin/bookings/'.$booking['id'] : '/admin';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title><?= $e($title) ?></title><link rel="stylesheet" href="/assets/decide.css"><script defer src="/assets/decide.js"></script></head>
<body>
<header class="decide-header"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="44" height="60"><span>GALANA FARM<br><strong>Visit requests</strong></span></header>
<main>
<?php if ($page === 'invalid'): ?>
<h1>This link doesn't work.</h1>
<p>It may be incomplete, or it's no longer valid. <a href="/admin">Sign in to the admin panel</a> to see the booking.</p>
<?php elseif ($page === 'unavailable'): ?>
<h1>Something went wrong.</h1>
<p>Nothing was changed. Please try again shortly.</p>
<?php elseif ($page === 'done'): ?>
<?php if ($done === 'confirmed'): ?>
<h1>Visit confirmed.</h1>
<p>We've told <?= $e($visitor) ?> and the rest of the team. You can close this page.</p>
<?php else: ?>
<h1>Visit rejected.</h1>
<p>We've told <?= $e($visitor) ?> to contact you, and shared your reason with the rest of the team. You can close this page.</p>
<?php endif; ?>
<?php else: ?>
<?php if ($page === 'status'): ?>
<h1><?= $e(BookingStatus::LINK_HEADINGS[$booking['status']]) ?></h1>
<?php if ($booking['status'] !== 'pending' && $latest): $at = (new DateTimeImmutable($latest['decided_at']))->setTimezone($nairobi); ?>
<p><?= $e(($booking['status'] === 'confirmed' ? 'Confirmed' : 'Rejected').' by '.$latest['user_name'].' on '.$at->format('j M').' at '.$at->format('H:i').'.') ?></p>
<?php endif; ?>
<?php if (!$open): ?><p>This link stopped working at 23:59 on the visit date.</p><?php endif; ?>
<p>To change a decision, <a href="<?= $e($adminLink) ?>">sign in to the admin panel</a>.</p>
<?php elseif ($page === 'accept'): ?>
<h1>Accept this visit?</h1>
<p><?= $e($visitor) ?> will be told by email and SMS that their visit is confirmed. The rest of the team will be told you accepted it.</p>
<?php else: ?>
<h1>Reject this visit?</h1>
<?php endif; ?>
<ul class="summary">
<li><strong><?= $e($visitor.(($booking['organization'] ?? '') !== '' ? ' ('.$booking['organization'].')' : '')) ?></strong></li>
<li><?= $e((new DateTimeImmutable($booking['visit_date'], $nairobi))->format('D j M Y').' at '.(new DateTimeImmutable($booking['booked_time'], $nairobi))->format('g:i a').' EAT · '.$booking['attendees'].' '.((int)$booking['attendees'] === 1 ? 'person' : 'people')) ?></li>
<li>Transport: <?= $booking['transport_requested'] ? 'Requested' : 'Not requested' ?></li>
<?php if ($booking['overnight']): ?><li>Overnight stay: Requested</li><?php endif; ?>
<li>Phone <?= $e($booking['phone']) ?><?= $booking['email'] ? ' · Email '.$e($booking['email']) : '' ?></li>
</ul>
<?php if (isset($errors['_form'])): ?><p class="form-error" role="alert"><?= $e($errors['_form']) ?></p><?php endif; ?>
<?php if ($page === 'accept'): ?>
<form method="post" action="<?= $e($self) ?>">
<input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="decision" value="confirmed">
<button type="submit">Yes, accept visit</button>
</form>
<p class="switch"><a href="<?= $e($self.'&action=reject') ?>">Reject instead</a></p>
<?php elseif ($page === 'reject'): ?>
<form method="post" action="<?= $e($self.'&action=reject') ?>" novalidate>
<input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="decision" value="rejected">
<label for="reason">Reason for rejecting (required)</label>
<p class="hint" id="reason-hint">Only the team sees this. The visitor won't see it. They'll be asked to contact you, <?= $e($user['name']) ?>, on <?= $e($user['phone']) ?> or <?= $e($user['email']) ?>.</p>
<textarea id="reason" name="reason" rows="4" required aria-describedby="reason-hint reason-error"<?= isset($errors['reason']) ? ' aria-invalid="true"' : '' ?>><?= $e($reason) ?></textarea>
<p class="counter" aria-live="polite"><?= mb_strlen($reason) ?>/500</p>
<p class="field-error" id="reason-error"><?= $e($errors['reason'] ?? '') ?></p>
<button type="submit">Reject visit</button>
</form>
<p class="switch"><a href="<?= $e($self.'&action=accept') ?>">Accept instead</a></p>
<?php endif; ?>
<?php endif; ?>
</main>
</body></html>
