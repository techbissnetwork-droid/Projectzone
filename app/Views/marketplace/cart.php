<?php
/** @var App\Core\View $view @var array $lines @var float $subtotal @var array $flash */
$view->extends('layouts.app');
$view->start('content');
$tiers = App\Models\Product::TIERS;
?>
<section class="section" style="padding-top:clamp(2rem,1.5rem + 2vw,3.5rem)">
  <div class="container container--wide">
    <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Marketplace' => '/marketplace', 'Cart' => '/marketplace/cart']]); ?>
    <h1 class="h1">Your cart</h1>
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <?php if ($lines === []): ?>
      <div class="empty mt-6">
        <?= icon('cart') ?>
        <h3>Your cart is empty</h3>
        <p>Browse the catalogue and add a licence — every product deploys with the Advanced Installer.</p>
        <a class="btn btn--primary" href="<?= e(url('/marketplace')) ?>">Browse the Marketplace</a>
      </div>
    <?php else: ?>
      <div class="cart-layout mt-6">
        <div class="panel">
          <div class="panel__head">
            <h2 class="h5"><?= count($lines) ?> <?= count($lines) === 1 ? 'licence' : 'licences' ?></h2>
            <a class="btn btn--sm btn--quiet" href="<?= e(url('/marketplace')) ?>">Continue shopping</a>
          </div>
          <div class="panel__body" style="padding-block:0">
            <?php foreach ($lines as $line): ?>
              <div class="cart-line">
                <span class="cart-line__thumb"><?= art_tile($line['product']['slug'], initials((string) $line['product']['name'])) ?></span>
                <div>
                  <strong><a href="<?= e(url('/marketplace/' . $line['product']['slug'])) ?>"><?= e($line['product']['name']) ?></a></strong>
                  <span><?= e($tiers[$line['tier']]['label']) ?> licence · v<?= e($line['product']['version']) ?></span>
                </div>
                <div style="text-align:right">
                  <strong style="font-variant-numeric:tabular-nums"><?= money($line['price']) ?></strong>
                  <form method="post" action="<?= e(url('/marketplace/cart/remove')) ?>" style="margin-top:.35rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $line['product']['id'] ?>">
                    <button class="btn btn--sm btn--quiet" type="submit">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <aside class="summary">
          <h2 class="h5">Order summary</h2>
          <div class="summary__row"><span>Subtotal</span><b><?= money($subtotal) ?></b></div>
          <div class="summary__row"><span>Tax</span><b>Calculated at checkout</b></div>
          <div class="summary__row summary__row--total"><span>Total</span><b><?= money($subtotal) ?></b></div>
          <a class="btn btn--primary btn--lg btn--block mt-4" href="<?= e(url('/marketplace/checkout')) ?>">
            Checkout<?= icon('arrow-right') ?>
          </a>
          <ul class="pd-includes mt-4">
            <li><?= icon('check') ?><span>Instant licence key delivery</span></li>
            <li><?= icon('check') ?><span>Advanced Installer with every product</span></li>
            <li><?= icon('check') ?><span>14-day refund if undeployed</span></li>
          </ul>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php $view->stop(); ?>
