<?php
/**
 * Real client projects, replacing the placeholder demo content on the
 * customer dashboard. A business can optionally be linked to a
 * customer login account (customer_id) — admin creates that login, not
 * a public signup form. Each business can have one or more projects,
 * each tracking domain/hosting/SSL/email expiry so admin can see what
 * needs renewing and reach out to the business's contact info.
 */
return function (PDO $pdo, array $context): void {
    $columns = [
        'contact_email' => "VARCHAR(190) NULL",
        'contact_phone' => "VARCHAR(40) NULL",
        'customer_id' => "INT UNSIGNED NULL",
    ];
    foreach ($columns as $col => $def) {
        $exists = $pdo->query("SHOW COLUMNS FROM businesses LIKE '$col'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE businesses ADD COLUMN `$col` $def");
        }
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['whatsapp_number', '']);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `status` ENUM('Planning','In progress','Live','On hold') NOT NULL DEFAULT 'Planning',
        `progress_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `domain` VARCHAR(190) NULL,
        `domain_expires_at` DATE NULL,
        `hosting_expires_at` DATE NULL,
        `ssl_expires_at` DATE NULL,
        `email_expires_at` DATE NULL,
        `notes` TEXT NULL,
        `portfolio_visible` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
