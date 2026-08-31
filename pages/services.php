<?php /** @var array $services @var array $faqs @var array $steps */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Services',
    'heading' => 'Everything your business needs to operate online.',
    'lead'    => 'Ten services, from infrastructure to the work that keeps it running. Take one, or take the lot.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <h2 class="sr-only">Services</h2>
        <?php if (!$services): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('layers') ?></span>
                <h3>Services are being prepared</h3>
                <p>Our service catalogue is being updated. In the meantime, tell us what you need and we will respond directly.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/contact')) ?>">Get in touch</a>
            </div>
        <?php else: ?>
            <div class="slider" data-slider>
                <div class="slider__track slider__track--cards" data-reveal-stagger>
                <?php foreach ($services as $i => $service): ?>
                    <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
                <?php endforeach; ?>
            </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($services): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">In detail</p>
            <h2 class="mt-4">What each service actually includes.</h2>
            <p class="lead">No vague deliverables. Here is what is in scope before you commit to anything.</p>
        </div>

        <div class="accordion" data-accordion>
            <?php foreach ($services as $service):
                $features = $service['features'] ?? [];
                $id = 'svc-' . (int) $service['id']; ?>
            <div class="accordion__item">
                <h3>
                    <button class="accordion__trigger" type="button" aria-expanded="false"
                            aria-controls="panel-<?= e($id) ?>" id="trigger-<?= e($id) ?>">
                        <span class="row row--tight row--nowrap">
                            <span class="icon-plate icon-plate--sm" data-accent="<?= e($service['accent'] ?: 'cyan') ?>"><?= icon((string) $service['icon']) ?></span>
                            <span><?= e($service['name']) ?></span>
                        </span>
                        <span class="accordion__icon" aria-hidden="true"><?= icon('chevron-down') ?></span>
                    </button>
                </h3>
                <div class="accordion__panel" id="panel-<?= e($id) ?>" role="region"
                     aria-labelledby="trigger-<?= e($id) ?>" data-open="false">
                    <div>
                        <div class="accordion__body">
                            <?php $deliverables = lines_to_list($service['deliverables']); ?>
                            <?php if ($deliverables): ?>
                            <div class="grid grid-2 mt-5">
                                <?php foreach ($deliverables as $d): ?>
                                <div class="row row--nowrap row--tight" style="align-items:flex-start">
                                    <span class="feature-row__check"><?= icon('check') ?></span>
                                    <span style="font-size:var(--fs-sm)"><?= e($d) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($features): ?>
                            <div class="chip-row mt-5">
                                <?php foreach ($features as $f): ?>
                                <span class="pill"><?= icon('check') ?><?= e($f['title']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="row mt-5">
                                <a class="btn btn--ghost btn--sm btn--arrow" href="<?= e(url('/services/' . $service['slug'])) ?>">
                                    Full details<?= icon('arrow-right') ?>
                                </a>
                                <a class="btn btn--quiet btn--sm" href="<?= e(url('/quote')) ?>">Request a quote</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($steps): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">The process</p>
            <h2 class="mt-4">However many services you take, the process is the same.</h2>
        </div>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($steps as $i => $step): ?>
            <div class="card" style="--i:<?= $i ?>" data-reveal>
                <div class="process__num" style="font-size:1.5rem"><?= e($step['step_number']) ?></div>
                <h3 class="card__title mt-3"><?= e($step['title']) ?></h3>
                <p class="card__text"><?= e(str_limit($step['description'], 130)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section">
    <div class="container container--narrow">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">Questions</p>
            <h2 class="mt-4">Before you commit</h2>
        </div>
        <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => 'svc-faq']) ?>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Next step',
    'heading' => 'Not sure which services you need?',
    'lead'    => 'Tell us where you are today. We will say what to build first, and what can wait.',
]) ?>
