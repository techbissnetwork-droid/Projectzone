<?php
/** @var string $email  the sign-in address, already masked for display */
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Client sign in',
    'heading' => 'Enter your code.',
    'lead'    => 'We sent a 6-digit code to ' . $email . '. It expires in 10 minutes.',
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <form class="card card--pad-lg" method="post" action="<?= e(url('/portal/verify')) ?>" data-reveal>
            <?= csrf_field() ?>

            <div class="field">
                <label class="label" for="portal-code">6-digit code</label>
                <input class="input<?= error_for('code') ? ' is-invalid' : '' ?>" type="text" id="portal-code"
                       name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                       required autofocus <?= $view->partial('partials/field-invalid', ['key' => 'code']) ?>>
                <?= $view->partial('partials/field-error', ['key' => 'code']) ?>
            </div>

            <div class="form-actions mt-4">
                <button class="btn btn--primary btn--lg btn--block" type="submit">
                    <?= icon('check') ?>Sign in
                </button>
            </div>

            <p class="hint mt-4">Code did not arrive, or it expired? <a href="<?= e(url('/portal/login')) ?>">Request a new one</a>.</p>
        </form>
    </div>
</section>
