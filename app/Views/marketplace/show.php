<?php
/** @var App\Core\View $view @var array $product @var array $related @var int $cartCount */
$view->extends('layouts.app');
$view->start('content');
$tiers = App\Models\Product::TIERS;
$categories = App\Models\Product::CATEGORIES;
$P = App\Models\Product::class;
?>
<section class="hero" style="padding-bottom:var(--s-6)">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => [
        'Home' => '/',
        'Marketplace' => '/marketplace',
        ($categories[$product['category']] ?? 'Products') => '/marketplace?category=' . $product['category'],
        $product['name'] => '/marketplace/' . $product['slug'],
      ]]); ?>

      <div style="max-width:64ch">
        <div class="cluster" data-reveal>
          <span class="badge"><?= e($categories[$product['category']] ?? ucfirst((string) $product['category'])) ?></span>
          <span class="badge badge--neutral">v<?= e($product['version']) ?></span>
          <span class="stars">
            <?= icon('star', ['fill' => true]) ?><?= number_format((float) $product['rating'], 1) ?>
            <span>(<?= number_format((int) $product['reviews_count']) ?> reviews)</span>
          </span>
        </div>
        <h1 class="h1 hero__title" data-reveal="60"><?= e($product['name']) ?></h1>
        <p class="lede hero__lede" data-reveal="120"><?= e($product['tagline']) ?></p>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="pd-layout">
      <div class="stack" style="--flow:var(--s-6)">
        <div class="pd-gallery" data-reveal>
          <div class="pd-gallery__stage" data-gallery-stage>
            <?= art_mockup($product['slug'], (string) $product['layout'], ['label' => $product['name'] . ' desktop preview']) ?>
          </div>
          <div class="pd-gallery__thumbs" role="tablist" aria-label="Preview screens">
            <?php foreach (['hero' => 'Home', 'grid' => 'Listing', 'dashboard' => 'Dashboard', 'editorial' => 'Content', 'commerce' => 'Commerce'] as $layout => $label): ?>
              <button type="button" data-gallery-thumb role="tab"
                      aria-selected="<?= $layout === $product['layout'] ? 'true' : 'false' ?>"
                      aria-label="<?= e($label) ?> preview">
                <?= art_mockup($product['slug'] . '-' . $layout, $layout, ['label' => $label . ' preview']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="cluster">
          <a class="btn btn--ghost" href="<?= e(url('/marketplace/preview/' . $product['slug'])) ?>">
            <?= icon('eye') ?>Live preview
          </a>
          <a class="btn btn--quiet" href="<?= e(url('/marketplace/installer')) ?>"><?= icon('rocket') ?>How deployment works</a>
        </div>

        <div data-reveal>
          <h2 class="h3">About this product</h2>
          <div class="prose mt-4" style="font-size:var(--t-0)">
            <?php foreach (preg_split('/\n\n+/', (string) $product['description']) ?: [] as $paragraph): ?>
              <p><?= e(trim($paragraph)) ?></p>
            <?php endforeach; ?>
          </div>
        </div>

        <div data-reveal>
          <h2 class="h3">What is included</h2>
          <div class="cols-2 mt-5">
            <?php foreach ($product['features'] as $feature): ?>
              <div class="card" style="padding:var(--s-4);display:flex;gap:.7rem;align-items:flex-start">
                <?= icon('check-circle', ['size' => 18]) ?>
                <span class="small"><?= e($feature) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="split" data-reveal>
          <div>
            <h2 class="h4">Specifications</h2>
            <table class="spec-table mt-4">
              <tbody>
                <?php foreach ($product['specs'] as $label => $value): ?>
                  <tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <h2 class="h4">Pages and screens</h2>
            <div class="cluster mt-4" style="gap:.35rem">
              <?php foreach ($product['pages'] as $page): ?>
                <span class="badge badge--neutral"><?= e($page) ?></span>
              <?php endforeach; ?>
            </div>
            <h2 class="h4 mt-6">Tags</h2>
            <div class="cluster mt-4" style="gap:.35rem">
              <?php foreach ($product['tags'] as $tag): ?>
                <a class="badge" href="<?= e(url('/marketplace?q=' . rawurlencode((string) $tag))) ?>"><?= e($tag) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <aside>
        <form class="pd-buy" method="post" action="<?= e(url('/marketplace/cart/add')) ?>" data-reveal="60">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

          <div class="pd-buy__price">
            <b><?= money((float) $product['price'], (string) $product['currency']) ?></b>
            <?php if (!empty($product['compare_price'])): ?>
              <del class="dim"><?= money((float) $product['compare_price'], (string) $product['currency']) ?></del>
            <?php endif; ?>
          </div>
          <p class="tiny dim" style="margin-top:-.6rem">One-time payment. No subscription.</p>

          <fieldset style="border:0;padding:0;margin:0">
            <legend class="field__label" style="padding:0">Choose a licence</legend>
            <div class="stack" style="--flow:.5rem">
              <?php foreach ($tiers as $key => $tier): ?>
                <label class="licence">
                  <input type="radio" name="tier" value="<?= e($key) ?>" <?= $key === 'standard' ? 'checked' : '' ?>>
                  <span class="licence__box">
                    <span class="licence__row">
                      <strong><?= e($tier['label']) ?></strong>
                      <b><?= money($P::priceFor($product, $key), (string) $product['currency']) ?></b>
                    </span>
                    <span><?= e($tier['blurb']) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <button class="btn btn--primary btn--lg btn--block" type="submit">
            <?= icon('cart') ?>Add to cart
          </button>

          <ul class="pd-includes">
            <?php foreach ($product['includes'] as $line): ?>
              <li><?= icon('check') ?><span><?= e($line) ?></span></li>
            <?php endforeach; ?>
          </ul>

          <div style="padding-top:var(--s-4);border-top:1px solid var(--line)">
            <div class="between small">
              <span class="dim">Lighthouse</span>
              <strong><?= (int) $product['lighthouse'] ?> / 100</strong>
            </div>
            <div class="between small mt-3">
              <span class="dim">Deployments</span>
              <strong><?= number_format((int) $product['sales_count']) ?></strong>
            </div>
            <div class="between small mt-3">
              <span class="dim">Last updated</span>
              <strong><?= e(human_date($product['updated_at'])) ?></strong>
            </div>
          </div>

          <p class="tiny dim center">
            14-day refund if undeployed. <a href="<?= e(url('/marketplace/licensing')) ?>" style="color:var(--accent-2)">Licence terms</a>
          </p>
        </form>
      </aside>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <h2 class="h4" data-reveal>More in <?= e($categories[$product['category']] ?? 'this category') ?></h2>
    <div class="mk-grid mt-5">
      <?php foreach ($related as $item): ?>
        <?php $view->partial('partials.product-card', ['item' => $item]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $view->partial('partials.cta-band', [
  'title' => 'Need it customised?',
  'body' => 'Every marketplace product is a starting point we can extend. Tell us what has to change and we will scope it as a fixed-price engagement.',
  'primary' => ['label' => 'Talk to us', 'path' => '/contact?topic=marketplace&product=' . $product['slug']],
  'secondary' => ['label' => 'Back to catalogue', 'path' => '/marketplace'],
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace/preview/' . $product['slug'])) ?>">Preview</a>
  <a class="btn btn--primary" href="#main"><?= money((float) $product['price']) ?> · Buy</a>
</div>
<?php $view->stop(); ?>
