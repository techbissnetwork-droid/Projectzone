<?php
/** @var App\Core\View $view @var array $grouped @var bool $installed @var bool $detectUrl @var string $currentUrl @var string $driver */
$view->extends('layouts.portal');
$view->start('content');
$toggles = ['marketplace_enabled', 'lazy_images', 'amp_enabled', 'indexable'];
$labels = [
    'site_name' => 'Site name', 'site_tagline' => 'Tagline', 'contact_email' => 'General enquiries',
    'sales_email' => 'Sales inbox', 'support_email' => 'Support inbox',
    'default_currency' => 'Default currency', 'tax_rate' => 'Tax rate (%)',
    'marketplace_enabled' => 'Marketplace enabled', 'page_cache_ttl' => 'Page cache TTL (seconds)',
    'lazy_images' => 'Lazy-load images', 'amp_enabled' => 'Serve AMP variants',
    'indexable' => 'Allow search engine indexing',
];
$hints = [
    'page_cache_ttl' => 'Shared CDN cache window for anonymous marketing pages.',
    'indexable' => 'Turning this off makes robots.txt disallow everything. Use for staging.',
    'amp_enabled' => 'Publishes /amp variants and links them from canonical pages.',
    'tax_rate' => 'Applied to marketplace subtotals at checkout.',
];
?>
<div class="panel">
  <div class="panel__head"><h3>Runtime</h3><span class="small dim">Read-only — set by the installer</span></div>
  <div class="panel__body">
    <div class="detected">
      <div class="detected__row"><span>Canonical URL</span><code><?= e($currentUrl) ?></code></div>
      <div class="detected__row"><span>URL auto-detection</span><code><?= $detectUrl ? 'enabled' : 'pinned' ?></code></div>
      <div class="detected__row"><span>Database driver</span><code><?= e($driver) ?></code></div>
      <div class="detected__row"><span>Installer</span><code><?= $installed ? 'locked' : 'unlocked' ?></code></div>
      <div class="detected__row"><span>Platform version</span><code><?= e(App\Core\Application::VERSION) ?></code></div>
    </div>
    <p class="tiny dim mt-4">
      To change these, edit <code class="mono">storage/installed.php</code> or re-run the installer by
      creating <code class="mono">storage/install.unlock</code>.
    </p>
  </div>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>" class="mt-4">
  <?= csrf_field() ?>
  <?php foreach ($grouped as $group => $rows): ?>
    <div class="panel mt-4">
      <div class="panel__head"><h3><?= e(ucfirst($group)) ?></h3></div>
      <div class="panel__body">
        <div class="field-row">
          <?php foreach ($rows as $row): ?>
            <?php $key = (string) $row['setting_key']; ?>
            <?php if (in_array($key, $toggles, true)): ?>
              <div class="field" style="grid-column:1/-1">
                <label class="check">
                  <input type="checkbox" name="settings[<?= e($key) ?>]" value="1" <?= $row['setting_value'] === '1' ? 'checked' : '' ?>>
                  <span>
                    <strong style="color:var(--ink)"><?= e($labels[$key] ?? $key) ?></strong>
                    <?php if (isset($hints[$key])): ?><br><?= e($hints[$key]) ?><?php endif; ?>
                  </span>
                </label>
              </div>
            <?php else: ?>
              <div class="field">
                <label class="field__label" for="s-<?= e($key) ?>">
                  <?= e($labels[$key] ?? $key) ?>
                  <?php if (isset($hints[$key])): ?><span class="field__hint"><?= e($hints[$key]) ?></span><?php endif; ?>
                </label>
                <input class="input" type="text" id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                       value="<?= e((string) $row['setting_value']) ?>">
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="cluster mt-5">
    <button class="btn btn--primary" type="submit">Save settings</button>
    <span class="small dim">Saving clears the page and fragment caches.</span>
  </div>
</form>
<?php $view->stop(); ?>
