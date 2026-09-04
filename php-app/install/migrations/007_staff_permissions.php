<?php
/**
 * Per-staff section access. NULL/empty means "full access" (so every
 * existing staff account keeps working exactly as before this migration
 * ran) — an admin only needs to touch this when they want to actually
 * restrict someone to a subset of the admin panel.
 */
return function (PDO $pdo, array $context): void {
    $col = $pdo->query("SHOW COLUMNS FROM staff LIKE 'permissions'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE staff ADD COLUMN permissions TEXT NULL AFTER role");
    }
    $col = $pdo->query("SHOW COLUMNS FROM staff LIKE 'is_owner'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE staff ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER permissions");
        $pdo->exec("UPDATE staff SET is_owner = 1 ORDER BY id ASC LIMIT 1");
    }
};
