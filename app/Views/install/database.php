<?php
/** @var App\Core\View $view @var array $config @var array $meta @var string|null $previous @var array $flash */
$view->extends('layouts.install');
$view->start('content');
$driver = (string) ($config['driver'] ?? 'sqlite');
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?> The connection is tested live before you can continue.</p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <form method="post" action="<?= e(url('/install/step/database')) ?>" id="db-form">
      <?= csrf_field() ?>

      <fieldset style="border:0;padding:0;margin:0 0 var(--s-5)">
        <legend class="field__label" style="padding:0;margin-bottom:.6rem">Driver</legend>
        <div class="mode-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <label class="mode">
            <input type="radio" name="driver" value="sqlite" <?= $driver === 'sqlite' ? 'checked' : '' ?>
                   onchange="document.getElementById('sqlite-fields').hidden=false;document.getElementById('mysql-fields').hidden=true">
            <span class="mode__box">
              <?= icon('file') ?>
              <strong>SQLite</strong>
              <span>Zero configuration. A single file on disk. Ideal for small sites, staging and evaluation.</span>
            </span>
          </label>
          <label class="mode">
            <input type="radio" name="driver" value="mysql" <?= $driver === 'mysql' ? 'checked' : '' ?>
                   onchange="document.getElementById('sqlite-fields').hidden=true;document.getElementById('mysql-fields').hidden=false">
            <span class="mode__box">
              <?= icon('database') ?>
              <strong>MySQL / MariaDB</strong>
              <span>Recommended for production and anything with concurrent writers.</span>
            </span>
          </label>
        </div>
      </fieldset>

      <div id="sqlite-fields" <?= $driver === 'sqlite' ? '' : 'hidden' ?>>
        <div class="field">
          <label class="field__label" for="sqlite-path">
            Database file path
            <span class="field__hint">Must be writable and outside the web root</span>
          </label>
          <input class="input" type="text" id="sqlite-path" name="database"
                 value="<?= e($driver === 'sqlite' ? (string) ($config['database'] ?? '') : app()->path('storage/db/techbiss.sqlite')) ?>">
        </div>
      </div>

      <div id="mysql-fields" <?= $driver === 'mysql' ? '' : 'hidden' ?>>
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="db-host">Host</label>
            <input class="input" type="text" id="db-host" name="host" value="<?= e((string) ($config['host'] ?? '127.0.0.1')) ?>" autocomplete="off">
          </div>
          <div class="field">
            <label class="field__label" for="db-port">Port</label>
            <input class="input" type="number" id="db-port" name="port" value="<?= e((string) ($config['port'] ?? 3306)) ?>">
          </div>
        </div>
        <div class="field">
          <label class="field__label" for="db-name">
            Database name
            <span class="field__hint">Must already exist</span>
          </label>
          <input class="input" type="text" id="db-name" name="database" value="<?= e($driver === 'mysql' ? (string) ($config['database'] ?? '') : 'techbiss') ?>" autocomplete="off">
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="db-user">Username</label>
            <input class="input" type="text" id="db-user" name="username" value="<?= e((string) ($config['username'] ?? '')) ?>" autocomplete="off">
          </div>
          <div class="field">
            <label class="field__label" for="db-pass">Password</label>
            <input class="input" type="password" id="db-pass" name="password" autocomplete="new-password">
          </div>
        </div>
      </div>

      <div class="cluster mt-5">
        <button class="btn btn--ghost" type="submit" formaction="<?= e(url('/install/test-database')) ?>"
                formmethod="post" form="db-test-form" hidden></button>
      </div>
    </form>

    <!-- The test posts the same fields asynchronously; app.js renders the result. -->
    <form method="post" action="<?= e(url('/install/test-database')) ?>" class="mt-5"
          data-async-form data-async-target="db-result"
          onsubmit="(function(f){var s=document.getElementById('db-form');
            f.querySelectorAll('.mirror').forEach(function(n){n.remove()});
            new FormData(s).forEach(function(v,k){if(k==='_token')return;var i=document.createElement('input');
            i.type='hidden';i.name=k;i.value=v;i.className='mirror';f.appendChild(i)});})(this)">
      <?= csrf_field() ?>
      <button class="btn btn--ghost" type="submit"><?= icon('refresh') ?>Test connection</button>
    </form>
    <div id="db-result" class="mt-4"></div>

    <div class="alert alert--info mt-5">
      <?= icon('info') ?>
      <div>
        <strong>Nothing is written yet.</strong>
        <p>Continuing tests the connection and stores it for the install step. Tables are created only when you press install.</p>
      </div>
    </div>
  </div>
  <div class="install-card__foot">
    <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
    <button class="btn btn--primary" type="submit" form="db-form">Test and continue<?= icon('arrow-right') ?></button>
  </div>
</div>
<?php $view->stop(); ?>
