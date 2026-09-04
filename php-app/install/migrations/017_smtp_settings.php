<?php
/**
 * SMTP configuration for outbound mail (OTP codes, magic links, ticket
 * notifications). Left blank by default — send_mail() falls back to
 * PHP's built-in mail() until an admin fills these in.
 */
return function (PDO $pdo, array $context): void {
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    foreach ([
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
    ] as $key => $value) {
        $stmt->execute([$key, $value]);
    }
};
