<?php
$shownPlaces = $filters['destination'] !== '' ? array_intersect_key($filterPlaces, [$filters['destination'] => true]) : $filterPlaces;
$csvQuery = http_build_query(array_filter($filters, fn($value) => $value !== ''));
?>
<div class="page-heading"><div><p class="eyebrow">Visits opening soon</p><h1>Interest</h1><p class="muted">People who would like to visit places not yet open for booking.</p></div><span class="readonly">Read-only access</span></div>
<?php
$today = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Africa/Nairobi'));
$shortcuts = ['All registrations'=>['',''], 'Last 7 days'=>[$today->modify('-6 days')->format('Y-m-d'),$today->format('Y-m-d')], 'Last 30 days'=>[$today->modify('-29 days')->format('Y-m-d'),$today->format('Y-m-d')]];
?>
<nav class="shortcuts" aria-label="Registration date shortcuts"><?php foreach($shortcuts as $label=>[$from,$to]): $active=$filters['from']===$from && $filters['to']===$to; ?><a <?= $active?'aria-current="page"':'' ?> href="<?=ae(adminListUrl(array_merge($filters,['from'=>$from,'to'=>$to]),1,'/admin/interest'))?>"><?=ae($label)?></a><?php endforeach; ?></nav>
<form class="filters" method="get" action="/admin/interest"><label>Search name, phone or organization<input name="q" maxlength="200" value="<?=ae($filters['q'])?>"></label><label>Destination<select name="destination" aria-label="Destination"><option value="">All destinations</option><?php foreach($filterPlaces as $key=>$name): ?><option value="<?=ae($key)?>" <?=$filters['destination']===$key?'selected':''?>><?=ae($name)?></option><?php endforeach; ?></select></label><label>Registered from<input type="date" name="from" value="<?=ae($filters['from'])?>"></label><label>Registered to<input type="date" name="to" value="<?=ae($filters['to'])?>"></label><button>Apply filters</button><a href="/admin/interest">Clear filters</a></form>
<?php if (!$error): ?>
<section class="summary-cards" aria-label="Interest by destination"><?php foreach ($shownPlaces as $key => $name): $total = $interestTotals[$key] ?? []; ?>
<div class="summary-card"><span class="badge destination <?=ae($key)?>"><?=ae($name)?></span><div class="summary-stats"><p><?=adminCount((int)($total['registrations'] ?? 0), 'registration', 'registrations')?></p><p><?=adminCount((int)($total['attendees'] ?? 0), 'person', 'people')?></p></div><p class="summary-note"><?=isset($total['top_month']) ? 'Most wanted: '.ae(DateTimeImmutable::createFromFormat('!Y-m', $total['top_month'])->format('M Y')) : 'No preferred dates yet'?></p></div>
<?php endforeach; ?></section>
<?php endif; ?>
<?php if (!$rows): ?><div class="empty-state"><h2>No registrations found.</h2><p class="muted">Try a different date, destination, or search.</p><a href="/admin/interest">View all registrations</a></div><?php else: ?>
<p class="csv-link"><a href="<?=ae('/admin/interest.csv'.($csvQuery !== '' ? '?'.$csvQuery : ''))?>" download>Download CSV</a> <span class="muted">· Includes every registration matching these filters.</span></p>
<div class="table" role="region" aria-label="Interest registrations"><table role="table"><thead><tr><th>Registered</th><th>Visitor</th><th>Organization</th><th>Phone</th><th>Email</th><th>Destination</th><th>Preferred date</th><th>Guests</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,25) as $row): ?><tr role="row">
<td role="cell" data-label="Registered"><?=ae(adminTimestamp($row['created_at']))?></td>
<td role="cell" data-label="Visitor" class="visitor-cell"><?=ae($row['full_name'])?></td>
<td role="cell" data-label="Organization"><?=ae($row['organization'] ?: 'Not provided')?></td>
<td role="cell" data-label="Phone"><a href="tel:<?=ae(preg_replace('/[^+0-9]/','',$row['phone']))?>"><?=ae($row['phone'])?></a></td>
<td role="cell" data-label="Email"><?php if ($row['email']): ?><a href="mailto:<?=ae($row['email'])?>"><?=ae($row['email'])?></a><?php else: ?>Not provided<?php endif; ?></td>
<td role="cell" data-label="Destination"><span class="badge destination <?=ae($row['destination'])?>"><?=ae($destinations[$row['destination']] ?? $row['destination'])?></span></td>
<td role="cell" data-label="Preferred date"><?=ae(date('j M Y', strtotime($row['preferred_date'])))?></td>
<td role="cell" data-label="Guests"><?=ae($row['attendees'])?></td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<nav aria-label="Pagination"><?php if ($page>1): ?><a href="<?=ae(adminListUrl($filters,$page-1,'/admin/interest'))?>">Previous</a><?php endif; ?><span>Page <?=$page?></span><?php if(count($rows)>25): ?><a href="<?=ae(adminListUrl($filters,$page+1,'/admin/interest'))?>">Next</a><?php endif; ?></nav>
