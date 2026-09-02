<?php
/**
 * Client sign-in, in two steps and with no password.
 *
 * Step 1 takes an email address and emails a six-digit code.
 * Step 2 takes the code.
 *
 * Step 1 says the same thing whether or not the address has an account, so
 * this page can never be used to find out who is a client.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();

if (is_client()) {
    redirect('index.php');
}

login_code_prune();

$step   = 'email';
$error  = null;
$notice = null;
$email  = strtolower(post('email', $_SESSION['login_email'] ?? ''));

if (is_post()) {
    csrf_check();

    /* --- step 1: ask for a code ------------------------------------- */
    if (post('action') === 'request') {
        $email = strtolower(post('email'));
        if (!valid_email($email)) {
            $error = 'Enter a valid email address.';
        } else {
            $code = login_code_request($email);
            if ($code) {
                $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
                $sent = $user ? mail_login_code($user, $code) : false;
                if (!$sent) {
                    error_log('[techbiss] could not email a sign-in code to ' . $email);
                }
            }
            /* Same outcome either way. */
            $_SESSION['login_email'] = $email;
            $step   = 'code';
            $notice = 'If that address has an account, a code is on its way.';
        }
    }

    /* --- resend ------------------------------------------------------- */
    if (post('action') === 'resend') {
        $email = strtolower($_SESSION['login_email'] ?? '');
        $code  = login_code_request($email);
        if ($code) {
            $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
            if ($user) {
                mail_login_code($user, $code);
            }
        }
        $step   = 'code';
        $notice = 'If that address has an account, another code is on its way.';
    }

    /* --- step 2: check the code --------------------------------------- */
    if (post('action') === 'verify') {
        $email = strtolower($_SESSION['login_email'] ?? post('email'));
        $user  = login_code_verify($email, post('code'), $error);
        if ($user) {
            unset($_SESSION['login_email']);
            $to = $_SESSION['after_login'] ?? 'index.php';
            unset($_SESSION['after_login']);
            redirect(str_contains($to, '/client/') ? $to : 'index.php');
        }
        $step = 'code';
    }
}

/* Coming back to the page with an address already in play. */
if ($step === 'email' && !empty($_SESSION['login_email']) && get('step') === 'code') {
    $step  = 'code';
    $email = $_SESSION['login_email'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Client sign-in — <?= esc(setting('site.name', 'TECHBISS')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../admin/assets/admin.css">
<style>
.codebox{font-family:var(--mono);font-size:30px;letter-spacing:.42em;text-align:center;
  padding:16px 12px 16px 22px;font-weight:500}
.codebox::placeholder{letter-spacing:.42em;color:var(--dim)}
.steps{display:flex;gap:8px;margin-bottom:20px}
.steps i{flex:1;height:3px;border-radius:2px;background:var(--line);display:block}
.steps i.on{background:var(--acc)}
.small{font-size:13px;color:var(--mute);margin-top:14px}
.small button{background:none;border:0;color:var(--acc);font:inherit;cursor:pointer;padding:0;
  text-decoration:underline}
</style>
</head>
<body>
<div class="loginwrap">
  <div class="loginbox">
    <span class="brand"><i aria-hidden="true"></i><?= esc(setting('site.name', 'TECHBISS')) ?></span>

<?php if ($step === 'email'): ?>
    <div class="steps"><i class="on"></i><i></i></div>
    <h1>Client portal</h1>
    <p class="lead">Type your email address and we will send you a code. No password to remember.</p>
<?php if ($error): ?><div class="flash bad"><?= esc($error) ?></div><?php endif; ?>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="request">
      <div class="f"><label for="email">Email address</label>
        <input id="email" name="email" type="email" required autofocus autocomplete="email"
               value="<?= esc($email) ?>"></div>
      <button class="btn" type="submit">Email me a code</button>
    </form>
    <div class="alt">
      Not a client yet? <a href="../contact.php">Talk to us</a>.<br><br>
      <a href="../index.php">Back to the website</a>
    </div>

<?php else: ?>
    <div class="steps"><i class="on"></i><i class="on"></i></div>
    <h1>Enter your code</h1>
    <p class="lead">We sent a six-digit code to <strong><?= esc($email) ?></strong>.
       It expires in <?= LOGIN_CODE_MINUTES ?> minutes.</p>
<?php if ($notice): ?><div class="flash ok"><?= esc($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash bad"><?= esc($error) ?></div><?php endif; ?>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="f"><label for="code">Six-digit code</label>
        <input id="code" name="code" type="text" class="codebox" required autofocus inputmode="numeric"
               autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="000000"></div>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <div class="small">
      Nothing arrived? Check your spam folder, or
      <form method="post" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <button type="submit">send another code</button>
      </form>
    </div>
    <div class="alt"><a href="login.php">Use a different email address</a></div>
<?php endif; ?>
  </div>
</div>
<script>
/* Codes are digits only, and submit as soon as six are in. */
(function () {
  var box = document.getElementById('code');
  if (!box) return;
  box.addEventListener('input', function () {
    box.value = box.value.replace(/\D/g, '').slice(0, 6);
    if (box.value.length === 6) box.form.requestSubmit();
  });
})();
</script>
</body>
</html>
