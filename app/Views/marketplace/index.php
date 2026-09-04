<?php
/** @var App\Core\View $view @var array $items @var int $total @var array $filters @var array $counts
 *  @var int $page @var int $pages @var int $cartCount @var array $flash */
$view->extends('layouts.app');
$view->start('content');
$categories = App\Models\Product::CATEGORIES;
$sorts = App\Models\Product::SORTS;
$activeCategory = $filters['category'] ?? '';
$link = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides), static fn ($v) => $v !== '' && $v !== null);
    return '/marketplace' . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="hero" style="padding-bottom:var(--s-6)">
  <div class="aura"></div>
  <div class="grid-lines"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Marketplace' => '/marketplace']]); ?>
      <div class="split split--wide-left" style="align-items:end">
        <div>
          <span class="eyebrow" data-reveal>Marketplace</span>
          <h1 class="h1 hero__title" data-reveal="60">Deploy-ready platforms, built to our engagement standard.</h1>
        </div>
        <p class="lede" data-reveal="120">
          Preview live, licence in a minute, then deploy with the Advanced
          Installer — URL detection, migration and configuration included.
        </p>
      </div>

      <div class="cluster mt-6" data-reveal="160">
        <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/installer')) ?>"><?= icon('rocket') ?>Advanced Installer</a>
        <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/licensing')) ?>"><?= icon('file') ?>Licensing</a>
        <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/cart')) ?>">
          <?= icon('cart') ?>Cart<?= $cartCount > 0 ? ' (' . (int) $cartCount . ')' : '' ?>
        </a>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <div class="mk-layout mt-5">
      <aside>
        <button type="button" class="btn btn--ghost btn--block mk-filters-toggle" data-filters-toggle aria-expanded="false">
          <?= icon('filter') ?>Filters<?= $activeCategory !== '' ? ' · 1 active' : '' ?>
        </button>
        <div class="mk-filters mt-3" data-filters>
          <div class="mk-filters__group">
            <h3>Category</h3>
            <div class="mk-filters__list">
              <a href="<?= e(url($link(['category' => null, 'page' => null]))) ?>" <?= $activeCategory === '' ? 'aria-current="true"' : '' ?>>
                All products <small><?= array_sum($counts) ?></small>
              </a>
              <?php foreach ($categories as $key => $label): ?>
                <a href="<?= e(url($link(['category' => $key, 'page' => null]))) ?>" <?= $activeCategory === $key ? 'aria-current="true"' : '' ?>>
                  <?= e($label) ?> <small><?= (int) ($counts[$key] ?? 0) ?></small>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mk-filters__group">
            <h3>Price</h3>
            <div class="mk-filters__list">
              <?php foreach (['' => 'Any price', '200' => 'Under $200', '400' => 'Under $400', '600' => 'Under $600'] as $value => $label): ?>
                <a href="<?= e(url($link(['max' => $value ?: null, 'page' => null]))) ?>"
                   <?= (string) ($filters['max'] ?? '') === (string) $value ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mk-filters__group">
            <h3>Every product includes</h3>
            <ul class="feature__list" style="padding-top:0">
              <?php foreach (['Full source code', 'Advanced Installer', 'Figma design system', '12 months of updates', 'Migration tooling'] as $line): ?>
                <li><?= icon('check') ?><span><?= e($line) ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </aside>

      <div>
        <form class="mk-toolbar" method="get" action="<?= e(url('/marketplace')) ?>"
              data-search-form data-search-endpoint="<?= e(url('/api/marketplace/search')) ?>">
          <?php if ($activeCategory !== ''): ?>
            <input type="hidden" name="category" value="<?= e($activeCategory) ?>">
          <?php endif; ?>
          <div class="mk-search">
            <?= icon('search') ?>
            <label class="sr-only" for="mk-q">Search the marketplace</label>
            <input class="input" type="search" id="mk-q" name="q" value="<?= e($filters['q'] ?? '') ?>"
                   placeholder="Search products, tags or stack…" autocomplete="off">
          </div>
          <div>
            <label class="sr-only" for="mk-sort">Sort</label>
            <select class="select" id="mk-sort" name="sort" onchange="this.form.submit()">
              <?php foreach ($sorts as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['sort'] ?? 'featured') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <noscript><button class="btn btn--ghost" type="submit">Apply</button></noscript>
          <span class="mk-count" data-search-count><?= (int) $total ?> <?= $total === 1 ? 'product' : 'products' ?></span>
        </form>

        <div class="mk-grid" data-search-results>
          <?php if ($items === []): ?>
            <?php $view->partial('marketplace.empty', ['filters' => $filters]); ?>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <?php $view->partial('partials.product-card', ['item' => $item]); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
          <nav class="pager" aria-label="Pagination">
            <?php if ($page > 1): ?>
              <a href="<?= e(url($link(['page' => $page - 1]))) ?>" rel="prev">Previous</a>
            <?php else: ?>
              <span aria-disabled="true">Previous</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $pages; $p++): ?>
              <?php if ($p === $page): ?>
                <span aria-current="page"><?= $p ?></span>
              <?php else: ?>
                <a href="<?= e(url($link(['page' => $p === 1 ? null : $p]))) ?>"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
              <a href="<?= e(url($link(['page' => $page + 1]))) ?>" rel="next">Next</a>
            <?php else: ?>
              <span aria-disabled="true">Next</span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <div class="split split--wide-left">
      <div data-reveal>
        <span class="eyebrow">Deployment</span>
        <h2 class="h2 mt-3">Every product installs the same way.</h2>
        <p class="lede mt-4">
          Whichever product you choose, putting it live is the same short,
          guided job — and it is the same one our own engineers use. No terminal,
          no developer, and nothing to unpick if you change your mind.
        </p>
        <div class="cluster mt-6">
          <a class="btn btn--primary" href="<?= e(url('/marketplace/installer')) ?>">How the installer works<?= icon('arrow-right') ?></a>
        </div>
      </div>
      <div class="cols-2" data-reveal="80">
        <?php foreach ([
          ['zap', 'Live in about ten minutes'], ['settings', 'No terminal needed'],
          ['server', 'Runs on ordinary hosting'], ['refresh', 'Your content moves with you'],
          ['search', 'Old links keep working'], ['lock', 'Closes the door behind it'],
        ] as $item): ?>
          <div class="card" style="padding:var(--s-4);display:flex;gap:.7rem;align-items:center">
            <?= icon($item[0], ['size' => 18]) ?><span class="small"><?= e($item[1]) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace/cart')) ?>"><?= icon('cart') ?>Cart<?= $cartCount > 0 ? ' (' . (int) $cartCount . ')' : '' ?></a>
  <a class="btn btn--primary" href="<?= e(url('/marketplace/installer')) ?>">Installer</a>
</div>
<?php $view->stop(); ?>
