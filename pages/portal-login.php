<?php
/**
 * Sign-in for a client we already built something for. No password: a
 * one-time code goes to the email address on file for the project.
 */
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Client sign in',
    'heading' => 'Sign in to your project.',
    'lead'    => 'Enter the email your project is registered under and we will send a one-time code to sign in — no password to remember.',
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <form class="card card--pad-lg" method="post" action="<?= e(url('/portal/login')) ?>" data-reveal>
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true">
                <label for="portal-hp">Leave this empty</label>
                <input id="portal-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
                <label class="label" for="portal-email">Email</label>
                <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" type="email" id="portal-email"
                       name="email" maxlength="190" autocomplete="email" required
                       value="<?= e(old('email')) ?>" <?= $view->partial('partials/field-invalid', ['key' => 'email']) ?>>
                <?= $view->partial('partials/field-error', ['key' => 'email']) ?>
            </div>

            <div class="form-actions mt-4">
                <button class="btn btn--primary btn--lg btn--block" type="submit">
                    <?= icon('send') ?>Send me a code
                </button>
            </div>

            <p class="hint mt-4"><?= icon('shield') ?> Only for clients with a project already on file. Not what you need? <a href="<?= e(url('/contact')) ?>">Talk to us instead</a>.</p>
        </form>
    </div>
</section>
