<?php
/**
 * Lets a marketplace product show a real photo/screenshot instead of
 * just its decorative icon — set per product in admin/products.php.
 */
return function (PDO $pdo, array $context): void {
    $exists = $pdo->query("SHOW COLUMNS FROM products LIKE 'image_path'")->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER icon');
    }
};
