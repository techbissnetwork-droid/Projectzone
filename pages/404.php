<?php /** @var array $services @var array $projects */ ?>
<section class="section" style="padding-top:clamp(4rem,3rem+4vw,7rem)">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern"></span></div>
    <div class="container container--narrow text-center">
        <p class="mono" style="font-size:clamp(3.5rem,2.5rem+5vw,6rem);font-weight:600;letter-spacing:-.04em;color:var(--text-faint);line-height:1">404</p>
        <h1 class="mt-4">This page does not exist.</h1>
        <p class="lead mt-4">
            The link may be out of date, or the page may have moved. Everything below is still where it should be.
        </p>
        <div class="row row--center mt-8">
            <a class="btn btn--primary btn--arrow" href="<?= e(url('/')) ?>">Back to the homepage<?= icon('arrow-right') ?></a>
            <a class="btn btn--ghost" href="<?= e(url('/contact')) ?>">Report a broken link</a>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="container">
        <div class="grid grid-4" data-reveal-stagger>
            <?php foreach ([
                ['Services', '/services', 'layers', 'Everything we build and run.'],
                ['Tell us what you need', '/request', 'package', 'Pick what you want and send it over.'],
                ['Our work', '/portfolio', 'image', 'Projects and case studies.'],
                ['Contact', '/contact', 'mail', 'Talk to a person.'],
            ] as $i => $link): ?>
            <a class="card card--interactive" href="<?= e(url($link[1])) ?>" style="--i:<?= $i ?>" data-reveal>
                <span class="icon-plate icon-plate--sm"><?= icon($link[2]) ?></span>
                <h2 class="card__title mt-4" style="font-size:var(--fs-sm)"><?= e($link[0]) ?></h2>
                <p class="card__text" style="font-size:var(--fs-xs)"><?= e($link[3]) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($services): ?>
<section class="section section--tight">
    <div class="container">
        <h2 class="mb-6" style="font-size:var(--fs-h3)">Popular services</h2>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
