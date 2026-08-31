<?php
/** @var array|null $section */
$heading = $heading ?? ($section['heading'] ?? "Let's build your digital business.");
$lead    = $lead    ?? ($section['subheading'] ?? 'Tell us about the business. We will come back with a clear scope, a schedule and a price — no obligation.');
$eyebrow = $eyebrow ?? ($section['eyebrow'] ?? 'Ready when you are');
$primaryLabel = $primaryLabel ?? ($section['cta_label'] ?? 'Start Your Digital Journey');
$primaryUrl   = $primaryUrl   ?? ($section['cta_url'] ?? '/start');

// Where a caller passes a message, the band asks on WhatsApp and by email
// instead of sending people to a form — the price is settled in conversation.
$waMessage   = $waMessage   ?? '';
$mailSubject = $mailSubject ?? '';
$waHref   = $waMessage   === '' ? '' : whatsapp_link($waMessage);
$mailHref = $mailSubject === '' ? '' : email_link($mailSubject, $waMessage);
$chat     = $waHref !== '' || $mailHref !== '';
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
                <?php if ($chat): ?>
                    <?php if ($waHref !== ''): ?>
                    <a class="btn btn--primary btn--lg" href="<?= e($waHref) ?>"
                       target="_blank" rel="noopener noreferrer" data-magnetic="0.24">
                        <?= icon('whatsapp') ?>Ask on WhatsApp
                    </a>
                    <?php endif; ?>
                    <?php if ($mailHref !== ''): ?>
                    <a class="btn <?= $waHref === '' ? 'btn--primary' : 'btn--ghost' ?> btn--lg" href="<?= e($mailHref) ?>">
                        <?= icon('mail') ?>Ask by email
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($primaryUrl)) ?>" data-magnetic="0.24">
                        <?= e($primaryLabel) ?><?= icon('arrow-right') ?>
                    </a>
                    <a class="btn btn--ghost btn--lg" href="<?= e(url('/packages')) ?>">Explore Packages</a>
                <?php endif; ?>
            </div>
            <p class="cta-band__note">No payment is taken on this website. We confirm scope and pricing with you first.</p>
        </div>
    </div>
</section>
