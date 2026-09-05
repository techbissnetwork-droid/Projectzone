<?php
/**
 * Site-wide display zoom, set by the admin in Settings > General.
 * Applied via a `zoom` CSS style on <html> across both the public site
 * and the admin panel — one value for every visitor, useful for
 * shrinking everything down so more fits on small screens.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['ui_zoom', '100']);
};
