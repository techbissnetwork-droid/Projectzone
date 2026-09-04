<?php
/**
 * Makes the homepage/about "1,900+ businesses launched", "38 countries
 * served" style numbers editable from Admin > Settings instead of being
 * hardcoded in the page templates. Seeded with the values already shown
 * on the site today, so nothing visually changes until an admin edits them.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['stat1_value', '1,900+']);
    $stmt->execute(['stat1_label', 'Businesses & apps launched']);
    $stmt->execute(['stat2_value', '38']);
    $stmt->execute(['stat2_label', 'Countries served']);
    $stmt->execute(['stat3_value', '4.9/5']);
    $stmt->execute(['stat3_label', 'Customer rating']);
    $stmt->execute(['stat4_value', '72 hrs']);
    $stmt->execute(['stat4_label', 'To your first draft']);
    $stmt->execute(['stat5_value', '9']);
    $stmt->execute(['stat5_label', 'Years in business']);
};
