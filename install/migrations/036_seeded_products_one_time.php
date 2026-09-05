<?php
/**
 * The eight seeded marketplace products were inserted without a
 * pricing_type (001_initial.php), so they fell to the column default
 * 'monthly' and every card rendered "$349 /mo", "$199 /mo", and so on —
 * for downloadable, one-time deliverables (themes, kits, dashboards). The
 * marketplace's own copy sells them as "buy … and download right now", so
 * the subscription framing was simply wrong.
 *
 * Flip the seeded catalogue to one-time, but only where it is still the
 * untouched 'monthly' default, so an admin who deliberately set a product
 * to a subscription keeps it.
 */
return function (PDO $pdo, array $context): void {
    $seeded = ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8'];
    $in = implode(',', array_fill(0, count($seeded), '?'));
    $pdo->prepare("UPDATE products SET pricing_type = 'fixed' WHERE pricing_type = 'monthly' AND id IN ($in)")
        ->execute($seeded);
};
