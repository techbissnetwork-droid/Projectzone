<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/guard.php';

/** Client portal sign-in. Administrators use staff-login.php. */
if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'admin/' : 'client/');
}
LoginCode::prune();

$audience = 'client';
$stage    = 'email';
$email    = '';
$error    = '';
$notice   = '';

if (post()) {
    Csrf::check();
    $step  = (string)($_POST['step'] ?? 'request');
    $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));

    if ($step === 'request') {
        [$ok, $msg] = LoginCode::request($email, $audience);
        if ($ok) { $stage = 'code'; $notice = $msg; } else { $error = $msg; }
    } else {
        [$ok, $msg] = LoginCode::verify($email, (string)($_POST['code'] ?? ''), $audience);
        if ($ok) {
            $next = $_SESSION['after_login'] ?? null;
            unset($_SESSION['after_login']);
            if ($next && str_starts_with((string)$next, '/') && !str_starts_with((string)$next, '//')) {
                header('Location: ' . $next);
                exit;
            }
            redirect('client/');
        }
        $stage = 'code';
        $error = $msg;
    }
}

$PAGE_TITLE = 'Client sign in';
$META_DESC  = 'Sign in to track your sites, renewals and support requests.';
$BODY_CLASS = 'authpage';
$eyebrow = 'Client portal';
$heading = $stage === 'code' ? 'Check your email' : 'Sign in';
$lede    = $stage === 'code'
    ? 'Enter the code we just sent. It expires in ' . LoginCode::TTL_MINUTES . ' minutes.'
    : 'Track your sites, renewals and support requests.';
$altLink = url('staff-login.php');
$altText = 'Staff sign in';
$extra   = '<p class="auth__foot muted">No account yet? One is created when we start a project for you. '
         . '<a class="link" href="' . e(url('contact.php')) . '">Talk to us</a></p>';

require __DIR__ . '/partials/public_header.php';
require __DIR__ . '/partials/login_form.php';
require __DIR__ . '/partials/public_footer.php';
