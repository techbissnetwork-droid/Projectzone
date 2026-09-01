<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Minimal dependency-free mailer.
 * Modes (settings, configurable in the installer and Admin > Settings):
 *   smtp_mode = 'smtp'   -> raw socket SMTP client with SSL (465) or
 *                           STARTTLS (587) and AUTH LOGIN
 *   smtp_mode = 'phpmail'-> PHP's mail() (uses the server's sendmail)
 *   smtp_mode = 'off'    -> emails disabled
 */
class Mailer
{
    public static function enabled(): bool
    {
        return Database::setting('smtp_mode', 'off') !== 'off';
    }

    /**
     * Send an email. Plain text always; pass $html for a rich multipart
     * message (clients that can't render HTML fall back to the text part).
     *
     * $opts records the attempt and shapes the headers:
     *   kind        - what it was for, for the email log filter
     *   member      - member id, so one account's mail can be read on its own
     *   context     - free text kept with the row ("13 pairs: BTCUSDT 1h ...")
     *   unsubscribe - absolute URL; adds the one-click opt-out headers. Set it
     *                 for bulk mail and leave it off transactional mail, where
     *                 an unsubscribe link is meaningless and mildly alarming.
     *
     * Every path through this function records what happened. A send that
     * fails silently is the same to an operator as a send that never happened,
     * and both used to look like success from the outside.
     */
    public static function send(string $to, string $subject, string $body, ?string $html = null,
                                array $opts = []): bool
    {
        $kind = (string)($opts['kind'] ?? EmailLog::OTHER);
        $member = (int)($opts['member'] ?? 0);
        $ctx = (string)($opts['context'] ?? '');
        // What the LOG should say, when that is not what the subject says.
        //
        // The log exists to answer "did this send, and to whom". It is not a
        // copy of the message, and for one message it must not be: a
        // verification code goes in the subject line on purpose - phones offer
        // to autofill it from there - and the log was storing that subject
        // verbatim, so every live code sat in plain text on the Email log page
        // for anyone with panel access to read and use before the member did.
        // A code that is written down somewhere else is not a possession
        // proof.
        $logSubject = (string)($opts['log_subject'] ?? $subject);
        $log = static function (bool $ok, string $why) use ($to, $logSubject, $kind, $member, $ctx): bool {
            EmailLog::record($to, $logSubject, $ok, $why, $kind, $member, $ctx);
            return $ok;
        };

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $log(false, 'Not a valid email address');
        }
        $mode = Database::setting('smtp_mode', 'off');
        if ($mode === 'off') {
            return $log(false, 'Email sending is switched off (Settings > Alerts > Email / SMTP)');
        }

