<?php if ($authView) { require __DIR__.'/admin-login.php'; return; } ?>
<?php require __DIR__.'/admin-header.php'; ?>
<main>
<?php if ($error): ?><p class="error" role="alert"><?=ae($error)?></p><?php endif; ?>
<?php if ($notice ?? ''): ?><p class="notice" role="status"><?=ae($notice)?></p><?php endif; ?>
<?php if ($alert ?? ''): ?><p class="error" role="alert"><?=ae($alert)?></p><?php endif; ?>
<?php if ($booking): ?>
<a href="<?=ae(adminListUrl($filters,$page))?>">Back to bookings</a>
<?php $photoNames = ['galana'=>'01-farm']; $place = adminPlace($destinations, $booking['destination']); ?>
<div class="detail-banner"><div><p class="eyebrow">Booking details</p><h1><?=ae($booking['full_name'])?></h1><p class="destination-name"><?=ae($place)?></p><p class="visit-summary"><?=ae(date('j M Y',strtotime($booking['visit_date'])))?> <span>·</span> <?=ae(date('g:i A',strtotime($booking['booked_time'])))?> <span>·</span> <?=ae($booking['attendees'])?> guests</p><p class="reference"><?=ae($booking['reference'])?></p></div><?php if (isset($photoNames[$booking['destination']])): ?><img src="<?=ae('/assets/photos/'.$booking['destination'].'/responsive/'.$photoNames[$booking['destination']].'-720.webp')?>" alt="<?=ae($place)?>" width="720" height="480"><?php endif; ?></div>
<?php
$status = $booking['status']; $latest = $decisionHistory[0] ?? null;
$detailUrl = adminListUrl($filters, $page, '/admin/bookings/'.$booking['id']);
$askUrl = fn(string $decision) => adminListUrl($filters + ['decide' => $decision], $page, '/admin/bookings/'.$booking['id']);
?>
<section class="panel decision"><h2>Decision</h2>
<?php if ($status === 'pending'): ?><p>This visit is awaiting confirmation.</p>
<?php elseif (!$latest): // Confirmed before visit approval; the history says so. ?>
<?php else: [$day, $time] = adminDecidedAt($latest['decided_at']); ?><p><?=ae(($status === 'confirmed' ? 'Confirmed' : 'Rejected').' by '.$latest['user_name'].' on '.$day.' at '.$time.'.')?></p><?php if ($status === 'rejected'): ?><p>Reason: <?=ae($latest['reason'])?></p><?php endif; ?>
<?php endif; ?>
<?php if (!BookingDecisions::decidable($booking, $now)): ?><p class="muted">Decisions can no longer be changed, because the visit date has passed.</p>
<?php elseif ($decisionForm): ?>
<form class="decision-form" method="post" action="<?=ae(adminListUrl($filters, $page, '/admin/bookings/'.$booking['id'].'/decision'))?>"><input type="hidden" name="csrf" value="<?=ae($_SESSION['csrf'])?>"><input type="hidden" name="decision" value="<?=ae($decisionForm)?>"><input type="hidden" name="from" value="<?=ae($status)?>">
<?php if ($decisionForm === 'rejected'): ?><label for="reason">Reason for rejecting (required)</label><p class="hint" id="reason-hint">Only the team sees this. The visitor will be asked to contact you.</p><?php if ($reasonError): ?><p class="error" id="reason-error"><?=ae($reasonError)?></p><?php endif; ?><textarea id="reason" name="reason" rows="3" required aria-describedby="reason-hint<?=$reasonError ? ' reason-error' : ''?>"><?=ae($reason)?></textarea><?php endif; ?>
<p class="decision-prompt"><?=$decisionForm === 'confirmed' ? 'Confirm' : 'Reject'?> this visit? The visitor and the rest of the team will be told.</p>
<div class="decision-actions"><button><?=$decisionForm === 'confirmed' ? 'Confirm visit' : 'Reject visit'?></button><a href="<?=ae($detailUrl)?>">Cancel</a></div></form>
<?php else: ?><div class="decision-actions"><?php if ($status === 'pending'): ?><a class="button-link" href="<?=ae($askUrl('confirmed'))?>">Confirm visit</a><a class="button-link secondary" href="<?=ae($askUrl('rejected'))?>">Reject visit</a><?php else: $other = $status === 'confirmed' ? 'rejected' : 'confirmed'; ?><a class="button-link secondary" href="<?=ae($askUrl($other))?>">Change to <?=ae($other)?></a><?php endif; ?></div>
<?php endif; ?>
<?php if ($decisionHistory || $status !== 'pending'): ?><h3>History</h3><ul class="history"><?php if (!$decisionHistory): ?><li>Confirmed before visit approval was introduced.</li><?php endif; ?>
<?php foreach ($decisionHistory as $row): [$day, $time] = adminDecidedAt($row['decided_at']);
    $what = $row['previous_status'] === 'pending' ? ($row['decision'] === 'confirmed' ? 'Confirmed' : 'Rejected') : 'Changed to '.$row['decision']; ?>
