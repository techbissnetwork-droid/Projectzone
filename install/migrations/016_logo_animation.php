<?php
/**
 * Lets an admin turn off the logo's idle tilt/breathe animation for a
 * fully static mark, controlled from Admin > Settings > Branding.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['logo_animation', 'on']);
};
