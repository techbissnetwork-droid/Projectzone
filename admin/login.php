<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();

if (is_staff()) {
    redirect('index.php');
}

$error = null;
if (is_post()) {
    csrf_check();
    $user = attempt_login(post('email'), $_POST['password'] ?? '', [ROLE_ADMIN, ROLE_STAFF], $error);
    if ($user) {
        $to = $_SESSION['after_login'] ?? 'index.php';
        unset($_SESSION['after_login']);
        redirect(str_contains($to, '/admin/') ? $to : 'index.php');
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in — TECHBISS admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="loginwrap">
  <div class="loginbox">
    <span class="brand"><i aria-hidden="true"></i>TECHBISS</span>
    <h1>Admin</h1>
    <p class="lead">Sign in to manage the site, projects and support.</p>
<?php if ($error): ?>
    <div class="flash bad"><?= esc($error) ?></div>
<?php endif; ?>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <div class="f"><label for="email">Email</label>
        <input id="email" name="email" type="email" required autofocus
               value="<?= esc(post('email')) ?>"></div>
      <div class="f"><label for="password">Password</label>
        <input id="password" name="password" type="password" required></div>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <div class="alt">
      Are you a client? <a href="../client/login.php">Use the client portal</a>.
    </div>
  </div>
</div>
</body>
</html>
