<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Delivers transactional mail through PHP mail() when configured, and always
 * writes a copy to storage/logs/mail.log so nothing is lost on a host without
 * a mail transport (the log doubles as an outbox for the admin console).
 */
final class Mailer
{
    public function __construct(private array $config, private string $logPath)
    {
    }

    public function send(string $to, string $subject, string $htmlBody, array $options = []): bool
    {
        $from = (string) ($this->config['from_address'] ?? 'no-reply@techbiss.com');
        $fromName = (string) ($this->config['from_name'] ?? 'TECHBISS');
        $replyTo = (string) ($options['reply_to'] ?? $from);

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->encodeName($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $replyTo,
            'X-Mailer: TECHBISS Platform',
        ];

        $this->log($to, $subject, $htmlBody);

        if (($this->config['driver'] ?? 'log') !== 'mail' || !function_exists('mail')) {
            return true;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return @mail($to, $this->encodeSubject($subject), $htmlBody, implode("\r\n", $headers));
    }

    private function encodeSubject(string $subject): string
    {
        return preg_match('/[\x80-\xFF]/', $subject)
            ? '=?UTF-8?B?' . base64_encode($subject) . '?='
            : $subject;
    }

    private function encodeName(string $name): string
    {
        return preg_match('/[\x80-\xFF]/', $name)
            ? '=?UTF-8?B?' . base64_encode($name) . '?='
            : '"' . str_replace('"', '', $name) . '"';
    }

    private function log(string $to, string $subject, string $body): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "[%s] TO=%s SUBJECT=%s\n%s\n%s\n",
            gmdate('c'),
            $to,
            $subject,
            trim(strip_tags($body)),
            str_repeat('-', 72)
        );
        @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