        // Hard-validate the From address: it is spliced into raw mail headers
        // and the SMTP envelope, so a malformed value (or anything containing
        // CR/LF) must never pass through.
        $fromEmail = Database::setting('smtp_from_email', 'noreply@localhost');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'noreply@localhost';
        }
        $fromName  = Database::setting('smtp_from_name', Database::setting('site_name', 'SignalMasterAi'));
        [$typeHeader, $mimeBody] = self::buildMime($body, $html);

        // One-click unsubscribe. Gmail and Yahoo require these headers from any
        // bulk sender, and without them alert mail is filtered long before a
        // member ever decides whether they want it.
        $unsub = '';
        if (!empty($opts['unsubscribe']) && is_string($opts['unsubscribe'])) {
            $u = str_replace(["\r", "\n"], '', $opts['unsubscribe']);
            $unsub = 'List-Unsubscribe: <' . $u . ">\r\n"
                   . "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        }

        if ($mode === 'phpmail') {
            // Date and Message-ID, which this path was not sending.
            //
            // The SMTP client below always wrote both; mail() was left to
            // whatever the host's sendmail decided to add, and many add
            // neither. RFC 5322 requires Date and From on every message, and
            // Message-ID is what every large receiver uses to thread and
            // de-duplicate - a message arriving without one is scored as
            // machine-generated bulk by Gmail and Outlook, which is precisely
            // how "the log says sent" and "it never arrived" happen together.
            //
            // The Message-ID domain comes from the From address, so it matches
            // the domain being authenticated by SPF and signed by DKIM.
            $idHost = substr((string)strrchr($fromEmail, '@'), 1) ?: 'localhost';
            $headers = 'From: ' . self::encodeHeader($fromName) . " <$fromEmail>\r\n"
                     . 'Date: ' . date('r') . "\r\n"
                     . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $idHost . ">\r\n"
                     . "MIME-Version: 1.0\r\n"
                     . $unsub
                     . $typeHeader;
            // -f sets the envelope sender. Without it the host sends as its own
            // system user, so the address SPF is checked against is not the one
            // the domain published a record for, and a correctly configured SPF
            // fails anyway.
            $ok = @mail($to, self::encodeHeader($subject), $mimeBody, $headers, '-f' . $fromEmail);
            if (!$ok) {
                $ok = @mail($to, self::encodeHeader($subject), $mimeBody, $headers);
            }
            return $log($ok, $ok ? '' : 'PHP mail() refused it - the server has no working sendmail, '
                . 'or the From address is not one it will send for');
        }

        try {
            self::smtpSend($to, $subject, $typeHeader, $mimeBody, $fromEmail, $fromName, $unsub);
            return $log(true, '');
        } catch (\Throwable $e) {
            // Still to the server log, for anyone who can read one - but now
            // also somewhere the operator can actually see it.
            error_log('SignalMasterAi mail error: ' . $e->getMessage());
            ErrorLog::record(ErrorLog::ALERTS, 'SMTP send failed - ' . $e->getMessage(), $kind);
            return $log(false, $e->getMessage());
        }
    }

    /**
     * multipart/alternative when HTML present; [content-type header, body].
     *
     * Encoded quoted-printable, not 8bit, and that is a deliverability fix
     * rather than a tidiness one. The template builds its HTML as a single
     * unbroken string - one measured alert email carried a line of 3,539
     * characters, where RFC 5321 puts the hard limit at 998 octets. An MTA is
     * entitled to reject that outright, and the ones that do not usually fold
     * it themselves at some arbitrary point, which lands a broken table in the
     * reader's inbox. Declaring 8bit made it worse: it is only legal to a
     * server that advertised 8BITMIME, and the emoji and box-drawing
     * characters in these messages are 8-bit UTF-8.
     *
     * quoted-printable answers both at once - it folds every line with soft
     * breaks under 76 characters and escapes anything above ASCII - and needs
     * nothing from the receiving server.
     */
    private static function buildMime(string $text, ?string $html): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $qp = static fn(string $s): string => quoted_printable_encode($s);
        if ($html === null) {
            return ["Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n",
                    $qp($text)];
        }
        $b = 'sma' . bin2hex(random_bytes(12));
        $body = "--$b\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
              . $qp($text) . "\r\n"
              . "--$b\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
              . $qp(str_replace(["\r\n", "\r"], "\n", $html)) . "\r\n"
              . "--$b--";
        return ["Content-Type: multipart/alternative; boundary=\"$b\"\r\n", $body];
    }

    /**
     * Branded HTML email wrapper. $rows: content HTML placed in the body;
     * optional CTA button. Inline CSS only - email-client safe.
     */
    public static function template(string $heading, string $rowsHtml, string $ctaText = '', string $ctaUrl = ''): string
    {
        $site = htmlspecialchars(Database::setting('site_name', 'SignalMasterAi'), ENT_QUOTES);
        $url = htmlspecialchars(Database::setting('site_url', '#'), ENT_QUOTES);
        $h = htmlspecialchars($heading, ENT_QUOTES);
        $cta = '';
        if ($ctaText !== '' && $ctaUrl !== '') {
            $cta = '<tr><td align="center" style="padding:8px 32px 28px">'
                 . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES) . '" style="display:inline-block;background-color:#4F5CE8;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 34px;border-radius:9px">'
                 . htmlspecialchars($ctaText, ENT_QUOTES) . '</a></td></tr>';
        }
        return '<!doctype html><html><body style="margin:0;padding:0;background-color:#eef1f6">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef1f6;padding:28px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;box-shadow:0 4px 24px rgba(20,30,60,0.10)">'
            . '<tr><td style="background-color:#4F5CE8;padding:22px 32px">'
            . '<span style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:0.3px">' . $site . '</span></td></tr>'
            . '<tr><td style="padding:28px 32px 6px"><h1 style="margin:0;font-size:21px;color:#101828">' . $h . '</h1></td></tr>'
            . '<tr><td style="padding:10px 32px 20px;color:#344054;font-size:14px;line-height:1.7">' . $rowsHtml . '</td></tr>'
            . $cta
            . '<tr><td style="padding:18px 32px 26px;border-top:1px solid #e7ebf1;color:#98a2b3;font-size:11.5px;line-height:1.7">'
            . htmlspecialchars(Database::setting('site_notice'), ENT_QUOTES) . '<br>'
            . 'You receive this because you enabled signal alerts on <a href="' . $url . '" style="color:#6E7BFF;text-decoration:none">' . $site . '</a>. Disable them any time from the site.'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function smtpSend(string $to, string $subject, string $typeHeader, string $mimeBody, string $fromEmail, string $fromName, string $unsub = ""): bool
    {
        $host = Database::setting('smtp_host');
        $port = (int)Database::setting('smtp_port', '587');
        $sec  = Database::setting('smtp_security', 'tls');   // tls (STARTTLS) | ssl | none
        $user = Database::setting('smtp_user');
        $pass = Database::setting('smtp_pass');
        if ($host === '' || $port <= 0) {
            throw new \RuntimeException('SMTP host/port not configured');
        }

        $remote = ($sec === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("connect failed: $errstr");
        }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;   // last line of a (possibly multi-line) reply
                }
            }
            return $data;
        };
        $cmd = function (string $c, array $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            $code = (int)substr($r, 0, 3);
            if (!in_array($code, $expect, true)) {
                throw new \RuntimeException("SMTP error after \"$c\": " . trim($r));
            }
            return $r;
        };

        $greet = $read();
        if ((int)substr($greet, 0, 3) !== 220) {
            throw new \RuntimeException('bad SMTP greeting: ' . trim($greet));
        }
        $myHost = parse_url(Database::setting('vapid_subject', 'mailto:admin@localhost'), PHP_URL_HOST) ?: 'localhost';
        $cmd('EHLO ' . $myHost, [250]);

        if ($sec === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS negotiation failed');
            }
            $cmd('EHLO ' . $myHost, [250]);
        }

        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }

        $cmd('MAIL FROM:<' . $fromEmail . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $headers = 'From: ' . self::encodeHeader($fromName) . " <$fromEmail>\r\n"
                 . "To: <$to>\r\n"
                 . 'Subject: ' . self::encodeHeader($subject) . "\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 // Keyed to the From domain, not to $myHost. $myHost comes from
                 // the push VAPID contact - a setting with nothing to do with
                 // email - so a site sending as alerts@example.com was stamping
                 // Message-IDs from whatever host that happened to name. A
                 // Message-ID domain that does not match the signed From domain
                 // is one more thing for a receiver to hold against the message.
                 . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@'
                 . (substr((string)strrchr($fromEmail, '@'), 1) ?: $myHost) . ">\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . $unsub
                 . $typeHeader;
        // dot-stuffing per RFC 5321
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\n", $mimeBody));
        $data = str_replace("\n", "\r\n", (string)$data);
        $cmd($headers . "\r\n" . $data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }

    /** RFC 2047 encode a header value when it contains non-ASCII. */
    private static function encodeHeader(string $s): string
    {
        if (preg_match('/[^\x20-\x7e]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }
}