<li><?=ae($day.', '.$time.': '.$what.' by '.$row['user_name'].($row['decision'] === 'rejected' ? '. Reason: '.$row['reason'] : ''))?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<div class="details-grid">
<section class="panel"><h2>Visit details</h2><dl>
<?php $fields = ['Visitor'=>$booking['full_name'], 'Organization'=>$booking['organization'] ?: 'Not provided', 'Phone'=>$booking['phone'], 'Email'=>$booking['email'] ?: 'Not provided', 'Destination'=>$place, 'Visit date'=>date('j M Y',strtotime($booking['visit_date'])), 'Time'=>date('g:i A',strtotime($booking['booked_time'])), 'Guests'=>$booking['attendees'], 'Transport'=>!empty($booking['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested', 'Status'=>BookingStatus::LABELS[$booking['status']] ?? $booking['status'], 'Created'=>adminTimestamp($booking['created_at'])];
if ($booking['overnight']) $fields['Overnight stay'] = 'Requested';
foreach($fields as $label=>$value): ?><dt><?=ae($label)?></dt><dd><?php if($label==='Phone'): ?><a href="tel:<?=ae(preg_replace('/[^+0-9]/','',$value))?>"><?=ae($value)?></a><?php elseif($label==='Email' && $booking['email']): ?><a href="mailto:<?=ae($value)?>"><?=ae($value)?></a><?php else: ?><?=ae($value)?><?php endif; ?></dd><?php endforeach; ?></dl></section>
<section class="panel"><h2>Visitor feedback</h2><?php if (!$booking['attendance']): ?><div class="empty-feedback"><span class="badge pending">No feedback received</span><p>This visitor has not submitted a response.</p></div><?php else: ?><dl><dt>Self-reported attendance</dt><dd><span class="badge"><?=$booking['attendance']==='attended'?'Attended':'Did not attend'?></span></dd><?php if($booking['attendance']==='attended'): ?><dt>Rating</dt><dd class="rating"><?=ae($booking['rating'])?> / 5</dd><?php endif; ?>
<?php foreach(['enjoyment'=>'What they enjoyed','improvement'=>'Suggested improvements','comments'=>'Comments'] as $key=>$label): ?><dt><?=ae($label)?></dt><dd><?=ae($booking[$key] ?: 'Not provided')?></dd><?php endforeach; ?><dt>Submitted</dt><dd><?=ae(adminTimestamp($booking['submitted_at']))?></dd></dl><?php endif; ?></section></div>
<?php else: ?>
<div class="page-heading"><div><p class="eyebrow">Your workspace</p><h1>Bookings</h1><p class="muted">Visits and feedback across your destinations.</p></div></div>
<?php
$today = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'));
$shortcuts = ['All visits'=>['',''], 'Today'=>[$today->format('Y-m-d'),$today->format('Y-m-d')], 'Next 7 days'=>[$today->format('Y-m-d'),$today->modify('+6 days')->format('Y-m-d')]];
?>
<nav class="shortcuts" aria-label="Visit date shortcuts"><?php foreach($shortcuts as $label=>[$from,$to]): $active=$filters['from']===$from && $filters['to']===$to; ?><a <?= $active?'aria-current="page"':'' ?> href="<?=ae(adminListUrl(array_merge($filters,['from'=>$from,'to'=>$to]),1))?>"><?=ae($label)?></a><?php endforeach; ?></nav>
<form class="filters" method="get" action="/admin"><label>Search reference or name<input name="q" maxlength="200" value="<?=ae($filters['q'])?>"></label><label>Destination<select name="destination" aria-label="Destination"><option value="">All destinations</option><?php foreach($destinations as $key=>$name): ?><option value="<?=ae($key)?>" <?=$filters['destination']===$key?'selected':''?>><?=ae($name)?></option><?php endforeach; ?></select></label><label>Status<select name="status" aria-label="Status"><option value="">All</option><?php foreach(BookingStatus::LABELS as $key=>$label): ?><option value="<?=ae($key)?>" <?=$filters['status']===$key?'selected':''?>><?=ae($label)?></option><?php endforeach; ?></select></label><label>Visit date from<input type="date" name="from" value="<?=ae($filters['from'])?>"></label><label>Visit date to<input type="date" name="to" value="<?=ae($filters['to'])?>"></label><button>Apply filters</button><a href="/admin">Clear filters</a></form>
<?php $shownPlaces = $filters['destination'] !== '' ? array_intersect_key($destinations, [$filters['destination'] => true]) : $destinations; ?>
<?php if (!$error && $shownPlaces): ?>
<section class="summary-cards" aria-label="Bookings by destination"><?php foreach ($shownPlaces as $key => $name):
    $total = $bookingTotals[$key] ?? []; $upcoming = (int)($total['upcoming'] ?? 0);
    $requests = array_filter([(int)($total['overnight'] ?? 0) ? ($total['overnight'] == 1 ? '1 overnight stay' : $total['overnight'].' overnight stays') : '', (int)($total['transport'] ?? 0) ? ($total['transport'] == 1 ? '1 transport request' : $total['transport'].' transport requests') : '']);
    [$weekFrom, $weekTo] = $shortcuts['Next 7 days']; ?>
<div class="summary-card"><span class="badge destination <?=ae($key)?>"><?=ae($name)?></span><div class="summary-stats"><p><?=adminCount((int)($total['visits'] ?? 0), 'visit', 'visits')?></p><p><?=adminCount((int)($total['guests'] ?? 0), 'guest', 'guests')?></p></div></div>
<?php $upcomingBody = '<span class="eyebrow">Next 7 days · '.ae($name).'</span><span class="upcoming-count">'.adminCount($upcoming, 'upcoming', 'upcoming').'</span><span class="upcoming-requests">'.($requests ? 'To arrange: '.ae(implode(' · ', $requests)) : 'Nothing to arrange').'</span>'; ?>
<?php if ($upcoming): ?><a class="summary-card upcoming urgent" href="<?=ae(adminListUrl(['q'=>'', 'destination'=>$key, 'from'=>$weekFrom, 'to'=>$weekTo], 1))?>" aria-label="<?=ae($upcoming.' upcoming at '.$name.' in the next 7 days. Show these visits')?>"><?=$upcomingBody?><span class="upcoming-arrow" aria-hidden="true">→</span></a><?php else: ?><div class="summary-card upcoming"><?=$upcomingBody?></div><?php endif; ?>
<?php endforeach; ?></section>
<?php endif; ?>
<?php if (!$rows): ?><div class="empty-state"><h2>No bookings found.</h2><p class="muted">Try a different date, destination, or search.</p><a href="/admin">View all bookings</a></div><?php else: $csvQuery = http_build_query(array_filter($filters, fn($value) => $value !== '')); ?>
<p class="csv-link"><a href="<?=ae('/admin/bookings.csv'.($csvQuery !== '' ? '?'.$csvQuery : ''))?>" download>Download CSV</a> <span class="muted">· Includes every booking matching these filters.</span></p>
<div class="table" role="region" aria-label="Bookings"><table role="table"><thead><tr><th>Reference</th><th>Visitor</th><th>Destination</th><th>Visit date</th><th>Time</th><th>Guests</th><th>Status</th><th>Visitor feedback</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,25) as $row): ?><tr role="row">
<td role="cell" data-label="Reference" class="reference-cell"><a href="<?=ae('/admin/bookings/'.$row['id'].'?'.http_build_query($filters + ['page'=>$page]))?>"><?=ae($row['reference'])?></a></td>
<td role="cell" data-label="Visitor" class="visitor-cell"><?=ae($row['full_name'])?></td>
<td role="cell" data-label="Destination"><span class="badge destination <?=ae(isset($destinations[$row['destination']]) ? $row['destination'] : 'retired')?>"><?=ae(adminPlace($destinations, $row['destination']))?></span></td>
<td role="cell" data-label="Visit date"><?=ae(date('j M Y', strtotime($row['visit_date'])))?></td>
<td role="cell" data-label="Time"><?=ae(date('g:i A', strtotime($row['booked_time'])))?></td>
<td role="cell" data-label="Guests"><?=ae($row['attendees'])?></td>
<td role="cell" data-label="Status"><span class="badge status <?=ae($row['status'])?>"><?=ae(BookingStatus::LABELS[$row['status']] ?? $row['status'])?></span></td>
<td role="cell" data-label="Visitor feedback"><span class="badge <?=$row['has_feedback']?'':'pending'?>"><?=$row['feedback_attendance']==='attended'?'Attended':($row['feedback_attendance']==='not_attended'?'Did not attend':'No feedback received')?></span></td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<nav aria-label="Pagination"><?php if ($page>1): ?><a href="<?=ae(adminListUrl($filters,$page-1))?>">Previous</a><?php endif; ?><span>Page <?=$page?></span><?php if(count($rows)>25): ?><a href="<?=ae(adminListUrl($filters,$page+1))?>">Next</a><?php endif; ?></nav>
<?php endif; ?></main></body></html>
