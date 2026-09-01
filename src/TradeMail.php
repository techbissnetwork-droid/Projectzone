<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The two emails a paper trade generates: it opened, and it finished.
 *
 * WHY THIS RUNS FROM CRON AND NOT FROM THE BUTTON.
 *
 * Opening a position is a click, and a click that waits on an SMTP handshake
 * is a click that feels broken - on a slow relay the order ticket would sit
 * there for seconds with the trade already booked. So nothing is sent inline.
 * Each row carries open_mailed_at and close_mailed_at, and this sweeps for the
 * ones that are still null.
 *
 * That shape buys two things a fire-and-forget send does not: a message that
 * failed is retried on the next pass, because the stamp was never written; and
 * a message that succeeded can never be sent twice, because the stamp is
 * written immediately after and the next pass no longer sees the row.
 *
 * WHAT GOES IN THEM.
 *
 * Everything needed to check the site's arithmetic without opening the site.
 * The open mail carries the plan as published - entry, stop, every target, the
 * size, the leverage, the margin at risk and the deadline. The close mail
 * carries what actually happened - the exit price, which of the four endings
 * it was, the money, the R, and the balance afterwards - because "your trade
 * closed" with no numbers in it is a notification, not a report.
 */
class TradeMail
{
    /** Is trade mail switched on at all? Site switch, then the alerts master. */
    public static function enabled(): bool
    {
        return Database::setting('trade_email_enabled', '1') === '1'
            && Master::on('alerts', 'email')
            && Mailer::enabled();
    }

    /**
     * Send what has not been sent. Returns how many went out.
     *
     * Bounded per pass: a site that has just been switched on could have a
     * thousand historic closes waiting, and mailing all of them in one cron
     * run is both a slow run and a spam report.
     */
    public static function sweep(int $limit = 40): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $pdo = Database::pdo();
        $sent = 0;

        // Opens first: a member should hear that a trade started before they
        // hear that it finished, and on a fast timeframe both can be waiting
        // in the same pass.
        try {
            $rows = $pdo->query(
                "SELECT t.*, m.email FROM paper_trades t
                   JOIN members m ON m.id = t.member_id
                  WHERE t.open_mailed_at IS NULL AND m.email <> ''
                  ORDER BY t.opened_at ASC LIMIT " . (int)$limit
            )->fetchAll();
        } catch (\Throwable $e) {
            return 0;                       // columns not there yet - see Database
        }
        $stampOpen = $pdo->prepare('UPDATE paper_trades SET open_mailed_at = ? WHERE id = ?');
        foreach ($rows as $t) {
            // A trade that opened and closed between two cron passes gets one
            // message, not two. The close mail says everything the open mail
            // would have and more, so the open is stamped as handled rather
            // than sent - the alternative is two emails arriving together
            // describing the same finished trade.
            if (($t['status'] ?? '') !== 'open') {
                $stampOpen->execute([time(), (int)$t['id']]);
                continue;
            }
            if (!self::wants((int)$t['member_id'])) {
                $stampOpen->execute([time(), (int)$t['id']]);
                continue;
            }
            if (self::sendOpen($t)) {
                $sent++;
            }
            // Stamped either way. A member whose address bounces would
            // otherwise have every pass retry them for ever; Mailer already
            // records the failure in the email log, which is where an operator
            // looks for it.
            $stampOpen->execute([time(), (int)$t['id']]);
        }

