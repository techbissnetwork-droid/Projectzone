<?php /** @var array $grouped */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'FAQs',
    'heading' => 'Questions, answered honestly.',
    'lead'    => 'Including the ones where the honest answer is “no one can promise that”.',
    'center'  => true,
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <?php if (!$grouped): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('help') ?></span>
                <h3>No questions published yet</h3>
                <p>Ask us anything directly and we will answer.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/contact')) ?>">Ask a question</a>
            </div>
        <?php else: ?>
        <div class="split" style="align-items:start">
            <aside style="position:sticky;top:6.5rem" class="hide-md">
                <p class="footer-col__title">Categories</p>
                <div class="stack stack-2">
                    <?php foreach (array_keys($grouped) as $category): ?>
                    <a class="pill" href="#cat-<?= e(\Techbiss\Core\Str::slug($category)) ?>" data-no-transition>
                        <?= e($category) ?>
                        <span class="text-muted"><?= count($grouped[$category]) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="card mt-6">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Still unclear?</h3>
                    <p class="card__text">Ask directly — we would rather answer a question than lose a good fit over confusion.</p>
                    <div class="card__footer">
                        <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('/contact')) ?>">Contact us</a>
                    </div>
                </div>
            </aside>

            <div>
                <?php foreach ($grouped as $category => $faqs): ?>
                <div class="mb-8" id="cat-<?= e(\Techbiss\Core\Str::slug($category)) ?>" style="scroll-margin-top:7rem">
                    <h2 class="mb-4" style="font-size:var(--fs-h3)" data-reveal><?= e($category) ?></h2>
                    <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => \Techbiss\Core\Str::slug($category)]) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Still have a question?',
    'heading' => 'Ask us anything.',
    'lead'    => 'We answer plainly, including when the answer is that something is outside what we do well.',
    'primaryLabel' => 'Contact the team',
    'primaryUrl'   => '/contact',
]) ?>
