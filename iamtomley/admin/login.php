<?php
require_once __DIR__ . '/_bootstrap.php';

if (current_user()) {
    header('Location: ' . url('/admin/index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (login_locked()) {
        $error = 'Too many failed attempts. Please wait about 15 minutes and try again.';
    } else {
        $u = trim((string) ($_POST['username'] ?? ''));
        $p = (string) ($_POST['password'] ?? '');
        if (attempt_login($u, $p)) {
            clear_login_fails();
            header('Location: ' . url('/admin/index.php'));
            exit;
        }
        record_login_fail();
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Sign in · <?= e(setting('brand_name', 'iamtomley')) ?> Admin</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('/favicon/favicon.svg')) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(url('/admin/assets/admin.css')) ?>" />
</head>
<body>
  <div class="login-wrap">
    <form class="login-card" method="post" action="<?= e(url('/admin/login.php')) ?>">
      <div class="brand"><span class="dot"></span><?= e(setting('brand_name', 'iamtomley')) ?></div>
      <div class="sub">Admin panel — sign in to manage your site</div>
      <?php if ($error): ?>
        <div class="alert alert-error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= e($error) ?></div>
      <?php endif; ?>
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" autofocus required />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required />
      </div>
      <button class="btn btn-primary" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign in
      </button>
    </form>
  </div>
</body>
</html>
