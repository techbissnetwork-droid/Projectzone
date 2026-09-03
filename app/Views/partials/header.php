<?php
/** @var App\Core\Request $request @var array $nav @var App\Core\Auth $auth */
$current = $request->path;
$portalHome = $auth->check() ? (App\Core\Auth::PORTALS[$auth->portal()]['home'] ?? '/') : null;
?>
<header class="header" data-header>
  <div class="container container--wide">
    <div class="header__bar">
      <a class="logo" href="<?= e(url('/')) ?>" aria-label="TECHBISS home">
        <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
        <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
      </a>

      <nav class="nav" aria-label="Primary">
        <?php foreach ($nav['primary'] as $item): ?>
          <?php if (isset($item['mega'])): ?>
            <button type="button" class="nav__link" data-mega-trigger="<?= e($item['mega']) ?>"
                    aria-expanded="false" aria-haspopup="true">
              <?= e($item['label']) ?><?= icon('chevron-down', ['class' => 'nav__chev']) ?>
            </button>
          <?php else: ?>
            <a class="nav__link" href="<?= e(url($item['path'])) ?>"
               <?= is_active($item['path'], $current) ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>

      <span class="header__spacer"></span>

      <button type="button" class="theme-toggle u-only@md" data-theme-toggle aria-label="Switch theme">
        <?= icon('moon', ['class' => 'i-moon']) ?><?= icon('sun', ['class' => 'i-sun']) ?>
      </button>

      <?php if ($portalHome !== null): ?>
        <a class="btn btn--sm btn--ghost u-only@lg" href="<?= e(url($portalHome)) ?>">
          <?= icon('grid') ?> Dashboard
        </a>
      <?php else: ?>
        <a class="btn btn--sm btn--quiet u-only@lg" href="<?= e(url('/client/login')) ?>">Sign in</a>
      <?php endif; ?>

      <a class="btn btn--sm btn--primary u-only@md" href="<?= e(url('/contact')) ?>">
        Start a project
      </a>

      <button type="button" class="burger" data-drawer-open aria-expanded="false"
              aria-controls="site-drawer" aria-label="Open navigation">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <?php foreach ($nav['mega'] as $key => $panel): ?>
    <div class="mega" data-mega="<?= e($key) ?>">
      <div class="container container--wide">
        <div class="mega__panel">
          <div class="mega__intro">
            <h3><?= e($panel['heading']) ?></h3>
            <p><?= e($panel['blurb']) ?></p>
          </div>
          <div class="mega__cols">
            <?php foreach ($panel['columns'] as $column): ?>
              <div class="mega__col">
                <h4><?= e($column['title']) ?></h4>
                <?php foreach ($column['links'] as $link): ?>
                  <a class="mega__link" href="<?= e(url($link['path'])) ?>">
                    <strong><?= e($link['label']) ?></strong>
                    <span><?= e($link['meta']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mega__feature">
            <span class="eyebrow eyebrow--plain"><?= e($panel['feature']['eyebrow']) ?></span>
            <h4><?= e($panel['feature']['title']) ?></h4>
            <p><?= e($panel['feature']['body']) ?></p>
            <a class="btn btn--sm btn--ghost" href="<?= e(url($panel['feature']['cta']['path'])) ?>">
              <?= e($panel['feature']['cta']['label']) ?><?= icon('arrow-right') ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</header>
