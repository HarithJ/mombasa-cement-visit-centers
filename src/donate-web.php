<?php
declare(strict_types=1);
require_once __DIR__.'/CommunityWeb.php';require_once __DIR__.'/CommunityStore.php';require_once __DIR__.'/MpesaClient.php';require_once __DIR__.'/DonationAttempt.php';
CommunityWeb::start();$e=CommunityWeb::escape(...);$config=MpesaConfig::load();$attempt=null;$error='';$amount='';$token=CommunityWeb::token('donation');$available=$config&&MpesaClient::qrConfigured();
$max=filter_var(getenv('MPESA_MAX_DONATION_KES')?:'250000',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999999999]])?:250000;
try{
 $db=CommunityStore::open();
 if($config){$service=new DonationAttempt($db,$config);$attempt=$service->find(hash('sha256',$token));}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $amount=is_string($_POST['amount']??null)?trim($_POST['amount']):'';
  if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);$error='This request is too large.';}
  elseif(!CommunityWeb::valid('donation')){http_response_code(403);$error='This form has expired. Please try again.';}
  elseif(!CommunityStore::throttle($db,'donation',$_SERVER['REMOTE_ADDR']??'',20)){http_response_code(429);$error='Please wait 15 minutes before trying again.';}
  elseif(!$available){http_response_code(503);$error='QR payments are currently unavailable.';}
  elseif(($_POST['action']??'')==='new'){$_SESSION['community_tokens']['donation']=bin2hex(random_bytes(32));header('Location: /donate',true,303);exit;}
  elseif(!$attempt&&(!preg_match('/^[1-9][0-9]{0,8}$/D',$amount)||(int)$amount>$max)){http_response_code(422);$error='Enter a whole-shilling amount between 1 and '.number_format($max).' KES.';}
  else{
   $attempt??=$service->create((int)$amount*100,hash('sha256',$token));
   $client=new MpesaClient($config,$mpesaTransport??null);$service->generate($attempt,[$client,'qr']);
   header('Location: /donate?status=1',true,303);exit;
  }
 }
}catch(Throwable $exception){http_response_code(503);$error='We could not load or save your donation request. No payment has been confirmed. Please try again.';}
CommunityWeb::header('Support Nyumba');
?>
<p class="eyebrow">SUPPORT NYUMBA</p><h1>A little more, only if you wish.</h1><p>Your visit is already enough of a contribution. A donation is optional and does not affect visits or school requests.</p>
<?php if($error): ?><p class="community-alert" role="alert"><?=$e($error)?></p><?php endif; ?>
<?php if($attempt): $status=DonationAttempt::paymentState($db,$attempt); ?>
<h2><?=$e($status)?></h2><p class="reference"><?=$e($attempt['reference'])?></p><dl><dt>Recipient</dt><dd><?=$e($attempt['merchant_name'])?></dd><dt>Amount</dt><dd>KES <?=$e(number_format($attempt['amount']/100,2))?></dd><dt><?=$e(ucfirst($attempt['merchant_type']))?> number</dt><dd><?=$e($attempt['payment_number'])?></dd><?php if($attempt['merchant_type']==='paybill'): ?><dt>Account reference</dt><dd><?=$e($attempt['reference'])?></dd><?php endif; ?></dl>
<?php if($attempt['environment']==='sandbox'): ?><p>Sandbox demonstration — no live donations.</p><?php endif; ?>
<?php if($status==='Donation received'): ?><p class="community-success">Thank you. Your donation has been recorded.</p>
<?php elseif($status==='Payment needs review'): ?><p>We have a payment update that needs review. Please keep your M-Pesa receipt and do not pay again while it is being checked.</p>
<?php elseif($attempt['qr']): ?><img class="payment-qr" alt="M-Pesa payment QR" src="data:image/png;base64,<?=$e($attempt['qr'])?>"><p>Scan with a supported M-Pesa app, check the recipient and amount, and authorize in M-Pesa. On the same phone, use the payment details above.</p><p>Already paid? Keep your M-Pesa receipt. Confirmation may take a little time; do not pay again.</p>
<?php else: ?><p>We could not prepare your QR yet. Retrying generates a QR only; it does not take a payment.</p><form method="post" class="community-form"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="submissionToken" value="<?=$e($token)?>"><button class="community-action">Retry QR generation</button></form><?php endif; ?>
<p><a href="/donate?status=1">Refresh payment status</a> · <a href="/#donations">Back to Visit Nyumba</a></p>
<?php if($status==='Donation received'): ?><form method="post" class="community-form"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="submissionToken" value="<?=$e($token)?>"><input type="hidden" name="action" value="new"><button class="community-action">Make another donation</button></form><?php endif; ?>
<?php elseif($available): ?>
<form class="community-form" method="post" action="/donate"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="submissionToken" value="<?=$e($token)?>"><label>Donation amount (KES)<input name="amount" type="number" min="1" max="<?=$max?>" step="1" required value="<?=$e($amount)?>"></label><button class="community-action wide">Create payment QR</button></form><p class="form-hint">You authorize payment in M-Pesa. We will never ask for your PIN.</p>
<?php else: ?><p>M-Pesa QR donations are not available here yet. You are warmly welcome to visit.</p><p><a href="/#donations">Back to Visit Nyumba</a></p><?php endif; ?></main></body></html>
