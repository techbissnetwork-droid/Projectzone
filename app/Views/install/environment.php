<?php
/** @var App\Core\View $view @var array $environment @var array $state @var array $meta @var string|null $previous @var array $flash */
$view->extends('layouts.install');
$view->start('content');
$url = (string) ($state['url'] ?? $environment['url']);
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?></p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <h2 class="h5">What we detected</h2>
    <div class="detected mt-4">
      <div class="detected__row"><span>Resolved URL</span><code><?= e($environment['url']) ?></code></div>
      <div class="detected__row"><span>Scheme</span><code><?= e($environment['scheme']) ?><?= $environment['behind_proxy'] ? ' (via forwarded header)' : '' ?></code></div>
      <div class="detected__row"><span>Host</span><code><?= e($environment['host']) ?></code></div>
      <div class="detected__row"><span>Base path</span><code><?= e($environment['base_path'] !== '' ? $environment['base_path'] : '/ (document root)') ?></code></div>
      <div class="detected__row"><span>Behind a proxy</span><code><?= $environment['behind_proxy'] ? 'yes' : 'no' ?></code></div>
      <div class="detected__row"><span>Server</span><code><?= e($environment['server_software']) ?></code></div>
    </div>

    <?php if (!$environment['docroot_is_public']): ?>
      <div class="alert alert--warn mt-4">
        <?= icon('alert') ?>
        <div>
          <strong>Document root is not the public directory.</strong>
          <p>
            Your web root is <code><?= e($environment['document_root'] ?: 'unknown') ?></code>, but the platform
            expects <code><?= e($environment['public_path']) ?></code>. The site will still work, though pointing
            the root at <code>public/</code> keeps application files off the web.
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$environment['https']): ?>
      <div class="alert alert--warn mt-4">
        <?= icon('lock') ?>
        <div>
          <strong>This request is not over HTTPS.</strong>
          <p>You can install over HTTP, but set a certificate up before going live — session cookies are only marked secure when the canonical URL uses HTTPS.</p>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/install/step/environment')) ?>" class="mt-6">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="url">
          Canonical site URL
          <span class="field__hint">Used for absolute links, canonical tags and the sitemap</span>
        </label>
        <input class="input" type="url" id="url" name="url" required value="<?= e($url) ?>"
               <?= error_for('url') ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('url')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
      </div>

      <label class="check">
        <input type="checkbox" name="detect_url" value="1" <?= ($state['detect_url'] ?? true) ? 'checked' : '' ?>>
        <span>
          <strong style="color:var(--ink)">Keep detecting the URL at runtime</strong><br>
          Recommended. The platform re-resolves its URL on every request, so the same
          installation works across domains, staging clones and sub-directories without
          re-configuration. Turn this off only if you need one fixed canonical origin.
        </span>
      </label>

      <div class="install-card__foot" style="margin:var(--s-6) calc(var(--s-6) * -1) calc(var(--s-6) * -1)">
        <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
        <button class="btn btn--primary" type="submit">Continue<?= icon('arrow-right') ?></button>
      </div>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
