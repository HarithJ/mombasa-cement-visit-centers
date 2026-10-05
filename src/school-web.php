<?php
declare(strict_types=1);
require_once __DIR__.'/CommunityWeb.php';
require_once __DIR__.'/CommunityStore.php';
require_once __DIR__.'/SchoolRequest.php';
CommunityWeb::start();
$e=CommunityWeb::escape(...); $values=[]; $errors=[]; $saved=null;
$token=CommunityWeb::token('school');
try {
    $db=CommunityStore::open();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        [$values,$errors]=SchoolRequest::validate($_POST);
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) {$errors['_form']='This request is too large.';http_response_code(413);}
        elseif (!CommunityWeb::valid('school')) {$errors['_form']='This form has expired. Please try again.';http_response_code(403);}
        elseif (!CommunityStore::throttle($db,'school',$_SERVER['REMOTE_ADDR']??'')) {$errors['_form']='Too many requests. Please try again in 15 minutes.';http_response_code(429);}
        elseif (($_POST['action']??'')==='new') {$_SESSION['community_tokens']['school']=bin2hex(random_bytes(32));header('Location: /school-request',true,303);exit;}
        elseif ($errors) http_response_code(422);
        else {SchoolRequest::save($db,$values,hash('sha256',$token));header('Location: /school-request?received=1',true,303);exit;}
    } else {
        $q=$db->prepare('SELECT reference FROM school_requests WHERE submission_token=?');$q->execute([hash('sha256',$token)]);$saved=$q->fetch();
    }
} catch (Throwable $error) {http_response_code(503);$errors['_form']='We could not save or load your request. Please try again. No new confirmation has been issued.';}
CommunityWeb::header('Request school support');
?>
<p class="eyebrow">BUILDING FOR EDUCATION</p><h1>Request school support</h1>
<?php if ($saved): ?>
<div class="community-success"><h2>Your request has been received</h2><p class="reference"><?=$e($saved['reference'])?></p><p>Our team will consider your school's needs. Submission does not guarantee construction or a completion date.</p></div><p><a href="/#school-projects">Back to school projects</a></p><form method="post" class="community-form"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="submissionToken" value="<?=$e($token)?>"><input type="hidden" name="action" value="new"><button class="community-action">Submit another school request</button></form>
<?php else: ?>
<p>Tell us about your school's need for a classroom or school wall. No booking or donation is needed.</p><p class="form-hint">All fields are required. Please include only school and contact information needed for follow-up, not pupils' personal information.</p>
<?php if (isset($errors['_form'])): ?><p role="alert" class="community-alert"><?=$e($errors['_form'])?></p><?php endif; ?>
<form class="community-form" method="post" action="/school-request">
<input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="submissionToken" value="<?=$e($token)?>">
<?php foreach (SchoolRequest::FIELDS as $key=>[$label,$max]): ?>
<label class="<?=$key==='description'?'wide':''?>" for="school-<?=$e($key)?>"><?=$e($label)?>
<?php if ($key==='support'): ?><select name="support" id="school-support" required aria-describedby="school-support-error"><option value="">Choose support</option><?php foreach (SchoolRequest::TYPES as $value=>$text): ?><option value="<?=$e($value)?>" <?=($values[$key]??'')===$value?'selected':''?>><?=$e($text)?></option><?php endforeach; ?></select>
<?php elseif ($key==='description'): ?><textarea name="description" id="school-description" required maxlength="3000" aria-describedby="school-description-error"><?=$e($values[$key]??'')?></textarea>
<?php else: ?><input id="school-<?=$e($key)?>" name="<?=$e($key)?>" type="<?=$key==='email'?'email':($key==='phone'?'tel':'text')?>" maxlength="<?=$max?>" required value="<?=$e($values[$key]??'')?>" aria-describedby="school-<?=$e($key)?>-error"><?php endif; ?>
<span class="community-error" id="school-<?=$e($key)?>-error"><?=$e($errors[$key]??'')?></span></label>
<?php endforeach; ?>
<p class="wide form-hint">Your details are shared privately with Nyumba's team for follow-up. This is a request for consideration, not an approval.</p><button class="community-action wide">Submit school request</button>
</form><?php endif; ?></main></body></html>
