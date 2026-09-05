<?php
/**
 * Site-wide brand color theme (Admin > Settings). One of the 11 palettes
 * explored during design — chosen once by staff for the whole site, not
 * a per-visitor toggle. Empty string means the default "Bloom" palette.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['color_palette', '']);
};
