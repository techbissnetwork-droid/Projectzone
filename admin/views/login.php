<div class="auth-card">
    <div class="auth-card__brand">
        <svg class="brand__mark" aria-hidden="true" focusable="false"><use href="#tb-mark"/></svg>
        <span class="brand__text"><?= e($settings->get('site_name', 'TECHBISS')) ?></span>
    </div>

    <h1 style="font-size:1.3rem">Sign in</h1>
    <p class="hint mt-2 mb-5">Administrator access to the TECHBISS content management system.</p>

    <form method="post" action="<?= e(url('/admin/login')) ?>" data-form novalidate>
        <?= csrf_field() ?>
        <div class="stack stack-4">
            <div class="field">
                <label class="label" for="login-email">Email address</label>
                <div class="input-group">
                    <?= icon('mail', 'icon icon--lead') ?>
                    <input class="input" id="login-email" type="email" name="email" value="<?= e(old('email')) ?>"
                           required autocomplete="username" autofocus maxlength="190">
                </div>
            </div>

            <div class="field">
                <label class="label" for="login-password">Password</label>
                <div class="input-group">
                    <?= icon('lock', 'icon icon--lead') ?>
                    <input class="input" id="login-password" type="password" name="password"
                           required autocomplete="current-password" maxlength="200">
                </div>
            </div>

            <button class="btn btn--primary btn--lg btn--block" type="submit">Sign in</button>
        </div>
    </form>

    <p class="help-text mt-6">
        <?= icon('shield') ?>
        Repeated failed attempts temporarily lock this account. All administrator activity is logged.
    </p>
</div>
