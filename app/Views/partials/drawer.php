<?php
/** @var App\Core\Request $request @var array $nav @var App\Core\Auth $auth */
$current = $request->path;
?>
<div class="drawer" id="site-drawer" data-drawer aria-hidden="true">
  <div class="drawer__scrim" data-drawer-close></div>
  <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="Site navigation">
    <div class="drawer__head">
      <span class="logo">
        <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
        <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
      </span>
      <div class="cluster">
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch theme">
          <?= icon('moon', ['class' => 'i-moon']) ?><?= icon('sun', ['class' => 'i-sun']) ?>
        </button>
        <button type="button" class="burger" data-drawer-close aria-label="Close navigation" aria-expanded="true">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>

    <div class="drawer__body">
      <nav aria-label="Mobile">
        <?php foreach ($nav['primary'] as $index => $item): ?>
          <?php if (isset($item['mega'])): $panel = $nav['mega'][$item['mega']]; $id = 'drawer-sub-' . $index; ?>
            <button type="button" class="drawer__link" data-drawer-sub aria-expanded="false" aria-controls="<?= e($id) ?>">
              <?= e($item['label']) ?><?= icon('chevron-down') ?>
            </button>
            <div class="drawer__sub" id="<?= e($id) ?>">
              <ul>
                <li><a href="<?= e(url($item['path'])) ?>">All <?= e(strtolower($item['label'])) ?></a></li>
                <?php foreach ($panel['columns'] as $column): ?>
                  <?php foreach ($column['links'] as $link): ?>
                    <li><a href="<?= e(url($link['path'])) ?>"><?= e($link['label']) ?></a></li>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <a class="drawer__link" href="<?= e(url($item['path'])) ?>"
               <?= is_active($item['path'], $current) ? 'aria-current="page"' : '' ?>>
              <?= e($item['label']) ?><?= icon('chevron-right') ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="drawer__link" href="<?= e(url('/contact')) ?>">Contact<?= icon('chevron-right') ?></a>
      </nav>

      <div class="drawer__portals">
        <?php foreach ($nav['portals'] as $portal): ?>
          <a class="drawer__portal" href="<?= e(url($portal['path'])) ?>">
            <?= icon($portal['icon']) ?>
            <span>
              <strong><?= e($portal['label']) ?></strong>
              <span><?= e($portal['summary']) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="drawer__foot">
      <a class="btn btn--primary btn--block" href="<?= e(url('/contact')) ?>">Start a project</a>
      <a class="btn btn--ghost btn--block" href="<?= e(url('/marketplace')) ?>">Browse the Marketplace</a>
    </div>
  </div>
</div>
