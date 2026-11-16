<?php
// Shared top of every signed-in admin page. $pageTitle names the page; admins also get the Users item.
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=ae(isset($pageTitle) ? $pageTitle.' · ' : '')?>Nyumba · Administration</title>
<link rel="icon" href="/assets/nyumba-group.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/admin/workspace.css"></head><body>
<header><a class="brand" href="/admin"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="42" height="55"><span>NYUMBA GROUP<small>Visit administration</small></span></a><?php if ($authenticated): ?><div class="header-actions"><?php if (($currentUser['role'] ?? '') === 'admin'): ?><nav class="admin-nav" aria-label="Administration"><a href="/admin" <?=str_starts_with($path, '/admin/users') ? '' : 'aria-current="page"'?>>Bookings</a><a href="/admin/users" <?=str_starts_with($path, '/admin/users') ? 'aria-current="page"' : ''?>>Users</a></nav><?php endif; ?><form action="/admin/logout" method="post"><input type="hidden" name="csrf" value="<?=ae($_SESSION['csrf'])?>"><button>Sign out</button></form></div><?php endif; ?></header>
