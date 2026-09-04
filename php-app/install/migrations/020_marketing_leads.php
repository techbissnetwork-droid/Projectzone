<?php
/**
 * Field-marketing leads: staff submit offline businesses they've found
 * (name, phone, address) for the team to follow up on. Each submission
 * earns the submitting staff member a small per-lead incentive.
 */
return function (PDO $pdo, array $context): void {
    $cols = $pdo->query('SHOW COLUMNS FROM staff')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('marketing_earnings_cents', $cols, true)) {
        $pdo->exec('ALTER TABLE staff ADD COLUMN marketing_earnings_cents INT UNSIGNED NOT NULL DEFAULT 0');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketing_leads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id INT UNSIGNED NOT NULL,
        business_name VARCHAR(150) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        address VARCHAR(255) NOT NULL,
        notes TEXT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        reviewed_by_staff_id INT UNSIGNED NULL,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
