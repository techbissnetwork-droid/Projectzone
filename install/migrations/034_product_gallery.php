<?php
/**
 * A marketplace product can now carry several images — a gallery on the
 * product page — instead of a single photo. Each image is a row here;
 * the lowest sort_order is the primary, and it is mirrored back into
 * products.image_path so the marketplace card and every existing
 * single-image code path keep working with no change.
 */
return function (PDO $pdo, array $context): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `product_images` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` VARCHAR(20) NOT NULL,
            `path` VARCHAR(255) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_product_images_product` (`product_id`, `sort_order`),
            CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`)
                REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backfill: every product that already has a single image keeps it as
    // its first gallery image, so nothing disappears on upgrade. Guarded so
    // re-running the migration never duplicates the row.
    $rows = $pdo->query(
        "SELECT id, image_path FROM products WHERE image_path IS NOT NULL AND image_path <> ''"
    )->fetchAll();
    $check = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $ins = $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, 0)');
    foreach ($rows as $r) {
        $check->execute([$r['id']]);
        if ((int)$check->fetchColumn() === 0) {
            $ins->execute([$r['id'], $r['image_path']]);
        }
    }
};
