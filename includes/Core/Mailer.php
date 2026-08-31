<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Minimal mail sender. The 'log' driver writes to storage/logs/mail.log so a
 * fresh installation records notifications truthfully instead of silently
 * pretending mail was delivered.
 */
final class Mailer
{
    public function __construct(private array $cfg, private string $logDir)
    {
    }

    public function send(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        $driver = (string) ($this->cfg['driver'] ?? 'log');
        if ($driver === 'none') {
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $from    = (string) ($this->cfg['from'] ?? 'no-reply@localhost');
        $name    = (string) ($this->cfg['name'] ?? 'TECHBISS');
        $subject = str_replace(["\r", "\n"], '', mb_substr($subject, 0, 180));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->encodeName($name) . ' <' . $from . '>',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        if ($driver === 'mail' && function_exists('mail')) {
            return @mail($to, $subject, $body, implode("\r\n", $headers));
        }

        // log driver
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
        $entry = sprintf(
            "[%s] TO: %s\nSUBJECT: %s\n%s\n\n%s\n%s\n",
            date('c'),
            $to,
            $subject,
            implode("\n", $headers),
            $body,
            str_repeat('-', 72)
        );
        return @file_put_contents($this->logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private function encodeName(string $name): string
    {
        return preg_match('/^[\x20-\x7E]+$/', $name) ? $name : '=?UTF-8?B?' . base64_encode($name) . '?=';
    }
}
