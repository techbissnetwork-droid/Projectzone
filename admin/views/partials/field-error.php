<?php
/**
 * One inline form error, tied to the control it belongs to.
 *
 * The id is what the field's aria-describedby points at, and role="alert" gets
 * the message announced when the page comes back from a failed save instead of
 * it only being seen. Kept beside field-invalid.php so the id is written in one
 * place and the two can never drift apart.
 *
 * @var string $key  the form control's name attribute
 * @var bool $withIcon  some admin forms show the alert glyph, some do not
 */
$message  = error_for($key);
$withIcon = $withIcon ?? true;
?>
<?php if ($message !== ''): ?><span class="field-error" id="err-<?= e($key) ?>" role="alert"><?php if ($withIcon): ?><?= icon('alert') ?><?php endif; ?><?= e($message) ?></span><?php endif; ?>
