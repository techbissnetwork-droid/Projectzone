<?php
/**
 * The pricing page shows a large "Starting from $X". Its setting was seeded
 * at $5 (migration 027) — a placeholder nobody updated — while the same
 * page's own comparison table starts the cheapest path (buy a ready-made
 * theme) at $59 and a custom build at $900. "$5" read as broken.
 *
 * Bring the teaser up to the real floor ($59, the "Buy" path), but only
 * when it is still the untouched $5 default, so an admin who deliberately
 * set their own figure keeps it.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE id = ?');
    $stmt->execute(['pricing_starting_price']);
    $current = $stmt->fetchColumn();
    if ($current === false || $current === '' || (int)$current <= 5) {
        $pdo->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute(['pricing_starting_price', '59']);
    }
};
