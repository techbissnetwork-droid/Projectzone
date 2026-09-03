<?php
/** @var App\Core\View $view @var string $portal @var array $copy @var array $others @var array $flash */
$view->extends('layouts.auth');
$view->start('content');
?>
<div class="auth a-<?= e($copy['accent']) ?>">
  <aside class="auth__aside">
    <div>
      <a class="logo" href="<?= e(url('/')) ?>">
        <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
        <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
      </a>
      <div class="mt-7">
        <span class="auth__badge"><?= icon($copy['icon']) ?><?= e($copy['eyebrow']) ?></span>
        <h1 class="h2 mt-5"><?= e($copy['title']) ?></h1>
        <p class="lede mt-4" style="font-size:var(--t-0)"><?= e($copy['lede']) ?></p>
      </div>
      <div class="auth__points">
        <?php foreach ($copy['points'] as $point): ?>
          <div class="auth__point">
            <?= icon($point[0]) ?>
            <span>
              <strong><?= e($point[1]) ?></strong>
              <span><?= e($point[2]) ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <p class="tiny dim">
        Protected by rate limiting, CSRF verification and session rotation.
        Read our <a href="<?= e(url('/legal/security')) ?>" style="color:var(--accent-2)">security statement</a>.
      </p>
    </div>
  </aside>

  <div class="auth__main">
    <div class="auth__card">
      <div class="u-hide" style="display:block">
        <a class="logo u-only@lg" href="<?= e(url('/')) ?>" style="margin-bottom:var(--s-6)">
          <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
          <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
        </a>
      </div>

      <h2 class="h2">Sign in</h2>
      <p class="muted mt-3" style="font-size:var(--t-0)">
        <?= e($copy['title']) ?> access. Use the credentials issued to you.
      </p>

      <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

      <form method="post" action="<?= e(url('/' . $portal . '/login')) ?>" class="mt-6" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="field__label" for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" required autocomplete="username"
                 autocapitalize="none" spellcheck="false" value="<?= e(old('email')) ?>"
                 <?= error_for('email') ? 'aria-invalid="true"' : '' ?> autofocus>
          <?php if ($error = error_for('email')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label class="field__label" for="password">
            Password
            <a class="field__hint" href="<?= e(url('/' . $portal . '/forgot-password')) ?>" style="color:var(--accent-2)">Forgot it?</a>
          </label>
          <input class="input" type="password" id="password" name="password" required autocomplete="current-password"
                 <?= error_for('password') ? 'aria-invalid="true"' : '' ?>>
          <?php if ($error = error_for('password')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>

        <label class="check mt-4">
          <input type="checkbox" name="remember" value="1">
          <span>Keep me signed in on this device</span>
        </label>

        <button class="btn btn--primary btn--lg btn--block mt-6" type="submit">
          Sign in to <?= e($copy['title']) ?><?= icon('arrow-right') ?>
        </button>
      </form>

      <div class="auth__demo">
        <strong style="color:var(--ink-2)">Demo credentials</strong><br>
        Email <code><?= e($copy['demo']) ?></code><br>
        Password <code><?= e($copy['demo_password']) ?></code>
      </div>

      <div class="auth__switch">
        <span class="tiny dim">Wrong portal?</span>
        <?php foreach ($others as $key => $other): ?>
          <a href="<?= e(url('/' . $key . '/login')) ?>">
            <span><?= icon($other['icon'], ['size' => 15]) ?> <?= e($other['title']) ?></span>
            <?= icon('arrow-right', ['size' => 14]) ?>
          </a>
        <?php endforeach; ?>
        <a href="<?= e(url('/')) ?>">
          <span><?= icon('globe', ['size' => 15]) ?> Back to the website</span>
          <?= icon('arrow-right', ['size' => 14]) ?>
        </a>
      </div>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
