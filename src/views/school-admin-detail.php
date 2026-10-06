<div class="school-admin-detail">
    <section class="school-admin-summary" aria-labelledby="school-summary-title">
        <div><p class="school-admin-eyebrow">Request summary</p><h2 id="school-summary-title"><?=ae($record['school'])?></h2><p class="school-admin-reference"><?=ae($record['reference'])?></p></div>
        <dl><div><dt>Construction requested</dt><dd><?=ae(SchoolRequest::TYPES[$record['support']])?></dd></div><div><dt>Submitted</dt><dd><?=ae(adminTimestamp($record['created_at']))?></dd></div><div><dt>Email notification</dt><dd><span class="school-admin-status"><?=ae($notificationState($record))?></span></dd></div></dl>
    </section>
    <div class="school-admin-columns">
    <?php foreach (['Contact details'=>['Contact name'=>'name','Relationship to school'=>'relationship','Email address'=>'email','Phone number'=>'phone'], 'School details'=>['School name'=>'school','County'=>'county','Town or locality'=>'locality']] as $heading=>$fields): ?>
        <section class="school-admin-section"><h2><?=ae($heading)?></h2><dl><?php foreach($fields as $label=>$key): ?><div><dt><?=ae($label)?></dt><dd><?=ae($record[$key])?></dd></div><?php endforeach; ?></dl></section>
    <?php endforeach; ?>
    </div>
    <section class="school-admin-section"><h2>Construction measurements</h2><p class="school-admin-hint">All measurements are in metres.</p>
        <div class="school-admin-measurement-groups">
        <?php foreach(['classroom'=>'Classroom','school_wall'=>'Boundary wall'] as $type=>$heading): if(!in_array($record['support'],[$type,'both'],true))continue; ?>
        <div><h3><?=ae($heading)?></h3><dl class="school-admin-measurements"><?php foreach(SchoolRequest::MEASUREMENTS as $key=>[$label,$measurementType]): if($measurementType!==$type)continue; ?><div><dt><?=ae($label)?></dt><dd><?=isset($record[$key])?ae((string)$record[$key]):'Not supplied'?></dd></div><?php endforeach; ?></dl></div>
        <?php endforeach; ?>
        </div>
    </section>
    <section class="school-admin-section"><h2>Additional details</h2><p class="school-admin-notes"><?=ae(trim($record['description'])!==''?$record['description']:'No additional details provided.')?></p></section>
</div>
