<?php require_once __DIR__.'/../MpesaConfig.php'; require_once __DIR__.'/../MpesaClient.php'; $merchant=MpesaConfig::load(); ?>
<section class="community-section wrap" id="donations" aria-labelledby="donations-title">
<div class="community-heading"><p class="eyebrow">SUPPORT NYUMBA</p><h2 id="donations-title">Your presence<br>means so much.</h2><p>Your visit to Sahajanand Special School, Galana Farm, or Kibarani Feeding Center is already enough of a contribution. If you would still like to give, you can make an optional donation through M-Pesa.</p></div>
<?php if($merchant): ?><div class="donation-details"><p><strong><?=e($merchant['name'])?></strong></p><p><?=e(ucfirst($merchant['type']))?> number: <strong><?=e($merchant['number'])?></strong></p><?php if($merchant['type']==='paybill'): ?><p>Account reference: <strong><?=e($merchant['reference'])?></strong></p><?php endif; ?><p>Enter your chosen amount in KES and confirm the recipient in M-Pesa.</p><?php if($merchant['environment']==='sandbox'): ?><p>Sandbox demonstration — no live donations.</p><?php endif; ?></div>
<?php else: ?>
<div class="mpesa-demo" data-mpesa-demo>
  <div>
    <p class="eyebrow">M-PESA</p>
    <h3>A little extra goes a long way.</h3>
    <label for="demo-donation-amount">Donation amount (KES)</label>
    <input id="demo-donation-amount" type="number" min="1" max="250000" step="1" value="500" inputmode="numeric">
    <p class="mpesa-demo-presets" aria-label="Suggested donation amounts"><button type="button" data-demo-amount="500">KES 500</button><button type="button" data-demo-amount="1000">KES 1,000</button><button type="button" data-demo-amount="2500">KES 2,500</button></p>
    <button type="button" class="community-action" data-demo-confirm>Preview donation ↗</button>
    <p role="status" data-demo-status></p>
    <noscript><p>Enable JavaScript to preview your donation.</p></noscript>
  </div>
  <figure><img src="/assets/mpesa-demo-qr.svg" alt="Payment preview QR code" width="240" height="240"><figcaption>Payment preview</figcaption></figure>
</div>
<script src="/assets/mpesa-demo.js" defer></script>
<?php endif; ?>
<?php if($merchant && MpesaClient::qrConfigured()): ?><a class="community-action" href="/donate">Donate with M-Pesa ↗</a><?php endif; ?>
</section>
