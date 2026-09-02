<?php
declare(strict_types=1);

/**
 * Plain-text email through PHP's mail(). Enough for sign-in codes and
 * notifications on shared hosting; swap send() for SMTP if you outgrow it.
 */
final class Mail
{
    /** @return array{0:bool,1:?string} [sent, error] */
    public static function send(string $to, string $subject, string $body): array
    {
        if (!is_email($to)) {
            return [false, 'Invalid recipient address.'];
        }
        $cfg      = $GLOBALS['TB_CONFIG']['mail'] ?? [];
        $fromMail = Settings::get('mail_from_email', '') ?: (string)($cfg['from_email'] ?? '');
        $fromName = Settings::get('mail_from_name', '')  ?: (string)($cfg['from_name'] ?? Settings::get('site_name', 'TECHBISS'));

        if ($fromMail === '' || !is_email($fromMail)) {
            $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $fromMail = 'no-reply@' . $host;
        }

        /* A header must never carry a newline from user-supplied data. */
        $clean    = static fn(string $v): string => trim(str_replace(["\r", "\n"], '', $v));
        $fromName = $clean($fromName);
        $subject  = $clean($subject);

        $headers = implode("\r\n", [
            'From: ' . self::encodeName($fromName) . ' <' . $clean($fromMail) . '>',
            'Reply-To: ' . $clean(Settings::get('contact_email', $fromMail)),
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'MIME-Version: 1.0',
            'X-Mailer: TECHBISS',
        ]);

        $ok = @mail($to, self::encodeSubject($subject), self::wrap($body), $headers);
        if (!$ok) {
            error_log('[TECHBISS] mail() failed for ' . $to . ' — subject: ' . $subject);
            return [false, 'The server could not send email.'];
        }
        return [true, null];
    }

    private static function encodeSubject(string $s): string
    {
        return preg_match('/[\x80-\xFF]/', $s)
            ? '=?UTF-8?B?' . base64_encode($s) . '?='
            : $s;
    }

    private static function encodeName(string $s): string
    {
        if (preg_match('/[\x80-\xFF]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return '"' . str_replace('"', '', $s) . '"';
    }

    private static function wrap(string $body): string
    {
        return wordwrap(str_replace(["\r\n", "\r"], "\n", $body), 74, "\n", false);
    }
}
