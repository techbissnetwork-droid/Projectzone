<?php
/**
 * Seeds a generic Privacy Policy and Terms & Conditions so /privacy and
 * /terms aren't blank on a fresh install — this is placeholder text, not
 * legal advice, and is meant to be reviewed and edited in
 * admin/settings.php (Legal tab) before a real launch.
 */
return function (PDO $pdo, array $context): void {
    $privacy = "We collect the information you give us directly — your name, email address, phone number and any details you share through our contact form, account signup, or when we're doing work for your business.\n\n"
        . "We use this information to respond to your enquiries, provide the services you've asked for, send you account-related emails (like sign-in codes), and — only if you've opted in — occasional updates about our services.\n\n"
        . "We don't sell your personal information. We may share it with service providers who help us run the business (for example, hosting or email delivery providers), bound by their own confidentiality obligations, or when required by law.\n\n"
        . "We keep your information for as long as your account is active or as needed to provide services, and take reasonable technical and organizational measures to protect it.\n\n"
        . "You can ask us to access, correct, or delete your personal information at any time by getting in touch through our Contact page.\n\n"
        . "We may update this policy from time to time; the \"last updated\" date above will always reflect the most recent version.";

    $terms = "By using our website or engaging us for services, you agree to these terms.\n\n"
        . "Our services (websites, apps, hosting, domain and email setup, and related work) are provided on the basis described in your individual quote or agreement with us. Where a quote and these terms differ, the quote takes precedence.\n\n"
        . "You're responsible for the accuracy of the content and information you provide us for your project, and for keeping any account credentials we issue you secure.\n\n"
        . "Unless otherwise agreed in writing, ownership of a custom-built website, app or domain transfers to you on full payment; ready-made marketplace themes are licensed for use on a single live project unless stated otherwise.\n\n"
        . "We aim for high availability on anything we host for you, but we don't guarantee uninterrupted service, and we're not liable for indirect or consequential losses arising from its use.\n\n"
        . "We may update these terms from time to time; continued use of our services after an update means you accept the revised terms.";

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    $stmt->execute(['privacy_policy', $privacy]);
    $stmt->execute(['privacy_updated_at', date('F Y')]);
    $stmt->execute(['terms_conditions', $terms]);
    $stmt->execute(['terms_updated_at', date('F Y')]);
};
