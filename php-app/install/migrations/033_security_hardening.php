<?php
/**
 * Security and integrity hardening.
 *
 *  - rate_limits: backs the staff-login throttle and the public
 *    contact/newsletter/sign-in-code limits. A shared table rather than an
 *    in-process counter, because each request may be served by a different
 *    PHP worker.
 *  - otp_codes indexes: every verify filtered on (customer_id, purpose) or
 *    token_hash with only a primary key to work from.
 *  - product_orders download expiry + counter: tokens previously never
 *    expired and were never counted, so a forwarded link was a permanent
 *    anonymous download of a paid product.
 *  - product_orders.product_id ON DELETE CASCADE -> RESTRICT: deleting a
 *    product silently erased every order ever placed for it, along with
 *    each buyer's access. Deletion is now refused while orders exist.
 *  - businesses.customer_id gains a real foreign key so an owner id that
 *    doesn't exist can no longer be stored.
 *  - payments_enabled: the checkout takes no payment (see README), so it
 *    defaults to off and the buy flow routes to Contact until a real
 *    payment processor is wired in.
 */
return function (PDO $pdo, array $context): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id` CHAR(64) PRIMARY KEY,
        `hits` INT UNSIGNED NOT NULL DEFAULT 0,
        `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_window_start` (`window_start`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $indexes = $pdo->query('SHOW INDEX FROM otp_codes')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('idx_customer_purpose', $indexes, true)) {
        $pdo->exec('ALTER TABLE otp_codes ADD INDEX `idx_customer_purpose` (`customer_id`, `purpose`, `used_at`)');
    }
    if (!in_array('idx_token_hash', $indexes, true)) {
        $pdo->exec('ALTER TABLE otp_codes ADD INDEX `idx_token_hash` (`token_hash`)');
    }
    if (!in_array('idx_expires_at', $indexes, true)) {
        $pdo->exec('ALTER TABLE otp_codes ADD INDEX `idx_expires_at` (`expires_at`)');
    }

    $orderCols = $pdo->query('SHOW COLUMNS FROM product_orders')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('download_expires_at', $orderCols, true)) {
        $pdo->exec('ALTER TABLE product_orders ADD COLUMN `download_expires_at` DATETIME NULL AFTER `download_token`');
        // Existing orders keep working, but from now on they expire too.
        $pdo->exec('UPDATE product_orders SET download_expires_at = (NOW() + INTERVAL 30 DAY)');
    }
    if (!in_array('download_count', $orderCols, true)) {
        $pdo->exec('ALTER TABLE product_orders ADD COLUMN `download_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `download_expires_at`');
    }

    // Swap the product_id cascade for a restrict. The constraint name is
    // auto-generated, so look it up rather than guessing.
    $fk = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_orders'
           AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products'"
    )->fetchColumn();
    if ($fk) {
        $pdo->exec('ALTER TABLE product_orders DROP FOREIGN KEY `' . $fk . '`');
        $pdo->exec('ALTER TABLE product_orders ADD CONSTRAINT `fk_orders_product`
                    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT');
    }

    // businesses.customer_id was added without a foreign key, so a
    // hand-crafted POST could point a business at a customer that isn't
    // there. Clear any such rows first or the constraint won't apply.
    $bizFk = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'businesses'
           AND COLUMN_NAME = 'customer_id' AND REFERENCED_TABLE_NAME = 'customers'"
    )->fetchColumn();
    if (!$bizFk) {
        $pdo->exec('UPDATE businesses b LEFT JOIN customers c ON c.id = b.customer_id
                    SET b.customer_id = NULL WHERE b.customer_id IS NOT NULL AND c.id IS NULL');
        $pdo->exec('ALTER TABLE businesses ADD CONSTRAINT `fk_business_customer`
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL');
    }

    // last_activity_at was written once on insert and never again, while
    // the admin list sorted by it under a "Last activity" heading. It is
    // now maintained on real events; seed it from the newest signal each
    // business already has.
    $pdo->exec("UPDATE businesses b
                SET b.last_activity_at = GREATEST(
                    b.last_activity_at,
                    COALESCE((SELECT MAX(t.created_at) FROM tickets t WHERE t.business_id = b.id), b.last_activity_at),
                    COALESCE((SELECT MAX(p.created_at) FROM projects p WHERE p.business_id = b.id), b.last_activity_at)
                )");

    // contact_messages was write-only until now; admin/messages.php needs
    // somewhere to record that an enquiry has been dealt with.
    $msgCols = $pdo->query('SHOW COLUMNS FROM contact_messages')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('handled_at', $msgCols, true)) {
        $pdo->exec('ALTER TABLE contact_messages ADD COLUMN `handled_at` DATETIME NULL');
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['payments_enabled', 'off']);
    $stmt->execute(['contact_notify_email', '']);
};
