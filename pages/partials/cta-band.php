<?php
/** @var array|null $section */
$heading = $heading ?? ($section['heading'] ?? "Let's build your digital business.");
$lead    = $lead    ?? ($section['subheading'] ?? 'Tell us about the business. We will come back with a clear scope, a schedule and a price — no obligation.');
$eyebrow = $eyebrow ?? ($section['eyebrow'] ?? 'Ready when you are');
$primaryLabel = $primaryLabel ?? ($section['cta_label'] ?? 'Start Your Digital Journey');
$primaryUrl   = $primaryUrl   ?? ($section['cta_url'] ?? '/start');
?>
<section class="section">
    <div class="container">
        <div class="cta-band" data-reveal>
            <div class="cta-band__bg" aria-hidden="true">
                <span class="glow"></span>
                <span class="grid-pattern grid-pattern--center"></span>
            </div>
            <?php if ($eyebrow !== ''): ?>
            <p class="eyebrow eyebrow--plain" style="justify-content:center"><?= e($eyebrow) ?></p>
            <?php endif; ?>
            <h2 class="mt-4"><?= e($heading) ?></h2>
            <p class="lead"><?= e($lead) ?></p>
            <div class="cta-band__actions">
                <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($primaryUrl)) ?>" data-magnetic="0.24">
                    <?= e($primaryLabel) ?><?= icon('arrow-right') ?>
                </a>
                <a class="btn btn--ghost btn--lg" href="<?= e(url('/packages')) ?>">Explore Packages</a>
            </div>
            <p class="cta-band__note">No payment is taken on this website. We confirm scope and pricing with you first.</p>
        </div>
    </div>
</section>
