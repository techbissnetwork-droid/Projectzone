<?php
/**
 * Shared shell for the admin, staff and client portals.
 *
 * @var App\Core\Seo $seo @var App\Core\View $view @var App\Core\Auth $auth
 * @var string $portal @var array $sidenav @var string $title @var string $subtitle
 */
$user = $auth->user() ?? ['name' => 'Account', 'role' => 'client', 'avatar_color' => 'blue'];
$accent = ['admin' => 'blue', 'staff' => 'teal', 'client' => 'violet'][$portal] ?? 'blue';
$portalLabel = App\Core\Auth::PORTALS[$portal]['label'] ?? 'Portal';
$current = $request->path;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo->title()) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#06080d">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<style><?= inline_file('assets/css/critical.css') ?></style>
<link rel="preload" as="style" href="<?= e(asset('assets/css/main.css')) ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>"></noscript>
<script>(function(){var d=document.documentElement;d.classList.add('js');try{var t=localStorage.getItem('tb-theme');if(t==='light'||t==='dark'){d.setAttribute('data-theme',t)}}catch(e){}})();</script>
</head>
<body class="a-<?= e($accent) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<div class="shell">
  <aside class="shell__side">
    <a class="logo" href="<?= e(url('/')) ?>">
      <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
      <span class="logo__text">TECHBISS<small><?= e(strtoupper($portal)) ?></small></span>
    </a>

    <nav class="sidenav" aria-label="<?= e($portalLabel) ?>">
      <?php foreach ($sidenav as $group => $links): ?>
        <div class="sidenav__group">
          <h4><?= e($group) ?></h4>
          <?php foreach ($links as $link): ?>
            <a href="<?= e(url($link['path'])) ?>"
               <?= is_active($link['path'], $current) ? 'aria-current="page"' : '' ?>>
              <?= icon($link['icon']) ?><?= e($link['label']) ?>
              <?php if (!empty($link['count'])): ?><span class="count"><?= e($link['count']) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <div style="margin-top:auto;padding-top:var(--s-5);border-top:1px solid var(--line)">
      <div class="cluster" style="gap:.6rem">
        <span class="avatar"><?= e(initials((string) $user['name'])) ?></span>
        <div style="min-width:0">
          <strong class="small" style="display:block;overflow:hidden;text-overflow:ellipsis"><?= e($user['name']) ?></strong>
          <span class="tiny dim"><?= e(ucfirst((string) $user['role'])) ?></span>
        </div>
      </div>
      <form method="post" action="<?= e(url('/' . $portal . '/logout')) ?>" class="mt-3">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm btn--block" type="submit">Sign out</button>
      </form>
    </div>
  </aside>

  <div class="shell__main">
    <header class="shell__top">
      <a class="logo u-only@lg" href="<?= e(url('/')) ?>" style="font-size:.95rem">
        <span class="logo__mark" style="width:26px;height:26px"><?= icon('layers', ['stroke' => 1.8]) ?></span>
      </a>
      <div style="min-width:0;flex:1">
        <h1 class="shell__title"><?= e($title) ?></h1>
        <?php if (!empty($subtitle)): ?><p class="shell__sub"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch theme">
        <?= icon('moon', ['class' => 'i-moon']) ?><?= icon('sun', ['class' => 'i-sun']) ?>
      </button>
      <span class="userchip u-only@md">
        <span class="avatar avatar--sm"><?= e(initials((string) $user['name'])) ?></span>
        <span>
          <strong style="display:block"><?= e(explode(' ', (string) $user['name'])[0]) ?></strong>
          <span><?= e($portalLabel) ?></span>
        </span>
      </span>
    </header>

    <nav class="shell__tabs" aria-label="<?= e($portalLabel) ?> sections">
      <?php foreach ($sidenav as $links): ?>
        <?php foreach ($links as $link): ?>
          <a href="<?= e(url($link['path'])) ?>" <?= is_active($link['path'], $current) ? 'aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <main id="main" class="shell__content">
      <?php $view->partial('partials.flash', ['flash' => $flash]); ?>
      <?= $view->section('content') ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
