<?php
// Sign-in pages share one layout; $authView picks the panel: login, link-sent, forgot, reset-sent, reset or expired.
$titles = ['login' => 'Sign in', 'link-sent' => 'Check your email', 'forgot' => 'Reset your password', 'reset-sent' => 'Check your email', 'reset' => 'Set a new password', 'expired' => 'Link expired'];
$csrfField = '<input type="hidden" name="csrf" value="'.ae($_SESSION['csrf']).'">';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=ae($titles[$authView])?> · Nyumba Administration</title>
    <link rel="icon" href="/assets/nyumba-group.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/admin/login.css">
<?php if ($authView === 'login'): ?>
    <script src="/assets/admin/login.js" defer></script>
<?php endif; ?>
</head>
<body>
    <main class="login-layout">
        <section class="scene" aria-label="Nyumba Group">
            <a class="brand" href="/" aria-label="Nyumba Group home"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="64" height="82"><span>NYUMBA GROUP<small>Visit administration</small></span></a>
            <div class="scene-title"><span class="eyebrow">A place to welcome.</span><h1>Nyumba.</h1></div>
            <div class="sun" aria-hidden="true"></div>
            <div class="house-wrap"><img class="house" src="/assets/admin/nyumba-house.webp" width="1536" height="1024" alt="A warm, sunlit Nyumba house with a welcoming front porch" fetchpriority="high"></div>
            <p class="scene-footer">Galana Farm</p>
        </section>
        <section class="sign-in" aria-labelledby="login-heading">
            <div class="form-wrap">
<?php if ($authView === 'login'): ?>
                <p class="eyebrow">Your workspace</p>
                <h2 id="login-heading">Welcome back.</h2>
                <p class="intro">Sign in to review bookings<br class="desktop-break"> and visitor feedback.</p>
                <?php if ($error): ?><p class="error" role="alert"><?=ae($error)?></p><?php endif; ?>
                <form method="post" action="/admin/login">
                    <?=$csrfField?>
                    <label for="admin-email">Email</label>
                    <input id="admin-email" type="email" name="email" autocomplete="username" required maxlength="254" autocapitalize="none" spellcheck="false" value="<?=ae($postedEmail)?>">
                    <label for="admin-password">Password</label>
                    <div class="password-field">
                        <input id="admin-password" type="password" name="password" autocomplete="current-password">
                        <button class="password-toggle" type="button" aria-controls="admin-password" aria-label="Show password" hidden>Show</button>
                    </div>
                    <a class="forgot-link" href="/admin/forgot">Forgot password?</a>
                    <button type="submit">Sign in <span aria-hidden="true">↗</span></button>
                    <p class="divider"><span>or</span></p>
                    <button class="secondary" type="submit" formaction="/admin/login/link">Email me a sign-in link</button>
                </form>
                <p class="access-note">For authorised team members.<br>Need access? Ask Harith or Apoorv.</p>
<?php elseif ($authView === 'link-sent' || $authView === 'reset-sent'): ?>
                <h2 id="login-heading">Check your email.</h2>
                <p class="intro"><?php if ($authView === 'link-sent'): ?>If <?=ae($sentTo)?> has an account, we've sent a sign-in link. It works once and expires in 15 minutes.<?php else: ?>If <?=ae($sentTo)?> has an account, we've sent a reset link. It expires in 1 hour.<?php endif; ?></p>
                <a class="text-link" href="/admin/login">Back to sign in</a>
<?php elseif ($authView === 'forgot'): ?>
                <h2 id="login-heading">Reset your password.</h2>
                <p class="intro">Enter your email and we'll send you a link to set a new password.</p>
                <form method="post" action="/admin/forgot">
                    <?=$csrfField?>
                    <label for="admin-email">Email</label>
                    <input id="admin-email" type="email" name="email" autocomplete="username" required maxlength="254" autocapitalize="none" spellcheck="false">
                    <button type="submit">Send reset link <span aria-hidden="true">↗</span></button>
                </form>
                <a class="text-link" href="/admin/login">Back to sign in</a>
<?php elseif ($authView === 'reset'): ?>
                <h2 id="login-heading">Set a new password.</h2>
                <?php if ($error): ?><p class="error" role="alert"><?=ae($error)?></p><?php endif; ?>
                <form method="post" action="/admin/reset">
                    <?=$csrfField?>
                    <input type="hidden" name="token" value="<?=ae($resetToken)?>">
                    <label for="new-password">New password</label>
                    <input id="new-password" type="password" name="password" autocomplete="new-password" required aria-describedby="password-hint">
                    <p class="hint" id="password-hint">At least 12 characters.</p>
                    <label for="confirm-password">Confirm new password</label>
                    <input id="confirm-password" type="password" name="confirm" autocomplete="new-password" required>
                    <button type="submit">Save password <span aria-hidden="true">↗</span></button>
                </form>
<?php else: ?>
                <h2 id="login-heading">This link has expired.</h2>
                <p class="intro">Links work once and only for a short time. Request a new one below.</p>
                <a class="button-link" href="/admin/login">Back to sign in</a>
<?php endif; ?>
            </div>
            <a class="back-link" href="/">← Back to the visitor website</a>
        </section>
    </main>
</body>
</html>
