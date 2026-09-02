<?php
require __DIR__ . '/../app/bootstrap.php';
require_installed();

if (auth_check()) {
    redirect('admin/index.php');
}

$error = '';
$email = '';

if (is_post()) {
    csrf_check();
    $email = post('email');

    // Five failures from one address in fifteen minutes stops the guessing.
    if (throttle_count('login', 900) >= 5) {
        $error = 'Too many failed attempts. Wait fifteen minutes and try again.';
    } elseif (auth_attempt($email, $_POST['password'] ?? '')) {
        redirect('admin/index.php');
    } else {
        $error = 'Those details were not recognised.';
    }
}
throttle_prune();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sign in — TECHBISS admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base_url('admin/assets/admin.css')) ?>">
</head>
<body class="alogin">
  <form method="post" class="acard">
    <?= csrf_field() ?>
    <div class="abrand abrand--big"><span class="amark">T</span> TECHBISS</div>
    <h1>Sign in</h1>
    <?php if ($error): ?><p class="aerr"><?= e($error) ?></p><?php endif; ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
    <p class="anote"><a href="<?= e(base_url('index.php')) ?>">← Back to the website</a></p>
  </form>
</body>
</html>
