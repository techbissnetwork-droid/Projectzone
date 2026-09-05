<?php
/**
 * The "Studios" line on the public Contact page ("San Francisco · Lisbon
 * · Singapore") used to be hardcoded in app.js — makes it an editable
 * setting like the other contact details.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['studios_locations', 'San Francisco · Lisbon · Singapore']);
};
