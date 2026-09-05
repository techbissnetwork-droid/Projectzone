<?php
/**
 * An optional hard daily submission cap, separate from the tracked
 * daily goal — 0 (or unset) means unlimited. A universal default plus
 * an optional per-staff override, mirroring marketing_daily_goal.
 * Once a staffer hits their cap for the day, the submit form hides
 * itself until the count resets at midnight.
 */
return function (PDO $pdo, array $context): void {
    $cols = $pdo->query('SHOW COLUMNS FROM staff')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('marketing_daily_cap', $cols, true)) {
        $pdo->exec('ALTER TABLE staff ADD COLUMN marketing_daily_cap INT UNSIGNED NULL');
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['marketing_daily_cap_default', '0']);
};
