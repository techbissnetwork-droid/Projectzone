<?php
/** @var App\Core\View $view @var array $lines @var float $subtotal @var array|null $user @var array $flash */
$view->extends('layouts.app');
$view->start('content');
$tiers = App\Models\Product::TIERS;
?>
<section class="section" style="padding-top:clamp(2rem,1.5rem + 2vw,3.5rem)">
  <div class="container container--wide">
    <?php $view->partial('partials.crumbs', ['crumbs' => ['Marketplace' => '/marketplace', 'Cart' => '/marketplace/cart', 'Checkout' => '/marketplace/checkout']]); ?>
    <h1 class="h1">Checkout</h1>
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <div class="cart-layout mt-6">
      <form method="post" action="<?= e(url('/marketplace/checkout')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="panel">
          <div class="panel__head"><h2 class="h5">Your details</h2></div>
          <div class="panel__body">
            <div class="field-row">
              <div class="field">
                <label class="field__label" for="co-name">Full name <span class="req">*</span></label>
                <input class="input" type="text" id="co-name" name="name" required autocomplete="name"
                       value="<?= e(old('name', (string) ($user['name'] ?? ''))) ?>">
                <?php if ($error = error_for('name')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
              </div>
              <div class="field">
                <label class="field__label" for="co-email">Email <span class="req">*</span></label>
                <input class="input" type="email" id="co-email" name="email" required autocomplete="email"
                       value="<?= e(old('email', (string) ($user['email'] ?? ''))) ?>">
                <?php if ($error = error_for('email')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
                <span class="field__hint" style="display:block;margin-top:.35rem">Licence keys are sent here.</span>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label class="field__label" for="co-company">Company</label>
                <input class="input" type="text" id="co-company" name="company" autocomplete="organization"
                       value="<?= e(old('company', (string) ($user['company'] ?? ''))) ?>">
              </div>
              <div class="field">
                <label class="field__label" for="co-country">Country <span class="req">*</span></label>
                <select class="select" id="co-country" name="country" required>
                  <option value="">Select…</option>
                  <?php foreach (['United States', 'United Kingdom', 'Germany', 'France', 'Netherlands', 'United Arab Emirates', 'Singapore', 'Australia', 'Canada', 'Nigeria', 'India', 'Other'] as $country): ?>
                    <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($error = error_for('country')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="panel mt-4">
          <div class="panel__head"><h2 class="h5">Payment</h2></div>
          <div class="panel__body">
            <div class="mode-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
              <label class="mode">
                <input type="radio" name="payment_method" value="card" checked>
                <span class="mode__box">
                  <?= icon('lock') ?>
                  <strong>Card</strong>
                  <span>Settles immediately. Licence keys issued on this page.</span>
                </span>
              </label>
              <label class="mode">
                <input type="radio" name="payment_method" value="invoice">
                <span class="mode__box">
                  <?= icon('file') ?>
                  <strong>Invoice</strong>
                  <span>Net 30 for registered companies. Keys issue on payment.</span>
                </span>
              </label>
            </div>
            <ul class="pd-includes mt-5">
              <li><?= icon('lock') ?><span>Card details are handled by our payment provider and never touch our servers.</span></li>
              <li><?= icon('check') ?><span>Licence keys are issued the moment payment settles.</span></li>
              <li><?= icon('refresh') ?><span>Full refund within 14 days if the product has not been deployed.</span></li>
            </ul>
          </div>
        </div>

        <div class="panel mt-4">
          <div class="panel__body">
            <label class="check">
              <input type="checkbox" name="terms" value="1" required <?= old('terms') ? 'checked' : '' ?>>
              <span>
                I accept the <a href="<?= e(url('/marketplace/licensing')) ?>" style="color:var(--accent-2)">licence terms</a>
                and the <a href="<?= e(url('/legal/terms')) ?>" style="color:var(--accent-2)">terms of service</a>. <span class="req">*</span>
              </span>
            </label>
            <?php if ($error = error_for('terms')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
            <button class="btn btn--primary btn--lg btn--block mt-5" type="submit">
              Complete order · <?= money($subtotal) ?>
            </button>
          </div>
        </div>
      </form>

      <aside class="summary">
        <h2 class="h5">Order summary</h2>
        <?php foreach ($lines as $line): ?>
          <div class="summary__row">
            <span><?= e($line['product']['name']) ?><br><span class="tiny dim"><?= e($tiers[$line['tier']]['label']) ?> licence</span></span>
            <b><?= money($line['price']) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="summary__row" style="padding-top:.6rem;border-top:1px solid var(--line)"><span>Subtotal</span><b><?= money($subtotal) ?></b></div>
        <div class="summary__row"><span>Tax</span><b><?= money(0) ?></b></div>
        <div class="summary__row summary__row--total"><span>Total</span><b><?= money($subtotal) ?></b></div>
        <a class="btn btn--quiet btn--sm btn--block mt-3" href="<?= e(url('/marketplace/cart')) ?>">Edit cart</a>
      </aside>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
