<?php
/**
 * The error-state attributes for one form control: aria-invalid plus the
 * aria-describedby that points at the message field-error.php renders.
 *
 * Emitted from inside the control's tag, so it prints nothing at all when the
 * field is fine. $describedBy carries any id the field is already described by
 * — a hint, say — so associating the error does not drop it.
 *
 * @var string $key  the form control's name attribute
 * @var string $describedBy  optional extra id to keep in aria-describedby
 */
$describedBy = $describedBy ?? '';
$invalid     = error_for($key) !== '';
$ids         = array_filter([$invalid ? 'err-' . $key : '', $describedBy]);
?>
<?php if ($invalid): ?>aria-invalid="true" <?php endif; ?><?php if ($ids): ?>aria-describedby="<?= e(implode(' ', $ids)) ?>"<?php endif; ?>
