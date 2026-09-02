<?php
/**
 * Renders one admin form field from a resource field definition.
 * @var array $field  @var mixed $value  @var array $extras
 */
use Techbiss\Core\Icons;

$key    = $field['key'];
$type   = $field['type'];
$label  = $field['label'];
$hint   = $field['hint'] ?? '';
$req    = !empty($field['required']);
$max    = (int) ($field['max'] ?? 255);
$err    = error_for($key);
$id     = 'f-' . preg_replace('/[^a-z0-9_-]/i', '', $key);
$value  = old($key, $value);
$extras = $extras ?? [];
$errId  = 'err-' . $id;
$hintId = 'hint-' . $id;
$noteId = $id . '-note';
// The control points at whichever description is actually on screen: the error
// replaces the hint below, so referencing a hidden hint would be a dangling id.
// The richtext note is always there, so it is always part of the description.
$describedBy = array_filter([
    $err !== '' ? $errId : '',
    $type === 'richtext' ? $noteId : '',
    ($err === '' && $hint !== '') ? $hintId : '',
]);
$aria = ($err !== '' ? ' aria-invalid="true"' : '')
    . ($describedBy !== [] ? ' aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '');
// A required field has to say so in the accessibility tree, not just with the
// asterisk beside its label. Native `required` where the server insists on the
// value too; aria-required where a native one would block a submit the server
// accepts — a slug it derives, a "None" option, a value in a hidden input.
$ariaReq = $req ? ' aria-required="true"' : '';
$accents = ['cyan' => '#34d3e0', 'violet' => '#a78bfa', 'emerald' => '#34d399', 'amber' => '#fbbf24', 'rose' => '#fb7185', 'blue' => '#4f8cff'];
?>
<div class="field">
    <?php if (!in_array($type, ['bool'], true)): ?>
    <label class="label" for="<?= e($id) ?>">
        <?= e($label) ?><?php if ($req): ?> <span class="req" aria-hidden="true">*</span><?php endif; ?>
    </label>
    <?php endif; ?>

    <?php switch ($type):
        case 'text': ?>
            <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="text" name="<?= e($key) ?>"
                   value="<?= e($value) ?>" maxlength="<?= $max ?>" <?= $req ? 'required' : '' ?>
                   <?= $max <= 255 ? 'data-counter' : '' ?><?= $aria ?>>
        <?php break;

        case 'slug': ?>
            <div class="input-group">
                <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="text" name="<?= e($key) ?>"
                       value="<?= e($value) ?>" maxlength="190" data-slug-from="<?= e($field['from'] ?? 'name') ?>"
                       pattern="[a-z0-9\-]*" spellcheck="false"<?= $ariaReq ?><?= $aria ?>>
                <button class="btn btn--quiet btn--sm" type="button" data-slug-regenerate
                        title="Regenerate from the title" aria-label="Regenerate slug from the title">
                    <?= icon('refresh') ?>
                </button>
            </div>
        <?php break;

        case 'textarea': ?>
            <textarea class="textarea<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"
                      maxlength="<?= max($max, 500) ?>" <?= $req ? 'required' : '' ?>
                      <?= $max <= 500 ? 'data-counter' : '' ?><?= $aria ?>><?= e($value) ?></textarea>
        <?php break;

        case 'lines': ?>
            <textarea class="textarea<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"
                      maxlength="5000" placeholder="One item per line"<?= $req ? ' required' : '' ?><?= $aria ?>><?= e($value) ?></textarea>
        <?php break;

        case 'richtext': ?>
            <textarea class="textarea textarea--tall<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>"
                      name="<?= e($key) ?>" maxlength="60000"<?= $req ? ' required' : '' ?><?= $aria ?>><?= e($value) ?></textarea>
            <span class="hint" id="<?= e($noteId) ?>">
                Accepts HTML. Permitted tags: headings, paragraphs, lists, links, images, tables, quotes and emphasis.
                Anything else — scripts especially — is stripped when saved.
            </span>
        <?php break;

        case 'decimal': ?>
            <div class="input-group">
                <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="number" name="<?= e($key) ?>"
                       value="<?= $value === null || $value === '' ? '' : e((string) $value) ?>"
                       step="0.01" min="0" <?= $req ? 'required' : '' ?> inputmode="decimal"<?= $aria ?>>
            </div>
        <?php break;

        case 'number': ?>
            <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="number" name="<?= e($key) ?>"
                   value="<?= e((string) $value) ?>" step="1"
                   <?= isset($field['min']) ? 'min="' . (int) $field['min'] . '"' : '' ?>
                   <?= isset($field['max_value']) ? 'max="' . (int) $field['max_value'] . '"' : '' ?><?= $ariaReq ?><?= $aria ?>>
        <?php break;

        case 'date': ?>
            <input class="input<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" type="date" name="<?= e($key) ?>"
                   value="<?= e(substr((string) $value, 0, 10)) ?>"<?= $ariaReq ?><?= $aria ?>>
        <?php break;

        case 'bool': ?>
            <label class="switch">
                <input type="checkbox" id="<?= e($id) ?>" name="<?= e($key) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?><?= $aria ?>>
                <span class="switch__track" aria-hidden="true"></span>
                <span><?= e($label) ?></span>
            </label>
        <?php break;

        case 'select': ?>
            <select class="select<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"<?= $req ? ' required' : '' ?><?= $aria ?>>
                <?php foreach (($field['options'] ?? []) as $optValue => $optLabel): ?>
                <option value="<?= e((string) $optValue) ?>" <?= (string) $value === (string) $optValue ? 'selected' : '' ?>>
                    <?= e($optLabel) ?>
                </option>
                <?php endforeach; ?>
            </select>
        <?php break;

        case 'lookup':
            $options = $extras['lookup_' . $key] ?? []; ?>
            <select class="select" id="<?= e($id) ?>" name="<?= e($key) ?>"<?= $ariaReq ?><?= $aria ?>>
                <option value="">None</option>
                <?php foreach ($options as $opt): ?>
                <option value="<?= (int) $opt['id'] ?>" <?= (int) $value === (int) $opt['id'] ? 'selected' : '' ?>>
                    <?= e($opt['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        <?php break;

        case 'customer_picker':
            $custOptions = $extras['lookup_' . $key] ?? [];
            $cNameErr    = error_for('client_name');
            $cEmailErr   = error_for('client_email'); ?>
            <select class="select" id="<?= e($id) ?>" name="<?= e($key) ?>"<?= $aria ?>>
                <option value="">— New client —</option>
                <?php foreach ($custOptions as $opt): ?>
                <option value="<?= (int) $opt['id'] ?>" <?= (int) $value === (int) $opt['id'] ? 'selected' : '' ?>>
                    <?= e($opt['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;margin-top:.6rem">
                <div class="field">
                    <label class="label" for="f-client_name">New client name</label>
                    <input class="input<?= $cNameErr ? ' is-invalid' : '' ?>" id="f-client_name" type="text"
                           name="client_name" value="<?= e(old('client_name')) ?>" maxlength="190">
                    <?php if ($cNameErr): ?><span class="field-error" role="alert"><?= icon('alert') ?><?= e($cNameErr) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="label" for="f-client_email">New client email</label>
                    <input class="input<?= $cEmailErr ? ' is-invalid' : '' ?>" id="f-client_email" type="email"
                           name="client_email" value="<?= e(old('client_email')) ?>" maxlength="190">
                    <?php if ($cEmailErr): ?><span class="field-error" role="alert"><?= icon('alert') ?><?= e($cEmailErr) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="label" for="f-client_phone">New client phone</label>
                    <input class="input" id="f-client_phone" type="text" name="client_phone"
                           value="<?= e(old('client_phone')) ?>" maxlength="40">
                </div>
            </div>
        <?php break;

        case 'media': ?>
            <div class="media-field" data-media-field<?= $ariaReq ?><?= $aria ?>>
                <div class="media-field__preview">
                    <?php if ($value !== ''): ?>
                        <img src="<?= e(media_url((string) $value)) ?>" alt="">
                    <?php else: ?>
                        <?= icon('image') ?>
                    <?php endif; ?>
                </div>
                <div class="media-field__body">
                    <span class="media-field__path"><?= $value !== '' ? e($value) : 'No image selected' ?></span>
                    <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                    <div class="row row--tight">
                        <button class="btn btn--ghost btn--sm" type="button" data-media-choose><?= icon('image') ?>Choose</button>
                        <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                    </div>
                </div>
            </div>
        <?php break;

        case 'icon': ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value ?: 'spark') ?>">
            <div class="icon-picker" data-icon-picker="<?= e($key) ?>"<?= $ariaReq ?><?= $aria ?>>
                <?php foreach (Icons::names() as $iconName): ?>
                <button type="button" class="icon-picker__item<?= $value === $iconName ? ' is-selected' : '' ?>"
                        data-icon="<?= e($iconName) ?>" title="<?= e($iconName) ?>" aria-label="<?= e($iconName) ?>">
                    <?= icon($iconName) ?>
                </button>
                <?php endforeach; ?>
            </div>
        <?php break;

        case 'accent': ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value ?: 'cyan') ?>">
            <div class="accent-picker" data-accent-picker="<?= e($key) ?>"<?= $ariaReq ?><?= $aria ?>>
                <?php foreach ($accents as $name => $hex): ?>
                <button type="button" class="accent-swatch<?= $value === $name ? ' is-selected' : '' ?>"
                        data-accent="<?= e($name) ?>" style="background:<?= e($hex) ?>"
                        title="<?= e(ucfirst($name)) ?>" aria-label="<?= e(ucfirst($name)) ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php break;

        case 'services':
            $all      = $extras['all_services'] ?? [];
            $selected = (array) old($key, $extras['selected_services'] ?? []); ?>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.35rem">
                <?php foreach ($all as $svc): ?>
                <label class="check">
                    <input type="checkbox" name="<?= e($key) ?>[]" value="<?= (int) $svc['id'] ?>"
                           <?= in_array((int) $svc['id'], array_map('intval', $selected), true) ? 'checked' : '' ?>>
                    <span class="check__box" aria-hidden="true"></span>
                    <span><?= e($svc['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        <?php break;

        default: ?>
            <input class="input" id="<?= e($id) ?>" type="text" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= $max ?>"<?= $aria ?>>
    <?php endswitch; ?>

    <?php if ($err): ?>
        <span class="field-error" id="<?= e($errId) ?>" role="alert"><?= icon('alert') ?><?= e($err) ?></span>
    <?php elseif ($hint !== ''): ?>
        <span class="hint" id="<?= e($hintId) ?>"><?= e($hint) ?></span>
    <?php endif; ?>
</div>
