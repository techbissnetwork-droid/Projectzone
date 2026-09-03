<?php
/** @var App\Core\View $view @var array $item @var array $related */
$view->extends('layouts.app');
$view->start('content');
?>
<article class="a-<?= e($item['accent']) ?>">
  <section class="hero">
    <div class="aura"></div>
    <div class="container container--wide">
      <div class="hero__inner">
        <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Resources' => '/resources', $item['topic'] => '/resources?topic=' . rawurlencode((string) $item['topic'])]]); ?>
        <div style="max-width:62ch">
          <div class="cluster" data-seq style="--seq:0">
            <span class="badge"><?= e($item['type']) ?></span>
            <span class="badge badge--neutral"><?= e($item['topic']) ?></span>
          </div>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><?= e($item['title']) ?></h1>
          <p class="lede hero__lede" data-seq style="--seq:2"><?= e($item['excerpt']) ?></p>
          <div class="cluster mt-6" data-seq style="--seq:3">
            <span class="avatar"><?= e(initials((string) $item['author'])) ?></span>
            <div>
              <strong class="small"><?= e($item['author']) ?></strong>
              <span class="tiny dim" style="display:block"><?= e($item['author_role']) ?></span>
            </div>
            <span class="tiny dim" style="margin-left:auto">
              <?= e(human_date($item['published_at'], 'j F Y')) ?> · <?= (int) $item['read_minutes'] ?> min read
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <span data-actionbar-after aria-hidden="true"></span>

  <section class="section section--flush-top">
    <div class="container container--wide">
      <div class="split split--wide-left" style="align-items:start">
        <div class="prose" data-reveal><?= $item['body'] ?></div>

        <aside class="stack" style="--flow:var(--s-4)">
          <div class="card" data-reveal style="position:sticky;top:calc(var(--header-h) + 1.5rem)">
            <h2 class="h5">Work with the author’s team</h2>
            <p class="small muted mt-3">
              This came out of live engagements. If the same problem is on your
              roadmap, an architect can tell you what it takes to fix.
            </p>
            <a class="btn btn--primary btn--sm btn--block mt-4" href="<?= e(url('/contact')) ?>">Talk to an architect</a>
            <a class="btn btn--ghost btn--sm btn--block mt-3" href="<?= e(url('/resources')) ?>">More insights</a>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <?php if ($related): ?>
    <section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
      <div class="container container--wide">
        <h2 class="h4" data-reveal>Related reading</h2>
        <div class="cols-3 mt-5">
          <?php foreach ($related as $other): ?>
            <article class="card card--lift a-<?= e($other['accent']) ?>">
              <span class="badge badge--neutral"><?= e($other['type']) ?></span>
              <h3 class="h4 mt-3"><a href="<?= e(url('/resources/' . $other['slug'])) ?>"><?= e($other['title']) ?></a></h3>
              <p class="small muted mt-3"><?= e($other['excerpt']) ?></p>
              <div class="feature__foot"><span><?= e($other['author']) ?></span><span><?= (int) $other['read_minutes'] ?> min</span></div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</article>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/resources')) ?>">All insights</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to us</a>
</div>
<?php $view->stop(); ?>
