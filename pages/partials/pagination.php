<?php
/** @var \Techbiss\Core\Paginator $paginator @var string $baseUrl @var array $query */
if (!$paginator->hasPages()) { return; }
$query = $query ?? [];
?>
<nav class="pagination" aria-label="Pagination">
    <a class="pagination__link<?= $paginator->page <= 1 ? ' is-disabled' : '' ?>"
       href="<?= e(url($paginator->url($baseUrl, max(1, $paginator->page - 1), $query))) ?>"
       <?= $paginator->page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?> rel="prev" aria-label="Previous page">
        <?= icon('chevron-left') ?>
    </a>

    <?php foreach ($paginator->window() as $p): ?>
        <?php if ($p === 0): ?>
            <span class="pagination__gap">…</span>
        <?php elseif ($p === $paginator->page): ?>
            <span class="pagination__link" aria-current="page"><?= $p ?></span>
        <?php else: ?>
            <a class="pagination__link" href="<?= e(url($paginator->url($baseUrl, $p, $query))) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <a class="pagination__link<?= $paginator->page >= $paginator->pages ? ' is-disabled' : '' ?>"
       href="<?= e(url($paginator->url($baseUrl, min($paginator->pages, $paginator->page + 1), $query))) ?>"
       <?= $paginator->page >= $paginator->pages ? 'aria-disabled="true" tabindex="-1"' : '' ?> rel="next" aria-label="Next page">
        <?= icon('chevron-right') ?>
    </a>
</nav>
