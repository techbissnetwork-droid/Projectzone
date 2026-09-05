<?php
/**
 * Minimal dependency-free mail sender. Uses a raw SMTP client when an
 * admin has configured SMTP in Settings > Email; otherwise falls back
 * to PHP's built-in mail() (works out of the box on many shared hosts,
 * but deliverability varies). No composer/vendor folder is required so
 * the app stays a single flat zip.
 */

/** A newline in any of these becomes header or SMTP-command injection. */
function mail_sanitize_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

function send_mail(string $to, string $subject, string $bodyText): bool
{
    $to = mail_sanitize_header_value($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Refusing to send mail to an invalid address: ' . $to);
        return false;
    }
    $subject = mail_sanitize_header_value($subject);

    $host = mail_sanitize_header_value(trim(get_setting('smtp_host', '')));
    $fromEmail = mail_sanitize_header_value(get_setting('smtp_from_email', '') ?: get_setting('contact_email', 'hello@techbiss.com'));
    $fromName = mail_sanitize_header_value(get_setting('smtp_from_name', '') ?: get_setting('site_name', 'TECHBISS'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('smtp_from_email / contact_email is not a valid address; mail not sent.');
        return false;
    }

    if ($host === '') {
        return mail_fallback($to, $subject, $bodyText, $fromEmail, $fromName);
    }

    $port = (int)(get_setting('smtp_port', '587') ?: 587);
    $user = get_setting('smtp_user', '');
    $pass = get_setting('smtp_pass', '');
    $encryption = get_setting('smtp_encryption', 'tls');

    try {
        return smtp_send($host, $port, $encryption, $user, $pass, $fromEmail, $fromName, $to, $subject, $bodyText);
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        return false;
    }
}

function mail_fallback(string $to, string $subject, string $bodyText, string $fromEmail, string $fromName): bool
{
    $headers = 'From: ' . mail_encode_header($fromName) . ' <' . $fromEmail . ">\r\n"
        . 'Reply-To: <' . $fromEmail . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, mail_encode_header($subject), str_replace(["\r\n", "\r"], "\n", $bodyText), $headers);
}

function mail_encode_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtp_send(
    string $host,
    int $port,
    string $encryption,
    string $user,
    string $pass,
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $bodyText
): bool {
    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Could not connect to $host:$port — $errstr");
    }
    stream_set_timeout($fp, 15);

    $ehloHost = (defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : null) ?: 'localhost';

    smtp_expect($fp, 220);
    smtp_command($fp, 'EHLO ' . $ehloHost, 250);

    if ($encryption === 'tls') {
        smtp_command($fp, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS negotiation failed.');
        }
        smtp_command($fp, 'EHLO ' . $ehloHost, 250);
    }

    if ($user !== '') {
        smtp_command($fp, 'AUTH LOGIN', 334);
        smtp_command($fp, base64_encode($user), 334);
        smtp_command($fp, base64_encode($pass), 235);
    }

    smtp_command($fp, 'MAIL FROM:<' . $fromEmail . '>', 250);
    smtp_command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_command($fp, 'DATA', 354);

    $date = date('r');
    $domain = (defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : null) ?: 'localhost';
    $message = "Date: $date\r\n"
        . 'From: ' . mail_encode_header($fromName) . " <$fromEmail>\r\n"
        . "To: <$to>\r\n"
        . "Reply-To: <$fromEmail>\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . ">\r\n"
        . 'Subject: ' . mail_encode_header($subject) . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "\r\n"
        . smtp_prepare_body($bodyText)
        . "\r\n.\r\n";
    fwrite($fp, $message);
    smtp_expect($fp, 250);

    smtp_command($fp, 'QUIT', 221);
    fclose($fp);
    return true;
}

/**
 * RFC 5321 wants CRLF line endings, and a line that begins with "." must be
 * escaped or it terminates the message early. The previous version wrote
 * bare LF and only dot-stuffed "\n." — so it missed the "\r\n." case and
 * left bodies to be normalised (or mangled) by whichever MTA received them.
 */
function smtp_prepare_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as $i => $line) {
        if (isset($line[0]) && $line[0] === '.') {
            $lines[$i] = '.' . $line;
        }
    }
    return implode("\r\n", $lines);
}

function smtp_command($fp, string $line, $expect): void
{
    fwrite($fp, $line . "\r\n");
    smtp_expect($fp, $expect);
}

function smtp_expect($fp, $expect): void
{
    $code = smtp_read($fp);
    $expected = is_array($expect) ? $expect : [$expect];
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException("Unexpected SMTP response: $code (expected " . implode('/', $expected) . ')');
    }
}

function smtp_read($fp): int
{
    $code = 0;
    while (!feof($fp)) {
        $line = fgets($fp, 512);
        if ($line === false) {
            break;
        }
        $code = (int)substr($line, 0, 3);
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $code;
}