        try {
            $rows = $pdo->query(
                "SELECT t.*, m.email FROM paper_trades t
                   JOIN members m ON m.id = t.member_id
                  WHERE t.close_mailed_at IS NULL AND t.status = 'closed' AND m.email <> ''
                  ORDER BY t.closed_at ASC LIMIT " . (int)$limit
            )->fetchAll();
        } catch (\Throwable $e) {
            return $sent;
        }
        $stampClose = $pdo->prepare('UPDATE paper_trades SET close_mailed_at = ? WHERE id = ?');
        foreach ($rows as $t) {
            if (self::wants((int)$t['member_id']) && self::sendClose($t)) {
                $sent++;
            }
            $stampClose->execute([time(), (int)$t['id']]);
        }
        return $sent;
    }

    /**
     * Does this member want them?
     *
     * Defaults to ON, because a trade of your own opening and closing is not
     * marketing - it is the receipt - and a member who does not want receipts
     * turns them off in one place on their account page. The opt-out is the
     * same one that governs every other email the site sends them, so
     * unsubscribing once means unsubscribing.
     */
    private static function wants(int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }
        try {
            $p = MemberPrefs::get($memberId);
            return ($p['trade_email'] ?? '1') !== '0';
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * A price at the pair's own precision, so a sub-cent coin is not "0.00".
     *
     * A dash for a price that is not there. exit_price is null on rows closed
     * before that column existed, and printing those as "0" told a member
     * their position left the market at zero.
     */
    private static function px($v): string
    {
        if ($v === null || $v === '' || (float)$v <= 0) {
            return '—';
        }
        return View::price((float)$v);
    }

    private static function money(float $v): string
    {
        return ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);
    }

    private static function link(array $t): string
    {
        $base = rtrim((string)Database::setting('site_url'), '/');
        return $base === '' ? '' : $base . '/portfolio.php';
    }

    /**
     * "Opened" - the plan, exactly as it was booked.
     */
    private static function sendOpen(array $t): bool
    {
        $side = strtoupper((string)$t['side']) === 'BUY' ? 'Long' : 'Short';
        $sym = (string)$t['symbol'];
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        $deadline = Paper::deadline($t);
        $margin = (float)$t['margin'];
        $lev = (float)$t['leverage'];

        // Rows shared by both formats, so the text and the HTML can never
        // describe different trades - which is the failure mode of writing a
        // message twice.
        $rows = [
            'Pair'        => $sym . '  ' . $t['tf'],
            'Direction'   => $side,
            'Entry'       => self::px($t['entry']),
            'Stop loss'   => self::px($t['stop_loss']),
        ];
        foreach ([1, 2, 3] as $n) {
            if ((float)($t['tp' . $n] ?? 0) > 0) {
                $rows['Target ' . $n] = self::px($t['tp' . $n]);
            }
        }
        $rows['Size'] = rtrim(rtrim(number_format((float)$t['units'], 8, '.', ''), '0'), '.')
                      . ' ' . preg_replace('/USDT?$/', '', $sym);
        $rows['Margin at risk'] = self::money($margin) . ($lev > 1 ? '  at ' . rtrim(rtrim(number_format($lev, 2, '.', ''), '0'), '.') . 'x' : '');
        // The number that answers "what is the worst case", which on an
        // isolated position is the margin and not the notional. Said plainly
        // because it is the one figure a reader most often gets wrong.
        $rows['Most this can lose'] = self::money($margin);
        $rows['Time stop'] = gmdate('D j M H:i', $deadline) . ' UTC  ('
            . View::span(max(0, $deadline - time())) . ' from now)';
        [$srcLabel] = Paper::sourceTag((string)($t['source'] ?? 'paper'));
        $rows['Opened from'] = $srcLabel;

        $subject = $side . ' ' . $sym . ' opened - ' . $siteName . ' paper trade';
        return self::deliver((string)$t['email'], (int)$t['member_id'], $subject,
            'Position opened', $rows,
            'It settles itself at the stop, a target or the time stop above. '
            . 'Nothing here is real money.',
            self::link($t), 'Open your portfolio',
            $sym . ' ' . $t['tf'] . ' open');
    }

    /**
     * "Closed" - what happened, and what it cost or paid.
     */
    private static function sendClose(array $t): bool
    {
        $side = strtoupper((string)$t['side']) === 'BUY' ? 'Long' : 'Short';
        $sym = (string)$t['symbol'];
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        $pnl = (float)$t['pnl'];
        $r = (float)$t['outcome_r'];
        $note = trim((string)($t['note'] ?? ''));
        $held = max(0, (int)$t['closed_at'] - (int)$t['opened_at']);

        // WON, LOST, OR NEITHER - BY THE MONEY, NOT BY THE LABEL.
        //
        // The same definition the track record uses. A trade closed at its
        // first target after a deep drawdown and a trade stopped at break-even
        // both land where the money says, and calling one a win because it
        // reached a target the other did not is how two pages end up
        // disagreeing about the same row.
        $verdict = $pnl > 0 ? 'Profit' : ($pnl < 0 ? 'Loss' : 'Flat');
        $rows = [
            'Pair'      => $sym . '  ' . $t['tf'],
            'Direction' => $side,
            'Result'    => $verdict . '  ' . self::money($pnl)
                         . '  (' . ($r >= 0 ? '+' : '') . number_format($r, 2) . 'R)',
            'How it ended' => $note !== '' ? $note : 'closed',
            'Entry'     => self::px($t['entry']),
            'Exit'      => self::px($t['exit_price'] ?? null),
            'Stop loss' => self::px($t['stop_loss']),
        ];
        foreach ([1, 2, 3] as $n) {
            if ((float)($t['tp' . $n] ?? 0) > 0) {
                $rows['Target ' . $n] = self::px($t['tp' . $n]);
            }
        }
        $rows['Margin'] = self::money((float)$t['margin']);
        if ((float)$t['leverage'] > 1) {
            $rows['Leverage'] = rtrim(rtrim(number_format((float)$t['leverage'], 2, '.', ''), '0'), '.') . 'x';
        }
        $rows['Held for'] = View::span($held);
        // The excursions, when the settler recorded them. How far it went
        // against you before it worked is the number that decides whether you
        // could actually have held it, and it is missing from most reports.
        if (($t['mae_r'] ?? null) !== null) {
            $rows['Worst point'] = number_format((float)$t['mae_r'], 2) . 'R against you';
        }
        if (($t['mfe_r'] ?? null) !== null) {
            $rows['Best point'] = '+' . number_format((float)$t['mfe_r'], 2) . 'R in your favour';
        }
        try {
            $funds = Paper::funds((int)$t['member_id']);
            $rows['Wallet now'] = self::money((float)($funds['balance'] ?? 0))
                . '  (' . self::money((float)($funds['available'] ?? 0)) . ' free)';
        } catch (\Throwable $e) {
            // a wallet that cannot be read is not a reason to withhold the rest
        }

        $mark = $pnl > 0 ? "\u{2705}" : ($pnl < 0 ? "\u{274C}" : "\u{2796}");
        $subject = $mark . ' ' . $sym . ' closed ' . strtolower($verdict) . ' '
                 . self::money($pnl) . ' - ' . $siteName;
        return self::deliver((string)$t['email'], (int)$t['member_id'], $subject,
            'Position closed - ' . $verdict, $rows,
            'Simulated. Your full record, including this trade, is on the portfolio page.',
            self::link($t), 'See your portfolio',
            $sym . ' ' . $t['tf'] . ' close ' . number_format($r, 2) . 'R');
    }

    /**
     * One send, both formats, from one set of rows.
     *
     * @param array<string,string> $rows
     */
    private static function deliver(string $to, int $memberId, string $subject, string $heading,
                                    array $rows, string $footNote, string $link, string $cta,
                                    string $logContext): bool
    {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $optOut = MemberAuth::optOutUrl($memberId);

        $text = $heading . "\n\n";
        foreach ($rows as $k => $v) {
            // Wide enough for the longest label plus a space. At 18 the
            // longest one - "Most this can lose:" - filled the column exactly
            // and the value ran straight into the colon.
            $text .= str_pad($k . ':', 21) . $v . "\n";
        }
        $text .= "\n" . $footNote . "\n";
        if ($link !== '') {
            $text .= "\n" . $link . "\n";
        }
        $text .= "\n--\nStop these emails: " . $optOut . "\n";

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" '
              . 'style="width:100%;font-size:13.5px;color:#344054">';
        $first = true;
        foreach ($rows as $k => $v) {
            $edge = $first ? '' : ';border-top:1px solid #eef1f6';
            $html .= '<tr><td style="padding:6px 0;color:#667085' . $edge . '">'
                   . htmlspecialchars($k, ENT_QUOTES) . '</td>'
                   . '<td align="right" style="padding:6px 0;font-weight:700;color:#101828' . $edge . '">'
                   . htmlspecialchars($v, ENT_QUOTES) . '</td></tr>';
            $first = false;
        }
        $html .= '</table><p style="margin:16px 0 0;font-size:12.5px;color:#667085">'
               . htmlspecialchars($footNote, ENT_QUOTES) . '</p>'
               . '<p style="margin:14px 0 0;font-size:11.5px;color:#98a2b3">'
               . '<a href="' . htmlspecialchars($optOut, ENT_QUOTES) . '" style="color:#98a2b3">'
               . 'Stop these emails</a></p>';

        return Mailer::send($to, $subject, $text,
            Mailer::template($heading, $html, $link !== '' ? $cta : '', $link),
            ['kind' => EmailLog::TRADE, 'member' => $memberId,
             'unsubscribe' => $optOut, 'context' => $logContext]);
    }
}
