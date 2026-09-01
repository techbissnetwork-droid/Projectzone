<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    // Brute-force damping, counted server-side.
    //
    // This used to live in $_SESSION, which is not a throttle: the counter sat
    // in a cookie the client controls, so an attacker who never sends one had
    // an unlimited number of guesses at the admin password. Counted per IP and
    // per username now, in the cache table, where the client cannot reach it.
    $user = trim((string)($_POST['username'] ?? ''));
    [$allowed, $retry] = \SignalMasterAi\RateLimit::loginCheck('adm', $user);
    if (!$allowed) {
        $error = 'Too many failed attempts. Try again in ' . (int)ceil($retry / 60) . ' minutes.';
    } elseif (Auth::attempt($user, (string)($_POST['password'] ?? ''))) {
        \SignalMasterAi\RateLimit::loginPassed('adm', $user);
        header('Location: index.php');
        exit;
    } else {
        \SignalMasterAi\RateLimit::loginFailed('adm', $user);
        $error = 'Invalid username or password.';
    }
}
$siteName = sma_setting('site_name', 'SignalMasterAi');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login - <?= sma_e($siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="../assets/brand/logo.svg">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/brand/favicon-32.png">
<link rel="stylesheet" href="admin.css">
</head>
<body class="login-body">
  <form class="login-box" method="post" action="login.php">
    <h1><?= \SignalMasterAi\Chrome::mark(30, 'vertical-align:-6px') ?> <?= sma_e($siteName) ?></h1>
    <p>Admin panel sign in</p>
    <?php if ($error): ?><div class="error"><?= sma_e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
    <label>Username</label>
    <input type="text" name="username" autocomplete="username" autofocus>
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password">
    <button class="btn" type="submit">Sign in</button>
    <p style="margin-top:14px"><a href="../index.php" style="color:var(--muted)">&larr; Back to site</a></p>
  </form>
</body>
</html>
