<?php
/**
 * Replaces the fixed Starter/Growth/Custom-Build plan cards on the public
 * Pricing page with a single admin-editable "starting from $X" figure —
 * every real engagement here is quoted individually, so listing fixed
 * monthly tiers was misleading. Drops the now-unused plans table.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['pricing_starting_price', '5']);

    $exists = $pdo->query("SHOW TABLES LIKE 'content_pricing_plans'")->fetch();
    if ($exists) {
        $pdo->exec('DROP TABLE content_pricing_plans');
    }
};
