<?php
$__page = 'account';
$title = 'Account';
require_once __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newUser = trim((string) ($_POST['username'] ?? ''));
    $cur     = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $conf    = (string) ($_POST['confirm_password'] ?? '');

    $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$user['id']]);
    $row = $st->fetch();

    if (!$row || !password_verify($cur, $row['password_hash'])) {
        $error = 'Your current password is incorrect.';
    } elseif ($newUser === '') {
        $error = 'Username cannot be empty.';
    } elseif ($new !== '' && strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== '' && $new !== $conf) {
        $error = 'New password and confirmation do not match.';
    } else {
        if ($new !== '') {
            $pdo->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?")
                ->execute([$newUser, password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$newUser, $user['id']]);
        }
        flash('Account updated.');
        header('Location: ' . url('/admin/account.php'));
        exit;
    }
}

require_once __DIR__ . '/includes/head.php';
?>
<div class="topbar"><div><h1 class="page-title">Account</h1><div class="page-sub">Change your admin username and password.</div></div></div>

<div class="panel" style="max-width:520px">
  <h2>Credentials</h2>
  <?php if ($error): ?><div class="alert alert-error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/account.php')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" required></div>
    <div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
    <div class="field"><label>New password</label><input type="password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep current"><div class="hint">At least 6 characters.</div></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="confirm_password" autocomplete="new-password"></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Update account</button></div>
  </form>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
