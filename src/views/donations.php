<?php require_once __DIR__.'/../MpesaConfig.php'; require_once __DIR__.'/../MpesaClient.php'; $merchant=MpesaConfig::load(); ?>
<section class="community-section wrap" id="donations" aria-labelledby="donations-title">
<div class="community-heading"><p class="eyebrow">SUPPORT NYUMBA</p><h2 id="donations-title">Your presence<br>means so much.</h2><p>Your visit to Sahajanand Special School, Galana Farm, or Kibarani Feeding Center is already enough of a contribution. If you would still like to give, you can make an optional donation through M-Pesa.</p></div>
<?php if($merchant): ?><div class="donation-details"><p><strong><?=e($merchant['name'])?></strong></p><p><?=e(ucfirst($merchant['type']))?> number: <strong><?=e($merchant['number'])?></strong></p><?php if($merchant['type']==='paybill'): ?><p>Account reference: <strong><?=e($merchant['reference'])?></strong></p><?php endif; ?><p>Enter your chosen amount in KES and confirm the recipient in M-Pesa.</p><?php if($merchant['environment']==='sandbox'): ?><p>Sandbox demonstration — no live donations.</p><?php endif; ?></div>
<?php else: ?><p class="community-note">M-Pesa donations are not available here yet. You are warmly welcome to visit.</p><?php endif; ?>
<?php if($merchant && MpesaClient::qrConfigured()): ?><a class="community-action" href="/donate">Donate with M-Pesa ↗</a><?php endif; ?>
</section>
