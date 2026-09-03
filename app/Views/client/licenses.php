<?php
/** @var App\Core\View $view @var array $licenses */
$view->extends('layouts.portal');
$view->start('content');
?>
<?php if ($licenses === []): ?>
  <div class="empty">
    <?= icon('tag') ?>
    <h3>No licences yet</h3>
    <p>Every marketplace purchase issues a licence key here, with its download and deployment tools.</p>
    <a class="btn btn--primary" href="<?= e(url('/marketplace')) ?>">Browse the Marketplace</a>
  </div>
<?php endif; ?>

<div class="stack" style="--flow:var(--s-4)">
  <?php foreach ($licenses as $license): ?>
    <?php $domains = json_decode((string) ($license['domains'] ?? '[]'), true) ?: []; ?>
    <div class="panel">
      <div class="panel__head">
        <div class="cluster" style="gap:.8rem;flex-wrap:nowrap;min-width:0">
          <span class="cart-line__thumb" style="width:44px"><?= art_tile((string) $license['slug'], initials((string) $license['product_name'])) ?></span>
          <div style="min-width:0">
            <h3><?= e($license['product_name']) ?></h3>
            <span class="tiny dim">v<?= e($license['version']) ?> · order <?= e($license['order_reference'] ?: '—') ?></span>
          </div>
        </div>
        <div class="cluster" style="gap:.4rem">
          <span class="badge badge--neutral"><?= e(ucfirst((string) $license['tier'])) ?></span>
          <?php $view->partial('partials.status-pill', ['value' => (string) $license['status']]); ?>
        </div>
      </div>
      <div class="panel__body">
        <div class="split split--wide-left" style="gap:var(--s-5);align-items:start">
          <div>
            <label class="field__label" for="key-<?= (int) $license['id'] ?>">Licence key</label>
            <div class="codeblock" style="padding:.7rem .9rem">
              <span id="key-<?= (int) $license['id'] ?>"><?= e($license['license_key']) ?></span>
              <button type="button" class="copy-btn" data-copy="key-<?= (int) $license['id'] ?>">Copy</button>
            </div>

            <div class="cluster mt-4">
              <a class="btn btn--sm btn--primary" href="<?= e(url('/client/downloads/' . $license['license_key'])) ?>">
                <?= icon('download') ?>Download licence file
              </a>
              <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/' . $license['slug'])) ?>">Product page</a>
              <a class="btn btn--sm btn--ghost" href="<?= e(url('/client/deployments')) ?>"><?= icon('rocket') ?>Deploy</a>
            </div>
          </div>

          <table class="spec-table">
            <tbody>
              <tr><th>Sites permitted</th><td><?= (int) $license['seats'] >= 999 ? 'Unlimited internal' : (int) $license['seats'] ?></td></tr>
              <tr><th>Registered domains</th><td><?= $domains ? e(implode(', ', $domains)) : 'None yet' ?></td></tr>
              <tr><th>Support until</th><td><?= e(human_date($license['support_until'], 'j F Y')) ?></td></tr>
              <tr><th>Issued</th><td><?= e(human_date($license['created_at'], 'j F Y')) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php $view->stop(); ?>
