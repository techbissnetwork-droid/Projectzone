<?php
/**
 * Replaces the per-submission cash incentive with a daily lead-count
 * goal instead: a universal default all staff are held to, with an
 * optional per-staff override for anyone who needs a different target.
 */
return function (PDO $pdo, array $context): void {
    $cols = $pdo->query('SHOW COLUMNS FROM staff')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('marketing_earnings_cents', $cols, true)) {
        $pdo->exec('ALTER TABLE staff DROP COLUMN marketing_earnings_cents');
    }
    if (!in_array('marketing_daily_goal', $cols, true)) {
        $pdo->exec('ALTER TABLE staff ADD COLUMN marketing_daily_goal INT UNSIGNED NULL');
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['marketing_daily_goal_default', '5']);
};
