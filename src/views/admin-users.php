<?php
// The Users page: $userView is list, form (add when $editing is null, else edit) or deactivate.
$pageTitle = 'Users';
$csrfField = '<input type="hidden" name="csrf" value="'.ae($_SESSION['csrf']).'">';
$roleNames = ['admin' => 'Admin', 'team' => 'Team'];
require __DIR__.'/admin-header.php';
?>
<main class="users-page">
<?php if ($notice): ?><p class="notice" role="status"><?=ae($notice)?></p><?php endif; ?>
<?php if ($userView === 'list'): ?>
<div class="page-heading"><div><p class="eyebrow">Your workspace</p><h1>Users</h1></div><a class="button-link" href="/admin/users/new">Add user</a></div>
<div class="table" role="region" aria-label="Users"><table role="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th></tr></thead><tbody>
<?php foreach ($allUsers as $user): ?><tr role="row">
<td role="cell" data-label="Name" class="visitor-cell"><a href="/admin/users/<?=ae($user['id'])?>"><?=ae($user['name'])?></a></td>
<td role="cell" data-label="Email"><?=ae($user['email'])?></td>
<td role="cell" data-label="Phone"><?=ae($user['phone'])?></td>
<td role="cell" data-label="Role"><?=ae($roleNames[$user['role']] ?? $user['role'])?></td>
<td role="cell" data-label="Status"><span class="badge <?=$user['active'] ? '' : 'pending'?>"><?=$user['active'] ? 'Active' : 'Deactivated'?></span></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($userView === 'deactivate'): ?>
<a href="/admin/users/<?=ae($editing['id'])?>">Back to <?=ae($editing['name'])?></a>
<section class="panel user-panel"><h1><?=ae($editing['name'])?></h1>
<p>Deactivate <?=ae($editing['name'])?>? They'll be signed out and stop getting booking emails. Their past decisions stay on record.</p>
<form method="post" action="/admin/users/<?=ae($editing['id'])?>/deactivate"><?=$csrfField?><button class="danger">Deactivate</button></form></section>
<?php else: ?>
<a href="/admin/users">Back to users</a>
<section class="panel user-panel"><h1><?=$editing ? ae($editing['name']) : 'Add user'?></h1>
<?php if ($editing): ?><p><span class="badge <?=$editing['active'] ? '' : 'pending'?>"><?=$editing['active'] ? 'Active' : 'Deactivated'?></span></p><?php endif; ?>
<?php if ($userErrors): ?><div class="error" role="alert"><?php foreach ($userErrors as $message): ?><p><?=ae($message)?></p><?php endforeach; ?></div><?php endif; ?>
<form class="user-form" method="post" action="<?=$editing ? '/admin/users/'.ae($editing['id']) : '/admin/users/new'?>"><?=$csrfField?>
<label>Name<input name="name" maxlength="120" autocomplete="off" value="<?=ae($userForm['name'])?>"></label>
<label>Email<input name="email" type="email" maxlength="254" autocomplete="off" value="<?=ae($userForm['email'])?>"></label>
<label>Phone<input name="phone" type="tel" maxlength="40" autocomplete="off" value="<?=ae($userForm['phone'])?>"></label>
<label>Role<select name="role" aria-describedby="role-hint"><?php foreach ($roleNames as $value => $label): ?><option value="<?=ae($value)?>" <?=$userForm['role'] === $value ? 'selected' : ''?>><?=ae($label)?></option><?php endforeach; ?></select></label>
<p id="role-hint" class="muted hint">Admins also get an SMS for every new booking and can manage users.</p>
<button><?=$editing ? 'Save changes' : 'Add user'?></button></form>
<?php if ($editing && $editing['active']): ?><p class="user-status-action"><a class="danger-link" href="/admin/users/<?=ae($editing['id'])?>/deactivate">Deactivate</a></p>
<?php elseif ($editing): ?><form class="user-status-action" method="post" action="/admin/users/<?=ae($editing['id'])?>/reactivate"><?=$csrfField?><button>Reactivate</button></form><?php endif; ?>
</section>
<?php endif; ?>
</main></body></html>
