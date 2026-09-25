<?php if ($path === '/admin/login') { require __DIR__.'/admin-login.php'; return; } ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nyumba · Administration</title>
<link rel="icon" href="/assets/nyumba-group.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/admin/workspace.css"></head><body>
<header><a class="brand" href="/admin"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="42" height="55"><span>NYUMBA GROUP<small>Visit administration</small></span></a><?php if ($authenticated): ?><form action="/admin/logout" method="post"><input type="hidden" name="csrf" value="<?=ae($_SESSION['csrf'])?>"><button>Sign out</button></form><?php endif; ?></header>
<main>
<?php if ($error): ?><p class="error" role="alert"><?=ae($error)?></p><?php endif; ?>
<?php if ($booking): ?>
<a href="<?=ae(adminListUrl($filters,$page))?>">Back to bookings</a>
<?php $photoNames = ['galana'=>'01-farm','sahajanand'=>'01-entrance','feeding'=>'01-meals']; ?>
<div class="detail-banner"><div><p class="eyebrow">Booking details</p><h1><?=ae($booking['full_name'])?></h1><p class="destination-name"><?=ae($destinations[$booking['destination']])?></p><p class="visit-summary"><?=ae(date('j M Y',strtotime($booking['visit_date'])))?> <span>·</span> <?=ae(date('g:i A',strtotime($booking['booked_time'])))?> <span>·</span> <?=ae($booking['attendees'])?> guests</p><p class="reference"><?=ae($booking['reference'])?></p></div><img src="<?=ae('/assets/photos/'.$booking['destination'].'/responsive/'.$photoNames[$booking['destination']].'-720.webp')?>" alt="<?=ae($destinations[$booking['destination']])?>" width="720" height="480"></div><div class="details-grid">
<section class="panel"><h2>Visit details</h2><dl>
<?php $fields = ['Visitor'=>$booking['full_name'], 'Phone'=>$booking['phone'], 'Email'=>$booking['email'] ?: 'Not provided', 'Destination'=>$destinations[$booking['destination']] ?? $booking['destination'], 'Visit date'=>date('j M Y',strtotime($booking['visit_date'])), 'Time'=>date('g:i A',strtotime($booking['booked_time'])), 'Guests'=>$booking['attendees'], 'Transport'=>!empty($booking['transport_requested']) ? 'Requested (subject to availability)' : 'Not requested', 'Status'=>'Automatically confirmed', 'Created'=>adminTimestamp($booking['created_at'])];
if ($booking['overnight']) $fields += ['Arrival'=>date('j M Y',strtotime($booking['arrival_date'])), 'Departure'=>date('j M Y',strtotime($booking['departure_date'])), 'Overnight guests'=>$booking['overnight_guests']];
foreach($fields as $label=>$value): ?><dt><?=ae($label)?></dt><dd><?php if($label==='Phone'): ?><a href="tel:<?=ae(preg_replace('/[^+0-9]/','',$value))?>"><?=ae($value)?></a><?php elseif($label==='Email' && $booking['email']): ?><a href="mailto:<?=ae($value)?>"><?=ae($value)?></a><?php else: ?><?=ae($value)?><?php endif; ?></dd><?php endforeach; ?></dl></section>
<section class="panel"><h2>Visitor feedback</h2><?php if (!$booking['attendance']): ?><div class="empty-feedback"><span class="badge pending">No feedback received</span><p>This visitor has not submitted a response.</p></div><?php else: ?><dl><dt>Self-reported attendance</dt><dd><span class="badge"><?=$booking['attendance']==='attended'?'Attended':'Did not attend'?></span></dd><?php if($booking['attendance']==='attended'): ?><dt>Rating</dt><dd class="rating"><?=ae($booking['rating'])?> / 5</dd><?php endif; ?>
<?php foreach(['enjoyment'=>'What they enjoyed','improvement'=>'Suggested improvements','comments'=>'Comments'] as $key=>$label): ?><dt><?=ae($label)?></dt><dd><?=ae($booking[$key] ?: 'Not provided')?></dd><?php endforeach; ?><dt>Submitted</dt><dd><?=ae(adminTimestamp($booking['submitted_at']))?></dd></dl><?php endif; ?></section></div>
<?php else: ?>
<div class="page-heading"><div><p class="eyebrow">Your workspace</p><h1>Bookings</h1><p class="muted">Visits and feedback across your destinations.</p></div><span class="readonly">Read-only access</span></div>
<?php
$today = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'));
$shortcuts = ['All visits'=>['',''], 'Today'=>[$today->format('Y-m-d'),$today->format('Y-m-d')], 'Next 7 days'=>[$today->format('Y-m-d'),$today->modify('+6 days')->format('Y-m-d')]];
?>
<nav class="shortcuts" aria-label="Visit date shortcuts"><?php foreach($shortcuts as $label=>[$from,$to]): $active=$filters['from']===$from && $filters['to']===$to; ?><a <?= $active?'aria-current="page"':'' ?> href="<?=ae(adminListUrl(array_merge($filters,['from'=>$from,'to'=>$to]),1))?>"><?=ae($label)?></a><?php endforeach; ?></nav>
<form class="filters" method="get" action="/admin"><label>Search reference or name<input name="q" maxlength="200" value="<?=ae($filters['q'])?>"></label><label>Destination<select name="destination" aria-label="Destination"><option value="">All destinations</option><?php foreach($destinations as $key=>$name): ?><option value="<?=ae($key)?>" <?=$filters['destination']===$key?'selected':''?>><?=ae($name)?></option><?php endforeach; ?></select></label><label>From date<input type="date" name="from" value="<?=ae($filters['from'])?>"></label><label>To date<input type="date" name="to" value="<?=ae($filters['to'])?>"></label><button>Apply filters</button><a href="/admin">Clear filters</a></form>
<?php if (!$rows): ?><div class="empty-state"><h2>No bookings found.</h2><p class="muted">Try a different date, destination, or search.</p><a href="/admin">View all bookings</a></div><?php else: ?>
<div class="table" role="region" aria-label="Bookings"><table role="table"><thead><tr><th>Reference</th><th>Visitor</th><th>Destination</th><th>Visit date</th><th>Time</th><th>Guests</th><th>Visitor feedback</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,25) as $row): ?><tr role="row">
<td role="cell" data-label="Reference" class="reference-cell"><a href="<?=ae('/admin/bookings/'.$row['id'].'?'.http_build_query($filters + ['page'=>$page]))?>"><?=ae($row['reference'])?></a></td>
<td role="cell" data-label="Visitor" class="visitor-cell"><?=ae($row['full_name'])?></td>
<td role="cell" data-label="Destination"><span class="badge destination <?=ae($row['destination'])?>"><?=ae($destinations[$row['destination']] ?? $row['destination'])?></span></td>
<td role="cell" data-label="Visit date"><?=ae(date('j M Y', strtotime($row['visit_date'])))?></td>
<td role="cell" data-label="Time"><?=ae(date('g:i A', strtotime($row['booked_time'])))?></td>
<td role="cell" data-label="Guests"><?=ae($row['attendees'])?></td>
<td role="cell" data-label="Visitor feedback"><span class="badge <?=$row['has_feedback']?'':'pending'?>"><?=$row['feedback_attendance']==='attended'?'Attended':($row['feedback_attendance']==='not_attended'?'Did not attend':'No feedback received')?></span></td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<nav aria-label="Pagination"><?php if ($page>1): ?><a href="<?=ae(adminListUrl($filters,$page-1))?>">Previous</a><?php endif; ?><span>Page <?=$page?></span><?php if(count($rows)>25): ?><a href="<?=ae(adminListUrl($filters,$page+1))?>">Next</a><?php endif; ?></nav>
<?php endif; ?></main></body></html>
