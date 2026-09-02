<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me     = current_user();
$errors = [];

if (is_post() && post('action') === 'details') {
    csrf_check();
    /* The email address is deliberately not editable here: it is the sign-in
       identity, so only staff may change it. */
    $name = post('name');
    if ($name === '') { $errors[] = 'Enter your name.'; }
    if (!$errors) {
        db_update('users', (int) $me['id'], [
            'name' => $name, 'phone' => post('phone'), 'company' => post('company'),
        ]);
        flash('Your details are saved.');
        redirect('account.php');
    }
}

client_head('Account', 'account.php');
?>

<div class="hero-line">
  <h1>Your account</h1>
  <p>Your name and contact details. There is no password to manage — you sign in with a code
     we email you.</p>
</div>

<?php foreach ($errors as $e): ?>
<div class="flash bad"><?= esc($e) ?></div>
<?php endforeach; ?>

<div class="grid2">
  <form method="post" class="admin">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="details">
    <fieldset>
      <legend>Your details</legend>
      <div class="f"><label for="name">Name</label>
        <input id="name" name="name" required value="<?= esc($me['name']) ?>"></div>
      <div class="f"><label>Email</label>
        <input value="<?= esc($me['email']) ?>" disabled>
        <span class="hint">This is your sign-in address and where your codes are sent.
          To change it, ask us &mdash; raise it under
          <a href="support.php" style="color:var(--acc)">Support</a> and we will update it.</span></div>
      <div class="f"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= esc($me['phone']) ?>"></div>
      <div class="f"><label for="company">Business name</label>
        <input id="company" name="company" value="<?= esc($me['company']) ?>"></div>
      <div class="formbar"><button class="btn" type="submit">Save details</button></div>
    </fieldset>
  </form>

  <div class="panel">
    <header><h2>Signing in</h2></header>
    <div class="pad" style="color:var(--mute);font-size:14px">
      <p style="margin-bottom:12px">This account has no password. Each time you sign in, we email
        a six-digit code to <strong style="color:var(--fg)"><?= esc($me['email']) ?></strong>.
        The code works once and expires after <?= LOGIN_CODE_MINUTES ?> minutes.</p>
      <p>That means there is nothing to forget and nothing to leak. If you lose access to that
        inbox, contact us and we will change the address for you.</p>
    </div>
  </div>
</div>

<?php client_foot(); ?>
