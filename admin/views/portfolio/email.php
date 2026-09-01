<?php
/** @var array $row */
use Techbiss\Core\App;
$val = static fn (string $k, $d = '') => old($k, $d);
$id  = (int) $row['id'];
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Sent from <?= e(App::settings()->get('sales_email') ?: App::settings()->get('contact_email') ?: 'the site') ?> · replies land there too.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/portfolio/' . $id . '/edit')) ?>"><?= icon('arrow-left') ?>Back to project</a>
    </div>
</div>

<div class="form-cols">
    <div>
        <form method="post" action="<?= e(url('/admin/portfolio/' . $id . '/email')) ?>" data-dirty-guard>
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="e-subject">Subject <span class="req">*</span></label>
                            <input class="input<?= error_for('subject') ? ' is-invalid' : '' ?>" id="e-subject" type="text"
                                   name="subject" value="<?= e($val('subject', 'About ' . $row['title'])) ?>" required maxlength="180"
                                   <?= $view->partial('partials/field-invalid', ['key' => 'subject']) ?>>
                            <?= $view->partial('partials/field-error', ['key' => 'subject']) ?>
                        </div>
                        <div class="field">
                            <label class="label" for="e-message">Message <span class="req">*</span></label>
                            <textarea class="textarea" id="e-message" name="message" rows="10" required maxlength="5000"
                                      <?= $view->partial('partials/field-invalid', ['key' => 'message']) ?>><?= e($val('message', 'Hi ' . ($row['client_name'] ?: '') . ",\n\n")) ?></textarea>
                            <?= $view->partial('partials/field-error', ['key' => 'message']) ?>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('send') ?>Send</button>
                </div>
            </div>
        </form>
    </div>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Sending to</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Name</span><span class="kv__value"><?= e($row['client_name'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Email</span><span class="kv__value"><a href="mailto:<?= e($row['client_email']) ?>"><?= e($row['client_email']) ?></a></span></div>
                    <?php if ((string) $row['client_phone'] !== ''): ?>
                    <div class="kv"><span class="kv__label">Phone</span><span class="kv__value"><?= e($row['client_phone']) ?></span></div>
                    <?php endif; ?>
                    <div class="kv"><span class="kv__label">Project</span><span class="kv__value"><?= e($row['title']) ?></span></div>
                </div>
            </div>
        </div>
    </aside>
</div>
