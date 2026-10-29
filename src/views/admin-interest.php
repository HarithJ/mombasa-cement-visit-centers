<?php
$shownPlaces = $filters['destination'] !== '' ? array_intersect_key($filterPlaces, [$filters['destination'] => true]) : $filterPlaces;
$csvQuery = http_build_query(array_filter($filters, fn($value) => $value !== ''));
?>
<div class="page-heading"><div><p class="eyebrow">Visits opening soon</p><h1>Interest</h1><p class="muted">People who would like to visit places not yet open for booking.</p></div><span class="readonly">Read-only access</span></div>
<?php if (!$error): ?>
<section class="interest-cards" aria-label="Interest by place"><?php foreach ($shownPlaces as $key => $name): $total = $interestTotals[$key] ?? ['registrations' => 0, 'attendees' => 0]; ?>
<div class="interest-card"><span class="badge destination <?=ae($key)?>"><?=ae($name)?></span><p><strong><?=ae((int)$total['registrations'])?></strong> <?=(int)$total['registrations'] === 1 ? 'registration' : 'registrations'?></p><p><strong><?=ae((int)$total['attendees'])?></strong> <?=(int)$total['attendees'] === 1 ? 'person' : 'people'?></p></div>
<?php endforeach; ?></section>
<section class="interest-months"><h2>Registrations by month</h2><div class="table" role="region" aria-label="Registrations by month"><table><thead><tr><th>Month registered</th><?php foreach ($shownPlaces as $name): ?><th><?=ae($name)?></th><?php endforeach; ?><?php if (count($shownPlaces) > 1): ?><th>Total</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($interestMonths as $month): $counts = $interestByMonth[$month->format('Y-m')] ?? []; ?><tr><td><?=ae($month->format('F Y'))?></td><?php foreach ($shownPlaces as $key => $name): ?><td><?=ae($counts[$key] ?? 0)?></td><?php endforeach; ?><?php if (count($shownPlaces) > 1): ?><td><?=ae(array_sum(array_intersect_key($counts, $shownPlaces)))?></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<h2>People who registered</h2>
<form class="filters" method="get" action="/admin/interest"><label>Search name, phone or organization<input name="q" maxlength="200" value="<?=ae($filters['q'])?>"></label><label>Place<select name="destination" aria-label="Place"><option value="">All places</option><?php foreach($filterPlaces as $key=>$name): ?><option value="<?=ae($key)?>" <?=$filters['destination']===$key?'selected':''?>><?=ae($name)?></option><?php endforeach; ?></select></label><label>Registered from<input type="date" name="from" value="<?=ae($filters['from'])?>"></label><label>Registered to<input type="date" name="to" value="<?=ae($filters['to'])?>"></label><button>Apply filters</button><a href="/admin/interest">Clear filters</a></form>
<?php if (!$rows): ?><div class="empty-state"><h2>No registrations found.</h2><p class="muted">Try a different place, date, or search.</p><a href="/admin/interest">View all registrations</a></div><?php else: ?>
<p class="csv-link"><a href="<?=ae('/admin/interest.csv'.($csvQuery !== '' ? '?'.$csvQuery : ''))?>" download>Download CSV</a> <span class="muted">· Includes every registration matching these filters.</span></p>
<div class="table" role="region" aria-label="Interest registrations"><table role="table"><thead><tr><th>Registered</th><th>Name</th><th>Organization</th><th>Phone</th><th>Email</th><th>Place</th><th>Preferred date</th><th>Attendees</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,25) as $row): ?><tr role="row">
<td role="cell" data-label="Registered"><?=ae(adminTimestamp($row['created_at']))?></td>
<td role="cell" data-label="Name" class="visitor-cell"><?=ae($row['full_name'])?></td>
<td role="cell" data-label="Organization"><?=ae($row['organization'] ?: 'Not provided')?></td>
<td role="cell" data-label="Phone"><a href="tel:<?=ae(preg_replace('/[^+0-9]/','',$row['phone']))?>"><?=ae($row['phone'])?></a></td>
<td role="cell" data-label="Email"><?php if ($row['email']): ?><a href="mailto:<?=ae($row['email'])?>"><?=ae($row['email'])?></a><?php else: ?>Not provided<?php endif; ?></td>
<td role="cell" data-label="Place"><span class="badge destination <?=ae($row['destination'])?>"><?=ae($destinations[$row['destination']] ?? $row['destination'])?></span></td>
<td role="cell" data-label="Preferred date"><?=ae(date('j M Y', strtotime($row['preferred_date'])))?></td>
<td role="cell" data-label="Attendees"><?=ae($row['attendees'])?></td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<nav aria-label="Pagination"><?php if ($page>1): ?><a href="<?=ae(adminListUrl($filters,$page-1,'/admin/interest'))?>">Previous</a><?php endif; ?><span>Page <?=$page?></span><?php if(count($rows)>25): ?><a href="<?=ae(adminListUrl($filters,$page+1,'/admin/interest'))?>">Next</a><?php endif; ?></nav>
