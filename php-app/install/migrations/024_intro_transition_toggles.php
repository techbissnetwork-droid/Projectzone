<?php
/**
 * Lets an admin turn off the one-time intro splash screen and/or the
 * between-page wipe transition, independent of the logo's own idle
 * animation (logo_animation) and each visitor's OS-level reduced-motion
 * preference (which already disables both regardless of these settings).
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['splash_enabled', 'on']);
    $stmt->execute(['page_transition_enabled', 'on']);
};
