<?php
/**
 * A small pulsing signal/radar motif — lighter than the full transform-viz
 * set-piece, for pages where a touch of "we're listening" ambiance fits
 * without competing with a form for attention (contact, request).
 * @var string $label  optional short caption under the rings
 */
$label = $label ?? '';
?>
<div class="radar-visual" aria-hidden="true">
    <span class="radar-visual__rings">
        <span class="radar-visual__ring radar-visual__ring--1"></span>
        <span class="radar-visual__ring radar-visual__ring--2"></span>
        <span class="radar-visual__ring radar-visual__ring--3"></span>
        <span class="radar-visual__dot"></span>
    </span>
    <?php if ($label !== ''): ?>
    <span class="radar-visual__label"><?= e($label) ?></span>
    <?php endif; ?>
</div>
