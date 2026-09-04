<?php
/**
 * Lets an admin choose "icon + site name" (default) or "site name only"
 * for the header/footer/admin logo when no custom logo image is
 * uploaded. A custom uploaded image always overrides this entirely.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['logo_style', 'icon_text']);
};
