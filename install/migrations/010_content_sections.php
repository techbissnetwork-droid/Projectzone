<?php
/**
 * Makes the Services, Industries ("Solutions"), Case Studies, Pricing
 * plans/FAQ and About page (Team & Values) content editable from
 * Admin > Content instead of being hardcoded in assets/app.js. Each
 * section is stored as one JSON blob per settings row, seeded from
 * includes/default_content.php (the exact content already on the site),
 * so nothing changes visually until an admin edits something.
 */
return function (PDO $pdo, array $context): void {
    $defaults = require __DIR__ . '/../../includes/default_content.php';

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['services_json', json_encode($defaults['services'])]);
    $stmt->execute(['solutions_json', json_encode($defaults['solutions'])]);
    $stmt->execute(['case_studies_json', json_encode($defaults['case_studies'])]);
    $stmt->execute(['pricing_json', json_encode($defaults['pricing'])]);
    $stmt->execute(['pricing_faq_json', json_encode($defaults['pricing_faq'])]);
    $stmt->execute(['team_json', json_encode($defaults['team'])]);
    $stmt->execute(['values_json', json_encode($defaults['values'])]);
};
