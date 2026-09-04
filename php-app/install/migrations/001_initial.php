<?php
/**
 * Fresh-install migration: creates every table (safe to re-run — uses
 * CREATE TABLE IF NOT EXISTS) and seeds realistic sample data the first
 * time only (each seed step checks the table is empty before inserting,
 * so re-running this after a partial failure never duplicates rows).
 *
 * $context is expected to carry: admin_name, admin_email, admin_password_hash
 * (collected by the installer's "create your admin account" step).
 */
return function (PDO $pdo, array $context): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(190) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` VARCHAR(60) NOT NULL DEFAULT 'Staff',
        `initials` VARCHAR(4) NOT NULL DEFAULT '',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(190) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `businesses` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL UNIQUE,
        `sector` VARCHAR(80) NOT NULL,
        `plan` VARCHAR(60) NOT NULL,
        `mrr_cents` INT UNSIGNED NOT NULL DEFAULT 0,
        `status` ENUM('Active','Trial','Past due') NOT NULL DEFAULT 'Active',
        `last_activity_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tickets` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `priority` ENUM('Low','Normal','High') NOT NULL DEFAULT 'Normal',
        `status` ENUM('Open','In progress','Closed') NOT NULL DEFAULT 'Open',
        `assignee_staff_id` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assignee_staff_id`) REFERENCES `staff`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` VARCHAR(20) PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `category` VARCHAR(60) NOT NULL,
        `icon` VARCHAR(40) NOT NULL,
        `price` DECIMAL(8,2) NOT NULL,
        `rating` DECIMAL(2,1) NOT NULL,
        `tagline` TEXT NOT NULL,
        `description` TEXT NOT NULL,
        `specs_json` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(190) NOT NULL,
        `company` VARCHAR(150) NULL,
        `need` VARCHAR(150) NULL,
        `message` TEXT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- Seed staff: the account you're creating right now, plus three
    // illustrative teammates that share the SAME password you just chose
    // (never a hardcoded default) so the admin panel looks populated.
    // Rename/remove/change these freely once you're in.
    if ((int)$pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn() === 0) {
        $hash = $context['admin_password_hash'];
        $stmt = $pdo->prepare(
            'INSERT INTO staff (name, email, password_hash, role, initials) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$context['admin_name'], $context['admin_email'], $hash, $context['admin_role'] ?? 'Admin', $context['admin_initials'] ?? strtoupper(substr($context['admin_name'], 0, 2))]);
        $stmt->execute(['Devon Kwan', 'devon@' . $context['email_domain'], $hash, 'Head of Engineering', 'DK']);
        $stmt->execute(['Rhea Solano', 'rhea@' . $context['email_domain'], $hash, 'Head of Design', 'RS']);
        $stmt->execute(['Jonah Traeger', 'jonah@' . $context['email_domain'], $hash, 'VP Client Success', 'JT']);
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO businesses (name, sector, plan, mrr_cents, status, last_activity_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $rows = [
            ['Maple & Co. Bakery', 'Bakery', 'Growth', 14900, 'Active', '-2 hours'],
            ['Solstice Yoga Studio', 'Fitness', 'Starter', 7900, 'Active', '-1 day'],
            ['Corner Hardware & Repair', 'Home services', 'Growth', 14900, 'Active', '-3 days'],
            ['Nomad Coffee Roasters', 'Creator', 'App + Web', 22900, 'Trial', '-6 hours'],
            ['Kinship Pet Rescue', 'Nonprofit', 'Starter', 7900, 'Active', '-1 day'],
            ['Bloom & Bramble Florist', 'Retail', 'Growth', 14900, 'Past due', '-11 days'],
        ];
        $ids = [];
        foreach ($rows as $r) {
            $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], date('Y-m-d H:i:s', strtotime($r[5]))]);
            $ids[] = (int)$pdo->lastInsertId();
        }

        $staffIds = $pdo->query('SELECT id FROM staff ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $tstmt = $pdo->prepare(
            'INSERT INTO tickets (business_id, title, priority, status, assignee_staff_id) VALUES (?, ?, ?, ?, ?)'
        );
        $tickets = [
            [0, 'Checkout error on mobile Safari', 'High', 'Open', $staffIds[1] ?? null],
            [5, 'Billing card declined — needs follow-up call', 'High', 'Open', $staffIds[0] ?? null],
            [0, 'Add a holiday hours banner', 'Normal', 'In progress', $staffIds[2] ?? null],
            [3, 'App Store review flagged a screenshot', 'Normal', 'Open', $staffIds[1] ?? null],
            [4, 'Domain renewal reminder', 'Low', 'Open', $staffIds[3] ?? null],
        ];
        foreach ($tickets as $t) {
            $tstmt->execute([$ids[$t[0]], $t[1], $t[2], $t[3], $t[4]]);
        }
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO products (id,name,category,icon,price,rating,tagline,description,specs_json,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $products = json_decode(file_get_contents(__DIR__ . '/001_initial_products.json'), true);
        foreach ($products as $i => $p) {
            $stmt->execute([$p['id'], $p['name'], $p['cat'], $p['icon'], $p['price'], $p['rating'], $p['tagline'], $p['desc'], json_encode($p['specs']), $i + 1]);
        }
    }
};
