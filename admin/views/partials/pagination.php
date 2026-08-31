<?php
/** @var \Techbiss\Core\Paginator $paginator @var string $baseUrl @var array $query */
if (!$paginator->hasPages()) {
    if ($paginator->total > 0): ?>
    <div class="panel__foot"><span class="hint"><?= $paginator->total ?> record<?= $paginator->total === 1 ? '' : 's' ?></span></div>
    <?php endif;
    return;
}
$query = $query ?? [];
?>
<div class="panel__foot">
    <span class="hint">
        Showing <?= $paginator->from() ?>–<?= $paginator->to() ?> of <?= $paginator->total ?>
    </span>
    <nav class="pagination" style="margin:0 0 0 auto" aria-label="Pagination">
        <a class="pagination__link<?= $paginator->page <= 1 ? ' is-disabled' : '' ?>"
           href="<?= e(url($paginator->url($baseUrl, $paginator->page - 1, $query))) ?>" aria-label="Previous page"><?= icon('chevron-left') ?></a>
        <?php foreach ($paginator->window(1) as $p): ?>
            <?php if ($p === 0): ?>
                <span class="pagination__gap">…</span>
            <?php elseif ($p === $paginator->page): ?>
                <span class="pagination__link" aria-current="page"><?= $p ?></span>
            <?php else: ?>
                <a class="pagination__link" href="<?= e(url($paginator->url($baseUrl, $p, $query))) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a class="pagination__link<?= $paginator->page >= $paginator->pages ? ' is-disabled' : '' ?>"
           href="<?= e(url($paginator->url($baseUrl, $paginator->page + 1, $query))) ?>" aria-label="Next page"><?= icon('chevron-right') ?></a>
    </nav>
</div>
