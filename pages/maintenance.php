<?php /** @var string $message */ ?>
<div class="container container--narrow text-center">
    <span class="icon-plate icon-plate--lg" style="margin-inline:auto"><?= icon('settings') ?></span>
    <h1 class="mt-6"><?= e(setting('site_name', 'TECHBISS')) ?></h1>
    <p class="lead mt-4"><?= e($message) ?></p>
    <?php if (setting('contact_email') !== ''): ?>
    <p class="hint mt-6">
        Need us urgently? <a class="link" href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
    </p>
    <?php endif; ?>
</div>
