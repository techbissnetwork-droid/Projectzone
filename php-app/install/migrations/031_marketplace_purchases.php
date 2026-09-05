<?php
/**
 * Real marketplace checkout: a product can have a downloadable file
 * attached (admin/products.php), and a completed purchase creates a
 * customer account (if the buyer's email is new) plus an order row with
 * its own one-time-ish download token, so the file can be re-downloaded
 * from the customer dashboard at any time, not just from the email link.
 */
return function (PDO $pdo, array $context): void {
    $hasDownloadPath = $pdo->query("SHOW COLUMNS FROM products LIKE 'download_path'")->fetch();
    if (!$hasDownloadPath) {
        $pdo->exec('ALTER TABLE products ADD COLUMN download_path VARCHAR(255) NULL AFTER specs_json');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_orders` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `order_ref` VARCHAR(20) NOT NULL UNIQUE,
        `product_id` VARCHAR(20) NOT NULL,
        `customer_id` INT UNSIGNED NOT NULL,
        `price_cents` INT UNSIGNED NOT NULL,
        `download_token` VARCHAR(64) NOT NULL UNIQUE,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
