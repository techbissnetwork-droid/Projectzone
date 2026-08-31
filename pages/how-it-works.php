<?php /** @var array $steps @var array $packages @var array $faqs */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'How it works',
    'heading' => 'Six stages from first conversation to ongoing growth.',
    'lead'    => 'You see the schedule before we start. No open-ended timelines, no invoice surprises.',
    'center'  => true,
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <?php if ($steps): ?>
        <div class="process">
            <?php foreach ($steps as $step): ?>
            <div class="process__step" data-reveal>
                <div class="process__num"><?= e($step['step_number']) ?></div>
                <div class="process__body">
                    <div class="row row--tight mb-3">
                        <?php if (!empty($step['icon'])): ?>
                        <span class="icon-plate icon-plate--sm"><?= icon((string) $step['icon']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($step['duration'])): ?>
                        <span class="badge"><?= icon('clock') ?><?= e($step['duration']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="process__title"><?= e($step['title']) ?></h2>
                    <p class="process__text"><?= e($step['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('list') ?></span>
            <h3>Our process is being documented</h3>
            <p>Get in touch and we will walk you through it directly.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="split">
            <div data-reveal="left">
                <p class="eyebrow">What we need from you</p>
                <h2 class="mt-4">Your part is smaller than you think.</h2>
                <p class="lead mt-4">Most of a project runs on our side. What we genuinely need from you is limited and defined upfront.</p>
            </div>
            <div class="stack stack-3" data-reveal="right">
                <?php
                $needs = [
                    ['icon' => 'chat',     'title' => 'One conversation about the business',   'text' => 'What you sell, who buys it, what is getting in the way today.'],
                    ['icon' => 'user',     'title' => 'A person who can approve decisions',    'text' => 'One decision-maker keeps a project moving. Committees do not.'],
                    ['icon' => 'file',     'title' => 'Whatever content already exists',       'text' => 'Logos, photos, price lists, existing copy. If there is none, we can produce it.'],
                    ['icon' => 'check',    'title' => 'Feedback at two review points',         'text' => 'Design review and pre-launch review. Both are scheduled in advance.'],
                ];
                foreach ($needs as $i => $need): ?>
                <div class="card" style="padding:1.1rem 1.25rem;--i:<?= $i ?>">
                    <div class="row row--nowrap" style="align-items:flex-start;gap:.85rem">
                        <span class="icon-plate icon-plate--sm"><?= icon($need['icon']) ?></span>
                        <div>
                            <div style="font-size:var(--fs-sm);font-weight:550;color:var(--text)"><?= e($need['title']) ?></div>
                            <p class="card__text mt-2" style="font-size:var(--fs-xs)"><?= e($need['text']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($packages): ?>
<section class="section">
    <div class="container">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">Stage 02</p>
            <h2 class="mt-4">Choose your setup</h2>
            <p class="lead">Pick a package, or ask for a custom scope. Either way you see the full price before deciding.</p>
        </div>
        <div class="package-grid" data-reveal-stagger>
            <?php foreach ($packages as $package): ?>
                <?= $view->partial('partials/package-card', ['package' => $package, 'compact' => true]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section">
    <div class="container container--narrow">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">Getting started</p>
            <h2 class="mt-4">Common questions</h2>
        </div>
        <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => 'hiw']) ?>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Stage 01',
    'heading' => 'Tell us about your business.',
    'lead'    => 'That is where it starts. Six short questions, then a plan.',
]) ?>
