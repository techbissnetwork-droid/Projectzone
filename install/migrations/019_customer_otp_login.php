<?php
/**
 * Customers no longer have passwords — they sign in with an emailed
 * one-time code or a magic link. otp_codes backs both that login flow
 * and the two-step (old email, then new email) email-change flow on
 * the account page.
 */
return function (PDO $pdo, array $context): void {
    $pdo->exec('ALTER TABLE customers MODIFY password_hash VARCHAR(255) NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        purpose VARCHAR(30) NOT NULL,
        code_hash VARCHAR(64) NOT NULL,
        token_hash VARCHAR(64) NULL,
        new_email VARCHAR(190) NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
