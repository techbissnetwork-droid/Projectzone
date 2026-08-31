<?php /** Legal document layout — narrower measure, generated table of contents. @var array $page */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => (string) $page['eyebrow'],
    'heading' => (string) $page['title'],
    'lead'    => (string) $page['subtitle'],
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <p class="hint mb-6">Last updated <?= e(format_date($page['updated_at'], 'j F Y')) ?></p>
        <div class="prose" style="max-width:none"><?= $page['content'] ?></div>

        <hr class="hairline mt-8">
        <div class="row row--between mt-5">
            <span class="hint">Questions about this document?</span>
            <a class="link" href="<?= e(url('/contact')) ?>">Contact us<?= icon('arrow-right') ?></a>
        </div>
    </div>
</section>
