<?php
/** @var App\Core\View $view @var array $scan @var array $state @var array $meta @var string|null $previous @var array $flash */
$view->extends('layouts.install');
$view->start('content');
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?> Every absolute URL in imported content is rewritten from the old origin to the new one.</p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <form method="post" action="<?= e(url('/install/step/migration')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="field">
        <label class="field__label" for="old_url">
          Old site URL
          <span class="field__hint">The origin that appears inside your exported content</span>
        </label>
        <input class="input" type="url" id="old_url" name="old_url" placeholder="https://old-site.example"
               value="<?= e((string) ($state['old_url'] ?? '')) ?>">
      </div>

      <div class="field">
        <label class="field__label" for="import_file">
          Content export
          <span class="field__hint">JSON or CSV, up to 20 MB</span>
        </label>
        <input class="input" type="file" id="import_file" name="import_file" accept=".json,.csv,application/json,text/csv"
               style="padding:.55rem .9rem">
      </div>

      <div class="alert alert--info mt-4">
        <?= icon('info') ?>
        <div>
          <strong>Expected shape</strong>
          <p>
            A JSON array of records, or an object with a <code>resources</code>, <code>posts</code> or
            <code>items</code> key. Each record needs a <code>title</code>; <code>slug</code>,
            <code>body</code>, <code>excerpt</code>, <code>topic</code>, <code>author</code> and
            <code>published_at</code> are used when present. WordPress export field names
            (<code>post_title</code>, <code>post_content</code>, <code>post_name</code>,
            <code>post_date</code>) are recognised too.
          </p>
        </div>
      </div>

      <div class="codeblock mt-4"><span class="c">// example.json</span>
[
  {
    "title": "Why we replatformed",
    "slug": "why-we-replatformed",
    "topic": "Engineering",
    "author": "A. Okonkwo",
    "published_at": "2026-04-12",
    "body": "&lt;p&gt;Links to https://old-site.example/about are rewritten.&lt;/p&gt;"
  }
]</div>

      <h2 class="h5 mt-7">What the migration step does</h2>
      <div class="checklist mt-3">
        <?php foreach ([
          ['Imports content records', 'Creates a resource entry per record, generating a unique slug where one collides.'],
          ['Rewrites absolute URLs', 'Every occurrence of the old origin in body and excerpt content becomes the new one.'],
          ['Preserves publication dates', 'So archive ordering and structured data stay correct.'],
          ['Estimates reading time', 'Derived from word count where the export does not supply one.'],
          ['Leaves your old site alone', 'Nothing is deleted or modified at the source.'],
        ] as $row): ?>
          <div class="checkrow checkrow--pass">
            <span class="checkrow__icon"><?= icon('check') ?></span>
            <span><strong><?= e($row[0]) ?></strong><span><?= e($row[1]) ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="install-card__foot" style="margin:var(--s-6) calc(var(--s-6) * -1) calc(var(--s-6) * -1)">
        <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
        <div class="cluster">
          <a class="btn btn--quiet" href="<?= e(url('/install/step/configuration')) ?>">Skip import</a>
          <button class="btn btn--primary" type="submit">Continue<?= icon('arrow-right') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
