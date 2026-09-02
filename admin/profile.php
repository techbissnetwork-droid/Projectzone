<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_admin();

$errors = [];
if (post()) {
    Csrf::check();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'details') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        if ($name === '')      $errors[] = 'Enter your name.';
        if (!is_email($email)) $errors[] = 'Enter a valid email address.';
        if (!$errors && Database::one('SELECT id FROM users WHERE email = :e AND id <> :id',
                ['e' => $email, 'id' => (int)$me['id']])) {
            $errors[] = 'Another account already uses that email.';
        }
        if (!$errors) {
            Database::update('users', [
                'name'    => $name,
                'email'   => $email,
                'phone'   => trim((string)($_POST['phone'] ?? '')) ?: null,
                'company' => trim((string)($_POST['company'] ?? '')) ?: null,
            ], (int)$me['id']);
            Flash::ok('Profile updated.');
            redirect('admin/profile.php');
        }
    }

    if ($do === 'password') {
        $cur  = (string)($_POST['current'] ?? '');
        $new  = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) $errors[] = 'Your current password is not correct.';
        if (strlen($new) < 10)  $errors[] = 'The new password must be at least 10 characters.';
        if ($new !== $new2)     $errors[] = 'The two new passwords do not match.';
        if (!$errors) {
            Database::update('users', [
                'password_hash' => password_hash($new, PASSWORD_DEFAULT),
                'must_change'   => 0,
            ], (int)$me['id']);
            log_activity('password.change', 'user', (int)$me['id']);
            Flash::ok('Password changed.');
            redirect('admin/profile.php');
        }
    }
}

$PAGE_TITLE = 'Profile';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if ($errors): ?>
  <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>
<?php if (!empty($me['must_change'])): ?>
  <div class="alert warn"><p><b>Set your own password.</b> This account still uses the password we generated for you.</p></div>
<?php endif; ?>

<div class="split">
  <section class="card">
    <div class="card__head"><h2>Your details</h2></div>
    <div class="card__body">
      <form method="post" class="form">
        <?= Csrf::field() ?><input type="hidden" name="do" value="details">
        <div class="row two">
          <label class="field"><span>Name</span><input name="name" value="<?= e($me['name']) ?>" required></label>
          <label class="field"><span>Email <small>this is your login</small></span>
            <input name="email" type="email" value="<?= e($me['email']) ?>" required></label>
        </div>
        <div class="row two">
          <label class="field"><span>Phone</span><input name="phone" value="<?= e($me['phone'] ?? '') ?>"></label>
          <label class="field"><span>Company</span><input name="company" value="<?= e($me['company'] ?? '') ?>"></label>
        </div>
        <div class="formfoot"><button class="btn" type="submit">Save details</button></div>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Password</h2></div>
    <div class="card__body">
      <form method="post" class="form">
        <?= Csrf::field() ?><input type="hidden" name="do" value="password">
        <label class="field"><span>Current password</span>
          <input name="current" type="password" required autocomplete="current-password"></label>
        <label class="field"><span>New password <small>10 characters or more</small></span>
          <input name="new" type="password" required minlength="10" autocomplete="new-password"></label>
        <label class="field"><span>Repeat new password</span>
          <input name="new2" type="password" required minlength="10" autocomplete="new-password"></label>
        <div class="formfoot"><button class="btn" type="submit">Change password</button></div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
