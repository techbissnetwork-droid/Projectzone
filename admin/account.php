<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$me     = current_user();
$errors = [];

if (is_post()) {
    csrf_check();

    if (post('action') === 'details') {
        $name  = post('name');
        $email = strtolower(post('email'));
        if ($name === '')          { $errors[] = 'Enter your name.'; }
        if (!valid_email($email))  { $errors[] = 'Enter a valid email address.'; }
        $clash = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $me['id']]);
        if ($clash)                { $errors[] = 'Another account already uses that email address.'; }
        if (!$errors) {
            db_update('users', (int) $me['id'], ['name' => $name, 'email' => $email, 'phone' => post('phone')]);
            flash('Your details are saved.');
            redirect('account.php');
        }
    }

    if (post('action') === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        if (!password_verify($current, $me['password_hash'])) {
            $errors[] = 'Your current password is not right.';
        }
        if ($p = password_problem($new)) { $errors[] = 'New password: ' . $p; }
        if (!$errors) {
            db_update('users', (int) $me['id'], ['password_hash' => hash_password($new)]);
            log_activity('Changed own password', 'user', (int) $me['id']);
            flash('Your password is changed.');
            redirect('account.php');
        }
    }
}

admin_head('Your account', 'account.php');
admin_page_head('Your account', 'Your own name, email and password.');

foreach ($errors as $e) {
    echo '<div class="flash bad">' . esc($e) . '</div>';
}
?>

<div class="grid2">
  <form method="post" class="admin">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="details">
    <fieldset>
      <legend>Details</legend>
      <div class="f"><label for="name">Name</label>
        <input id="name" name="name" value="<?= esc($me['name']) ?>" required></div>
      <div class="f"><label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= esc($me['email']) ?>" required></div>
      <div class="f"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= esc($me['phone']) ?>"></div>
      <div class="formbar"><button class="btn" type="submit">Save details</button></div>
    </fieldset>
  </form>

  <form method="post" class="admin">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <fieldset>
      <legend>Password</legend>
      <div class="f"><label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required></div>
      <div class="f"><label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" required>
        <span class="hint">At least 10 characters, with a letter and a number.</span></div>
      <div class="formbar"><button class="btn" type="submit">Change password</button></div>
    </fieldset>
  </form>
</div>

<?php admin_foot(); ?>
