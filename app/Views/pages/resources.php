<?php
/** @var App\Core\View $view @var array $items @var array $topics @var array $types @var array $filters @var int $total */
$view->extends('layouts.app');
$view->start('content');
$feature = $items[0] ?? null;
$rest = array_slice($items, 1);
$query = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides));
    return '/resources' . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Resources' => '/resources']]); ?>
      <div class="split split--wide-left" style="align-items:end">
        <div>
          <span class="eyebrow" data-seq style="--seq:0">Insights</span>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><span data-lines>Field notes from people who are still doing the work.</span></h1>
        </div>
        <p class="lede" data-seq style="--seq:2">
          Playbooks, essays and post-mortems on architecture, performance,
          delivery and design. No gated PDFs.
        </p>
      </div>

      <div class="cluster mt-7" data-seq style="--seq:3" role="group" aria-label="Filter articles">
        <a class="btn btn--sm <?= empty($filters['topic']) ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url($query(['topic' => null]))) ?>">All topics</a>
        <?php foreach ($topics as $topic): ?>
          <a class="btn btn--sm <?= (($filters['topic'] ?? '') === $topic) ? 'btn--solid' : 'btn--ghost' ?>"
             href="<?= e(url($query(['topic' => $topic]))) ?>"><?= e($topic) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="cluster mt-3" data-seq style="--seq:3" role="group" aria-label="Filter by format">
        <span class="tiny dim" style="margin-right:.25rem">Format</span>
        <a class="btn btn--sm <?= empty($filters['type']) ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url($query(['type' => null]))) ?>">Any</a>
        <?php foreach ($types as $type): ?>
          <a class="btn btn--sm <?= (($filters['type'] ?? '') === $type) ? 'btn--solid' : 'btn--ghost' ?>"
             href="<?= e(url($query(['type' => $type]))) ?>"><?= e($type) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <?php if ($feature === null): ?>
      <div class="empty">
        <?= icon('book') ?>
        <h3>Nothing published under those filters yet</h3>
        <p>Try a different topic or format — or subscribe and we will send the next one.</p>
        <a class="btn btn--ghost" href="<?= e(url('/resources')) ?>">Clear filters</a>
      </div>
    <?php else: ?>
      <article class="card card--flush spotlight edge-light a-<?= e($feature['accent']) ?>" data-reveal>
        <div class="split split--wide-right" style="gap:0">
          <div style="padding:clamp(1.5rem,1rem + 2vw,2.75rem);display:flex;flex-direction:column;justify-content:center">
            <div class="cluster" style="gap:.4rem">
              <span class="badge"><?= e($feature['type']) ?></span>
              <span class="badge badge--neutral"><?= e($feature['topic']) ?></span>
            </div>
            <h2 class="h2 mt-4"><a href="<?= e(url('/resources/' . $feature['slug'])) ?>"><?= e($feature['title']) ?></a></h2>
            <p class="lede mt-4"><?= e($feature['excerpt']) ?></p>
            <div class="cluster mt-6">
              <span class="avatar avatar--sm"><?= e(initials((string) $feature['author'])) ?></span>
              <div>
                <strong class="small"><?= e($feature['author']) ?></strong>
                <span class="tiny dim" style="display:block"><?= e($feature['author_role']) ?></span>
              </div>
              <span class="tiny dim" style="margin-left:auto"><?= (int) $feature['read_minutes'] ?> min · <?= e(human_date($feature['published_at'])) ?></span>
            </div>
            <div class="mt-6">
              <a class="btn btn--primary" href="<?= e(url('/resources/' . $feature['slug'])) ?>">Read it<?= icon('arrow-right') ?></a>
            </div>
          </div>
          <a href="<?= e(url('/resources/' . $feature['slug'])) ?>" style="display:block">
            <?= art_mockup($feature['slug'], 'editorial', ['label' => $feature['title']]) ?>
          </a>
        </div>
      </article>

      <div class="between mt-7" data-reveal>
        <h2 class="h4">All articles</h2>
        <span class="mk-count"><?= (int) $total ?> published</span>
      </div>

      <div class="cols-3 mt-5">
        <?php foreach ($rest as $i => $item): ?>
          <article class="card card--lift spotlight edge-light a-<?= e($item['accent']) ?>" data-reveal="<?= $i * 50 ?>">
            <div class="cluster" style="gap:.4rem">
              <span class="badge badge--neutral"><?= e($item['type']) ?></span>
              <span class="badge badge--neutral"><?= e($item['topic']) ?></span>
            </div>
            <h3 class="h4 mt-4"><a href="<?= e(url('/resources/' . $item['slug'])) ?>"><?= e($item['title']) ?></a></h3>
            <p class="small muted mt-3"><?= e($item['excerpt']) ?></p>
            <div class="feature__foot">
              <span><?= e($item['author']) ?></span>
              <span><?= (int) $item['read_minutes'] ?> min read</span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Want this applied to your platform?',
  'body' => 'Everything published here comes out of real engagements. Bring us the problem and we will tell you which of it applies to you.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/work')) ?>">Case studies</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to us</a>
</div>
<?php $view->stop(); ?>
