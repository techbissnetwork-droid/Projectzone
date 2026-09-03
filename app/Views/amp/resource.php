<?php
/** @var App\Core\View $view @var array $item */
$view->extends('layouts.amp');
$view->start('content');
?>
<article>
  <section class="hero">
    <div class="wrap">
      <span class="tag"><?= e($item['type']) ?></span><span class="tag"><?= e($item['topic']) ?></span>
      <h1 style="margin-top:12px"><?= e($item['title']) ?></h1>
      <p class="lede"><?= e($item['excerpt']) ?></p>
      <div class="byline">
        <span class="avatar"><?= e(initials((string) $item['author'])) ?></span>
        <span><?= e($item['author']) ?> · <?= e(human_date($item['published_at'], 'j F Y')) ?> · <?= (int) $item['read_minutes'] ?> min read</span>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="prose"><?= $item['body'] ?></div>
      <a class="btn btn-primary full" href="<?= e(url('/contact')) ?>">Talk to the author’s team</a>
    </div>
  </section>
</article>
<?php $view->stop(); ?>
