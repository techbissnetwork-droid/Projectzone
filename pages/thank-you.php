<?php /** @var string $reference @var array $steps @var array $posts */ ?>
<section class="section" style="padding-top:clamp(4rem,3rem+4vw,7rem)">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern"></span></div>
    <div class="container container--narrow text-center">
        <span class="icon-plate icon-plate--lg" style="margin-inline:auto;background:rgba(52,211,153,.12);color:var(--success);border-color:transparent">
            <?= icon('check') ?>
        </span>
        <h1 class="mt-6">Thank you — we have it.</h1>
        <p class="lead mt-4">
            A member of the team will read your request properly and reply personally, usually within one business day.
        </p>

        <?php if ($reference !== ''): ?>
        <div class="card mt-6" style="display:inline-flex;flex-direction:row;align-items:center;gap:1rem;padding:.85rem 1.15rem">
            <span class="hint">Your reference</span>
            <strong class="mono" style="font-size:1rem;color:var(--text)"><?= e($reference) ?></strong>
            <button class="btn btn--quiet btn--sm" type="button" data-copy="<?= e($reference) ?>" aria-label="Copy reference"><?= icon('copy') ?></button>
        </div>
        <p class="hint mt-3">Quote this reference in any follow-up and we will find your request immediately.</p>
        <?php endif; ?>

        <div class="row row--center mt-8">
            <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Back to the homepage</a>
            <a class="btn btn--quiet" href="<?= e(url('/portfolio')) ?>">Browse our work</a>
        </div>
    </div>
</section>

<?php if ($steps): ?>
<section class="section section--tight">
    <div class="container">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">What happens next</p>
            <h2 class="mt-4">The first three stages</h2>
        </div>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($steps as $i => $step): ?>
            <div class="card" style="--i:<?= $i ?>" data-reveal>
                <div class="process__num" style="font-size:1.4rem"><?= e($step['step_number']) ?></div>
                <h3 class="card__title mt-3"><?= e($step['title']) ?></h3>
                <p class="card__text"><?= e(str_limit($step['description'], 130)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<section class="section section--tight">
    <div class="container">
        <h2 class="mb-6" style="font-size:var(--fs-h3)" data-reveal>While you wait</h2>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($posts as $i => $post): ?>
            <article class="card card--interactive post-card" style="--i:<?= $i ?>" data-reveal>
                <div class="post-card__body">
                    <div class="post-card__meta"><span><?= e(format_date($post['published_at'])) ?></span></div>
                    <h3 class="post-card__title"><a href="<?= e(url('/blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h3>
                    <p class="post-card__excerpt"><?= e(str_limit($post['excerpt'], 110)) ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
