<?php
/** @var App\Core\View $view @var array $document @var array $documents @var string $slug */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Legal' => '/legal/privacy', $document['title'] => '/legal/' . $slug]]); ?>
      <span class="eyebrow" data-reveal>Legal</span>
      <h1 class="h1 hero__title" data-reveal="60"><?= e($document['title']) ?></h1>
      <p class="lede hero__lede" data-reveal="120"><?= e($document['summary']) ?></p>
      <p class="small dim mt-5">Last updated <?= e(human_date($document['updated'], 'j F Y')) ?></p>
    </div>
  </div>
</section>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="split split--wide-right" style="align-items:start">
      <aside data-reveal>
        <div class="card" style="position:sticky;top:calc(var(--header-h) + 1.5rem)">
          <h2 class="small" style="text-transform:uppercase;letter-spacing:var(--ls-eyebrow);color:var(--ink-3)">Documents</h2>
          <div class="sidenav mt-4">
            <?php foreach ($documents as $key => $doc): ?>
              <a href="<?= e(url('/legal/' . $key)) ?>" <?= $key === $slug ? 'aria-current="page"' : '' ?>>
                <?= icon('file') ?><?= e($doc['title']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
      <div class="prose" data-reveal="60"><?= $document['body'] ?></div>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
