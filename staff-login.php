<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/guard.php';

/**
 * Staff sign-in, separate from the client portal.
 *
 * The code is the normal route. A password is kept as a fallback so a mail
 * outage or a misconfigured server can never lock an administrator out of
 * their own site permanently.
 */
if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/' : 'client/');
}
LoginCode::prune();

$audience = 'staff';
$stage    = 'email';
$email    = '';
$error    = '';
$notice   = '';

if (post()) {
    Csrf::check();
    $step  = (string)($_POST['step'] ?? 'request');
    $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));

    if ($step === 'password') {
        [$ok, $msg] = Auth::attempt($email, (string)($_POST['password'] ?? ''));
        if ($ok && Auth::isAdmin()) {
            redirect('admin/');
        }
        if ($ok) {
            Auth::logout();
            $error = 'That account is not an administrator. Use the client sign-in page.';
        } else {
            $error = $msg;
        }
    } elseif ($step === 'request') {
        [$ok, $msg] = LoginCode::request($email, $audience);
        if ($ok) { $stage = 'code'; $notice = $msg; } else { $error = $msg; }
    } else {
        [$ok, $msg] = LoginCode::verify($email, (string)($_POST['code'] ?? ''), $audience);
        if ($ok) {
            redirect('admin/');
        }
        $stage = 'code';
        $error = $msg;
    }
}

$PAGE_TITLE = 'Staff sign in';
$META_DESC  = 'Administrator sign in.';
$BODY_CLASS = 'authpage';
$eyebrow = 'Admin panel';
$heading = $stage === 'code' ? 'Check your email' : 'Staff sign in';
$lede    = $stage === 'code'
    ? 'Enter the code we just sent. It expires in ' . LoginCode::TTL_MINUTES . ' minutes.'
    : 'Manage projects, clients, support and the site.';
$hideCodeForm = !LoginCode::available();
$altLink = url('login.php');
$altText = 'Client sign in';

/* Normally folded away. When sign-in codes cannot work — because the database
   is behind the code — it is the only way in, so open it and say why. */
$codesDown = !LoginCode::available();
ob_start(); ?>
<?php if ($codesDown): ?>
  <div class="notice notice--err">
    <p><b>Sign-in codes are unavailable.</b> This site's database has not been updated for the
      current version yet.</p>
    <p>Sign in with your password below, then open <b>System → Run database update</b>.</p>
  </div>
<?php endif; ?>
<details class="auth__fallback"<?= $codesDown ? ' open' : '' ?>>
  <summary><?= $codesDown ? 'Sign in with a password' : 'Email not arriving? Use a password instead' ?></summary>
  <form method="post" class="wform">
    <?= Csrf::field() ?>
    <input type="hidden" name="step" value="password">
    <label><span>Email</span>
      <input name="email" type="email" required autocomplete="username" value="<?= e($email) ?>"></label>
    <label><span>Password</span>
      <input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn btn--ghost btn--block magnetic" type="submit">Sign in with a password</button>
    <p class="wform__note">Kept as a fallback so a mail problem cannot lock you out of your own site.</p>
  </form>
</details>
<?php $extra = ob_get_clean();

require __DIR__ . '/partials/public_header.php';
require __DIR__ . '/partials/login_form.php';
require __DIR__ . '/partials/public_footer.php';
