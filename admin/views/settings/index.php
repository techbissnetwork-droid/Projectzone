<?php
/** @var array $rows @var string $group @var array $groups */
use Techbiss\Core\Auth;
?>
<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p>Everything here is read live by the public site. Nothing about the company, contact details or SEO is hard-coded.</p>
    </div>
</div>

<div class="tabs">
    <?php foreach ($groups as $key => $meta):
        if (!Auth::can($meta['permission'])) { continue; } ?>
    <a class="tab<?= $group === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/settings?group=' . urlencode($key))) ?>">
        <?= icon($meta['icon']) ?><?= e($meta['label']) ?>
    </a>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>" data-dirty-guard style="max-width:820px">
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($group) ?>">

    <div class="panel">
        <div class="panel__head">
            <span class="panel__title"><?= e($groups[$group]['label']) ?></span>
        </div>
        <div class="panel__body">
            <?php if (!$rows): ?>
                <p class="hint">No settings in this group.</p>
            <?php else: ?>
            <div class="form-section">
                <?php foreach ($rows as $row):
                    $key   = (string) $row['key_name'];
                    $type  = (string) $row['type'];
                    $value = (string) old($key, $row['value'] ?? '');
                    $err   = error_for($key);
                    $id    = 's-' . preg_replace('/[^a-z0-9_]/i', '', $key);
                ?>
                <div class="field">
                    <?php if ($type !== 'bool'): ?>
                    <label class="label" for="<?= e($id) ?>"><?= e($row['label'] ?: $key) ?></label>
                    <?php endif; ?>

                    <?php if ($type === 'bool'): ?>
                        <label class="switch">
                            <input type="checkbox" id="<?= e($id) ?>" name="<?= e($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                            <span class="switch__track" aria-hidden="true"></span>
                            <span><?= e($row['label'] ?: $key) ?></span>
                        </label>
                    <?php elseif ($type === 'textarea'): ?>
                        <textarea class="textarea<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"
                                  maxlength="20000"><?= e($value) ?></textarea>
                    <?php elseif ($type === 'image'): ?>
                        <div class="media-field" data-media-field>
                            <div class="media-field__preview">
                                <?php $img = media_url($value); ?>
                                <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                            </div>
                            <div class="media-field__body">
                                <span class="media-field__path"><?= $value !== '' ? e($value) : 'No image selected' ?></span>
                                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                                <div class="row row--tight">
                                    <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                    <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($type === 'select' && $key === 'theme_mode'): ?>
                        <select class="select" id="<?= e($id) ?>" name="<?= e($key) ?>">
                            <option value="dark" <?= $value === 'dark' ? 'selected' : '' ?>>Dark (default)</option>
                            <option value="light" <?= $value === 'light' ? 'selected' : '' ?>>Light</option>
                        </select>
                    <?php else: ?>
                        <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="text"
                               name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="500">
                    <?php endif; ?>

                    <?php if ($err): ?>
                        <span class="field-error"><?= icon('alert') ?><?= e($err) ?></span>
                    <?php elseif (!empty($row['hint'])): ?>
                        <span class="hint"><?= e($row['hint']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="panel__foot">
            <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save settings</button>
            <span class="hint ml-auto">Changes take effect on the public site immediately.</span>
        </div>
    </div>
</form>

<?php if ($group === 'commerce'): ?>
<div class="notice notice--accent mt-4" style="max-width:820px">
    <?= icon('shield') ?>
    <span>
        Only payment methods listed in <strong>Enabled payment methods</strong> are offered at checkout. If a gateway is not
        configured on your server, do not list it — the site should never imply a payment route that does not exist.
    </span>
</div>
<?php endif; ?>
