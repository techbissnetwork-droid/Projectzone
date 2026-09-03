<?php
/** @var App\Core\View $view @var string $portal @var array $copy @var array $flash */
$view->extends('layouts.auth');
$view->start('content');
?>
<div class="auth a-<?= e($copy['accent']) ?>" style="grid-template-columns:1fr">
  <div class="auth__main">
    <div class="auth__card">
      <a class="logo" href="<?= e(url('/')) ?>" style="margin-bottom:var(--s-6)">
        <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
        <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
      </a>

      <h1 class="h2">Reset your password</h1>
      <p class="muted mt-3" style="font-size:var(--t-0)">
        Enter the address on your <?= e($copy['title']) ?> account and we will send
        a link that stays valid for one hour.
      </p>

      <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

      <form method="post" action="<?= e(url('/' . $portal . '/forgot-password')) ?>" class="mt-6" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="field__label" for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" required autocomplete="username"
                 autocapitalize="none" spellcheck="false" autofocus>
        </div>
        <button class="btn btn--primary btn--lg btn--block" type="submit">Send reset link</button>
      </form>

      <div class="auth__switch">
        <a href="<?= e(url('/' . $portal . '/login')) ?>">
          <span><?= icon('arrow-right', ['size' => 14]) ?> Back to sign in</span>
        </a>
      </div>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
