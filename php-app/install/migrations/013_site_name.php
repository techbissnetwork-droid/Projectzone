<?php
/**
 * Makes the brand name shown next to the logo (header, footer, splash
 * screen) editable from Admin > Settings instead of being hardcoded.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['site_name', 'TECHBISS']);
};
