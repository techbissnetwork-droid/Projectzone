<?php
/** @var App\Core\View $view @var array $order @var array $items @var array $licenses @var array $flash */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="section" style="padding-top:clamp(2rem,1.5rem + 2vw,3.5rem)">
  <div class="container container--wide">
    <div class="center" style="max-width:60ch;margin-inline:auto">
      <span class="feature__icon" style="margin-inline:auto;width:52px;height:52px;border-radius:15px">
        <?= icon('check', ['size' => 24, 'stroke' => 2]) ?>
      </span>
      <h1 class="h1 mt-5">Order <?= e($order['reference']) ?> confirmed</h1>
      <p class="lede mt-4" style="margin-inline:auto">
        <?php if ($order['status'] === 'pending'): ?>
          We have recorded your order and emailed an invoice to <?= e($order['customer_email']) ?>.
          Licence keys below activate once payment clears.
        <?php else: ?>
          Your licence keys are below and on their way to <?= e($order['customer_email']) ?>.
          Each one activates the Advanced Installer for that product.
        <?php endif; ?>
      </p>
    </div>

    <div class="cart-layout mt-7">
      <div class="stack" style="--flow:var(--s-4)">
        <div class="panel">
          <div class="panel__head">
            <h2 class="h5">Your licences</h2>
            <span class="badge <?= $order['status'] === 'paid' ? 'badge--ok' : 'badge--warn' ?>"><?= e(ucfirst((string) $order['status'])) ?></span>
          </div>
          <div class="panel__body" style="padding-block:var(--s-4)">
            <?php foreach ($licenses as $license): ?>
              <div style="padding:var(--s-4) 0;border-bottom:1px solid var(--line)">
                <div class="between">
                  <div>
                    <strong><?= e($license['product_name']) ?></strong>
                    <div class="tiny dim mt-3">
                      <?= e(ucfirst((string) $license['tier'])) ?> licence ·
                      <?= (int) $license['seats'] >= 999 ? 'Unlimited' : (int) $license['seats'] ?> site<?= (int) $license['seats'] === 1 ? '' : 's' ?> ·
                      Support until <?= e(human_date($license['support_until'])) ?>
                    </div>
                  </div>
                  <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/' . $license['slug'])) ?>">Product</a>
                </div>
                <div class="codeblock mt-4" style="padding:.7rem .9rem">
                  <span id="key-<?= (int) $license['id'] ?>"><?= e($license['license_key']) ?></span>
                  <button type="button" class="copy-btn" data-copy="key-<?= (int) $license['id'] ?>">Copy</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel">
          <div class="panel__head"><h2 class="h5">Next: deploy it</h2></div>
          <div class="panel__body">
            <ol class="stack" style="--flow:1rem;counter-reset:s">
              <?php foreach ([
                ['Download the package', 'Your licence gives access to the full source and the Advanced Installer package.'],
                ['Upload to your server', 'Any host running PHP 8.1+ with PDO. Point the document root at the public directory.'],
                ['Open /install in a browser', 'The installer detects your URL, scans for an existing site, and tests your database live.'],
                ['Choose clean install or migrate', 'If it finds WordPress or another platform, it offers to import content and rewrite URLs.'],
                ['Configure and finish', 'Name the site, create the owner account, and the installer locks itself when it is done.'],
              ] as $i => $step): ?>
                <li style="display:flex;gap:.85rem;align-items:flex-start">
                  <span class="wstep__num" style="margin-top:.15rem"><?= $i + 1 ?></span>
                  <span>
                    <strong style="font-size:var(--t-0)"><?= e($step[0]) ?></strong>
                    <span class="small dim" style="display:block;margin-top:.2rem"><?= e($step[1]) ?></span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ol>
            <div class="cluster mt-6">
              <a class="btn btn--primary" href="<?= e(url('/marketplace/installer')) ?>"><?= icon('rocket') ?>Installer guide</a>
              <a class="btn btn--ghost" href="<?= e(url('/client/login')) ?>">Client portal</a>
            </div>
          </div>
        </div>
      </div>

      <aside class="summary">
        <h2 class="h5">Receipt</h2>
        <div class="summary__row"><span>Reference</span><b><?= e($order['reference']) ?></b></div>
        <div class="summary__row"><span>Date</span><b><?= e(human_date($order['created_at'])) ?></b></div>
        <div class="summary__row"><span>Method</span><b><?= e(ucfirst((string) $order['payment_method'])) ?></b></div>
        <?php foreach ($items as $item): ?>
          <div class="summary__row" style="padding-top:.6rem;border-top:1px solid var(--line)">
            <span><?= e($item['product_name']) ?><br><span class="tiny dim"><?= e(ucfirst((string) $item['license_tier'])) ?></span></span>
            <b><?= money((float) $item['unit_price'], (string) $order['currency']) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="summary__row"><span>Tax</span><b><?= money((float) $order['tax'], (string) $order['currency']) ?></b></div>
        <div class="summary__row summary__row--total"><span>Total</span><b><?= money((float) $order['total'], (string) $order['currency']) ?></b></div>
        <p class="tiny dim mt-3">
          Keep this reference for support. Sign in to the client portal to manage
          licences, register domains and track deployments.
        </p>
      </aside>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
