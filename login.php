<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/guard.php';

if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/' : 'client/');
}

$error = '';
if (post()) {
    Csrf::check();
    [$ok, $msg] = Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($ok) {
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if ($next && str_starts_with((string)$next, '/') && !str_starts_with((string)$next, '//')) {
            header('Location: ' . $next);
            exit;
        }
        redirect(Auth::isAdmin() ? 'admin/' : 'client/');
    }
    $error = $msg;
}

$PAGE_TITLE = 'Sign in';
$META_DESC  = 'Client portal sign in.';
$BODY_CLASS = 'authpage';
require __DIR__ . '/partials/public_header.php';
?>
<section class="auth" data-theme="deep">
  <div class="auth__field" aria-hidden="true"><span class="auth__halo"></span></div>
  <div class="auth__box">
    <p class="eyebrow"><i class="dot dot--live"></i> Client portal</p>
    <h1 class="auth__title">Sign in</h1>
    <p class="auth__lede">Track your sites, renewals and support requests.</p>

    <?php if ($error): ?><div class="notice notice--err"><p><?= e($error) ?></p></div><?php endif; ?>

    <form method="post" class="wform">
      <?= Csrf::field() ?>
      <label><span>Email</span>
        <input name="email" type="email" required autocomplete="username" autofocus value="<?= e(old('email')) ?>"></label>
      <label><span>Password</span>
        <input name="password" type="password" required autocomplete="current-password"></label>
      <button class="btn btn--primary btn--lg btn--block magnetic" type="submit">Sign in <span class="btn__arrow">→</span></button>
    </form>

    <p class="auth__foot">No account yet? Accounts are created when we start a project for you.
      <a class="link" href="<?= e(url('contact.php')) ?>">Talk to us <span aria-hidden="true">→</span></a></p>
    <p class="auth__foot muted">Forgot your password? Email
      <a class="link" href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('contact_email')) ?></a> and we will reset it.</p>
  </div>
</section>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
