<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/**
 * The two-step sign-in card, shared by the client and staff pages.
 * Expects: $audience ('client'|'staff'), $eyebrow, $heading, $lede,
 *          $stage ('email'|'code'), $email, $error, $notice, $altLink, $altText.
 */
?>
<section class="auth" data-theme="deep">
  <div class="auth__field" aria-hidden="true"><span class="auth__halo"></span></div>
  <div class="auth__box">
    <p class="eyebrow"><i class="dot dot--live"></i> <?= e($eyebrow) ?></p>
    <h1 class="auth__title"><?= e($heading) ?></h1>
    <p class="auth__lede"><?= e($lede) ?></p>

    <?php if (!empty($notice)): ?><div class="notice notice--ok"><p><?= e($notice) ?></p></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="notice notice--err"><p><?= e($error) ?></p></div><?php endif; ?>

    <?php if ($stage === 'code'): ?>
      <form method="post" class="wform">
        <?= Csrf::field() ?>
        <input type="hidden" name="step" value="verify">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <label><span>Six-digit code <small>sent to <?= e($email) ?></small></span>
          <input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                 maxlength="6" required autofocus class="otp" placeholder="000000"></label>
        <button class="btn btn--primary btn--lg btn--block magnetic" type="submit">Sign in <span class="btn__arrow">→</span></button>
      </form>
      <form method="post" class="auth__resend">
        <?= Csrf::field() ?>
        <input type="hidden" name="step" value="request">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <button class="linkish" type="submit">Send a new code</button>
        <a class="linkish" href="?">Use a different address</a>
      </form>

    <?php else: ?>
      <form method="post" class="wform">
        <?= Csrf::field() ?>
        <input type="hidden" name="step" value="request">
        <label><span>Email address</span>
          <input name="email" type="email" required autocomplete="email" autofocus
                 value="<?= e($email) ?>" placeholder="you@yourbusiness.com"></label>
        <button class="btn btn--primary btn--lg btn--block magnetic" type="submit">Email me a code <span class="btn__arrow">→</span></button>
        <p class="wform__note">No password needed. We send a six-digit code that works once.</p>
      </form>
    <?php endif; ?>

    <?php if (!empty($extra)): ?><?= $extra ?><?php endif; ?>

    <p class="auth__foot"><a class="link" href="<?= e($altLink) ?>"><?= e($altText) ?> <span aria-hidden="true">→</span></a></p>
  </div>
</section>
