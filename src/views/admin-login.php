<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign in · Nyumba Administration</title>
    <link rel="icon" href="/assets/nyumba-group.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/admin/login.css">
</head>
<body>
    <main class="login-layout">
        <section class="scene" aria-label="Nyumba Group">
            <a class="brand" href="/" aria-label="Nyumba Group home"><img src="/assets/nyumba-group.svg" alt="Nyumba Group" width="64" height="82"><span>NYUMBA GROUP<small>Visit administration</small></span></a>
            <div class="scene-title"><span class="eyebrow">A place to welcome.</span><h1>Nyumba.</h1></div>
            <div class="sun" aria-hidden="true"></div>
            <div class="house-wrap"><img class="house" src="/assets/admin/nyumba-house.webp" width="1536" height="1024" alt="A warm, sunlit Nyumba house with a welcoming front porch" fetchpriority="high"></div>
            <p class="scene-footer">Sahajanand <span>·</span> Galana <span>·</span> Kibarani</p>
        </section>
        <section class="sign-in" aria-labelledby="login-heading">
            <div class="form-wrap">
                <p class="eyebrow">Your workspace</p>
                <h2 id="login-heading">Welcome back.</h2>
                <p class="intro">Sign in to review bookings<br class="desktop-break"> and visitor feedback.</p>
                <?php if ($error): ?><p class="error" role="alert"><?=ae($error)?></p><?php endif; ?>
                <form method="post" action="/admin/login">
                    <input type="hidden" name="csrf" value="<?=ae($_SESSION['csrf'])?>">
                    <label for="admin-username">Username</label>
                    <input id="admin-username" name="username" autocomplete="username" required maxlength="200" autocapitalize="none" spellcheck="false" value="<?=ae(is_string($_POST['username'] ?? null) ? $_POST['username'] : '')?>">
                    <label for="admin-password">Password</label>
                    <input id="admin-password" type="password" name="password" autocomplete="current-password" required>
                    <button type="submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <p class="access-note">For authorised team members.<br>Need access? Contact your site administrator.</p>
            </div>
            <a class="back-link" href="/">← Back to the visitor website</a>
        </section>
    </main>
</body>
</html>
