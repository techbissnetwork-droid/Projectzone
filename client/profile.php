<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

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
            redirect('client/profile.php');
        }
    }

}

$PAGE_TITLE = 'Profile';
$AREA = 'client';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if ($errors): ?>
  <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
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
    <div class="card__head"><h2>How you sign in</h2></div>
    <div class="card__body stack">
      <p class="dim">There is no password on your account. Each time you sign in we email a
        six-digit code to <b><?= e($me['email']) ?></b>, and it works once.</p>
      <p class="hint">Changing your email above changes where those codes go, so make sure you
        can receive mail at the new address first.</p>
      <a class="btn ghost" href="<?= e(url('login.php')) ?>">See the sign-in page</a>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
