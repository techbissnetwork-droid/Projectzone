<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_installed('../install/');

if (current_staff()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired — please try again.';
    } else {
        $email = trim(strtolower((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        usleep(200000);

        // Before this, a 200ms sleep was the only obstacle to guessing the
        // password of the account that controls the entire panel. Throttle
        // per account and per source address: the first stops one login
        // being hammered, the second stops one attacker sweeping many.
        $ipOk = rate_limit_hit('login:ip:' . client_ip(), LOGIN_MAX_ATTEMPTS * 3, LOGIN_WINDOW_SECONDS);
        $emailOk = $email !== '' && rate_limit_hit('login:email:' . $email, LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS);

        if (!$ipOk || !$emailOk) {
            $error = 'Too many sign-in attempts. Please wait a few minutes and try again.';
        } else {
            $stmt = db()->prepare('SELECT id, name, password_hash FROM staff WHERE email = ?');
            $stmt->execute([$email]);
            $staff = $stmt->fetch();
            if ($staff && password_verify($password, $staff['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['staff_id'] = (int)$staff['id'];
                header('Location: index.php');
                exit;
            }
            $error = 'Invalid email or password.';
        }
    }
}
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<meta name="robots" content="noindex, nofollow">
<title>Staff sign in — TECHBISS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= ASSET_VERSION ?>">
<?= ui_zoom_style() ?>
<style>
body{ min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
.admin-login-card{ max-width:400px; width:100%; }
</style>
</head>
<body>
<main>
  <div class="card admin-login-card">
    <div class="card-head" style="margin-bottom:18px;"><?= blob_icon('shield', 'sm', true) ?><h3>Staff sign in</h3></div>
    <?php if ($error): ?>
      <p class="badge danger" style="margin-bottom:16px;"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required placeholder="you@techbiss.com"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••"></div>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p style="margin-top:16px;font-size:.82rem;color:var(--ink-faint);">Internal use only. This is not the customer login — that's on the main site.</p>
  </div>
</main>
</body>
</html>
