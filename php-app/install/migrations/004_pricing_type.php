<?php
/**
 * Adds a monthly-vs-fixed pricing type to products, so the marketplace
 * isn't forced to show "/mo" on things that are really a one-time price
 * (templates, launch kits, etc). Safe on a database that already has the
 * column (fresh installs get it directly from 001_initial.php).
 */
return function (PDO $pdo, array $context): void {
    $col = $pdo->query("SHOW COLUMNS FROM products LIKE 'pricing_type'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE products ADD COLUMN pricing_type ENUM('monthly','fixed') NOT NULL DEFAULT 'monthly' AFTER price");
    }
};
