<?php
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$me = auth_user();
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'delete') {
        $id = (int) post('id');
        if ($id === (int) $me['id']) {
            $errors[] = 'You cannot delete the account you are signed in with.';
        } elseif ((int) scalar('SELECT COUNT(*) FROM users') <= 1) {
            $errors[] = 'There must always be at least one admin account.';
        } else {
            db_delete('users', $id);
            flash('Admin removed.');
            redirect('admin/users.php');
        }
    }

    if ($action === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password2'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $me['password_hash'])) {
            $errors[] = 'Your current password was not correct.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'Use at least 10 characters for the new password.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords did not match.';
        } else {
            db_update('users', (int) $me['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            flash('Password changed.');
            redirect('admin/users.php');
        }
    }

    if ($action === 'add') {
        $name  = post('name');
        $email = mb_strtolower(post('email'));
        $pass  = (string) ($_POST['password'] ?? '');
        if ($name === '') {
            $errors[] = 'A name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address is not valid.';
        } elseif (scalar('SELECT COUNT(*) FROM users WHERE email = :e', ['e' => $email])) {
            $errors[] = 'An admin with that email already exists.';
        }
        if (strlen($pass) < 10) {
            $errors[] = 'Use at least 10 characters for the password.';
        }
        if (!$errors) {
            db_insert('users', [
                'name' => $name, 'email' => $email,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role' => 'admin', 'created_at' => now(),
            ]);
            flash('Admin added.');
            redirect('admin/users.php');
        }
    }
}

$users = all('SELECT * FROM users ORDER BY id ASC');
admin_header('Admin users');
?>
<h1>Admin users</h1>

<?php if ($errors): ?>
  <div class="aflash aflash--err"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<table class="atable">
  <thead><tr><th>Name</th><th>Email</th><th>Last signed in</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="amuted">(you)</span>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= $u['last_login_at'] ? e(date('j M Y, H:i', strtotime($u['last_login_at']))) : '—' ?></td>
        <td>
          <?php if ((int) $u['id'] !== (int) $me['id']): ?>
            <form method="post" class="ainline" onsubmit="return confirm('Remove this admin?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="abtn abtn--sm abtn--danger">Remove</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="asplit asplit--wide">
  <form method="post" class="apanel">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2>Change your password</h2>
    <div class="afield"><label for="current">Current password</label><input type="password" id="current" name="current" autocomplete="current-password" required></div>
    <div class="afield"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required><p class="ahint">At least 10 characters.</p></div>
    <div class="afield"><label for="password2">Repeat new password</label><input type="password" id="password2" name="password2" autocomplete="new-password" required></div>
    <div class="arow"><button type="submit">Change password</button></div>
  </form>

  <form method="post" class="apanel">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <h2>Add an admin</h2>
    <div class="afield"><label for="name">Name</label><input type="text" id="name" name="name" required></div>
    <div class="afield"><label for="email">Email</label><input type="email" id="email" name="email" required></div>
    <div class="afield"><label for="npass">Password</label><input type="password" id="npass" name="password" autocomplete="new-password" required><p class="ahint">At least 10 characters.</p></div>
    <div class="arow"><button type="submit">Add admin</button></div>
  </form>
</div>
<?php admin_footer(); ?>
