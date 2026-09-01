<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Email alerts on BUY/SELL flips for members who opted in.
 * Driven by cron.php, mirroring the Web Push dispatcher.
 */
class EmailAlerts
{
    /** All pairs watched by any email-alert member (for cron analysis targets). */
    public static function watchedPairs(): array
    {
        // WHO IS ENTITLED TO WHAT, not merely who asked.
        //
        // This collected every watched pair from every member with no idea of
        // tier, so a premium coin watched by an account whose plan had run out
        // stayed in the scan rotation for ever - budget spent on an analysis
        // that the dispatcher would then decline to send, to the only person
        // watching it. Membership::expireDue() prunes those lists, but a pair
        // can also become unreachable because the OPERATOR moved a coin to
        // premium, which no member action will ever clean up.
        //
        // The tier is taken from the row, expiry included, which is the same
        // rule the dispatchers apply a few lines later. A pair nobody entitled
        // is watching is simply not returned.
        $pdo = Database::pdo();
        $symTier = [];
        foreach ($pdo->query('SELECT symbol, tier FROM symbols WHERE enabled = 1') as $r) {
            $symTier[(string)$r['symbol']] = (string)$r['tier'];
        }
        $out = [];
        $rows = $pdo->query(
            'SELECT a.pairs, m.tier AS mtier, m.paid_until
               FROM member_alerts a LEFT JOIN members m ON m.id = a.member_id
              WHERE a.enabled = 1'
        );
        foreach ($rows as $row) {
            $viewer = ($row['mtier'] ?? 'free') === 'paid'
                      && ((int)($row['paid_until'] ?? 0) === 0 || (int)$row['paid_until'] >= time())
                ? 'paid' : (($row['mtier'] ?? '') === '' ? 'guest' : 'free');
            foreach (array_filter(explode(',', (string)$row['pairs'])) as $p) {
                $sym = strtok($p, ':');
                if ($sym === false || !isset($symTier[$sym])) {
                    continue;
                }
                if (MemberAuth::canAccess($symTier[$sym], $viewer)) {
                    $out[$p] = true;
                }
            }
        }
        return array_keys($out);
    }

    /**
     * Daily digest: one summary email per alert-enabled member covering the
     * last 24h - flips on their watched pairs + the site's verified stats.
     * Members with zero relevant flips are skipped (no empty spam).
     */
    public static function sendDigests(): int
    {
        $pdo = Database::pdo();
        $since = time() - 86400;
        // Never surface a flip weaker than what push/email/Telegram would
        // have alerted about - see Publish::alertFloor(), the same floor
        // dispatchFlips() applies below. Without this a digest recapped
        // grade-C flips that never triggered a real-time alert, and on a
        // site with a publish floor set were not even visible anywhere else
        // on the site - a coin-page link in the email pointing at nothing.
        $floor = Publish::alertFloor();
        $gradeSql = '';
        if ($floor !== '') {
            $ok = [];
            foreach (Publish::RANK as $g => $rank) {
                if ($rank >= Publish::RANK[$floor]) {
                    $ok[] = "'" . $g . "'";
                }
            }
            $gradeSql = " AND (grade = '' OR grade IN (" . implode(',', $ok) . '))';
        }
        $flipStmt = $pdo->prepare(
            'SELECT `signal`, score, price, created_at, outcome FROM signals
             WHERE symbol = ? AND tf = ? AND created_at > ?' . $gradeSql . ' ORDER BY created_at DESC LIMIT 5'
        );
        $site = Database::setting('site_url');
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        $stats = $pdo->query(
            // Money, not labels - the digest quotes this next to the track
            // record and the two have to be the same measurement. Also the
            // same publish floor the track record page itself applies
            // (Publish::sql()), so this figure and that page never disagree
            // about what counts as a verified signal here.
            "SELECT COUNT(*) t, SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w
             FROM signals WHERE outcome IN ('confirmed','invalid') AND closed_at > " . $since
            . Publish::sql()
        )->fetch();
        $sent = 0;
        $rows = $pdo->query(
            'SELECT a.pairs, a.member_id, m.email FROM member_alerts a
             JOIN members m ON m.id = a.member_id WHERE a.enabled = 1'
        )->fetchAll();
        foreach ($rows as $row) {
            $pairs = array_slice(array_filter(explode(',', $row['pairs'])), 0, 30);
            $lines = [];
            $rowsHtml = '';
            foreach ($pairs as $pair) {
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $flipStmt->execute([$ps, $ptf, $since]);
                foreach ($flipStmt->fetchAll() as $f) {
                    if (!in_array($f['signal'], ['BUY', 'SELL'], true)) {
                        continue;
                    }
                    $oc = $f['outcome'] === 'confirmed' ? ' - hit target'
                        : ($f['outcome'] === 'invalid' ? ' - stopped out' : '');
                    $lines[] = gmdate('H:i', (int)$f['created_at']) . " UTC  $ps ($ptf): {$f['signal']} @ "
                        . View::price($f['price'] ?? null) . $oc;
                    $col = $f['signal'] === 'BUY' ? '#0D8762' : '#D73E35';
                    $rowsHtml .= '<tr><td style="padding:5px 0;color:#667085">' . gmdate('H:i', (int)$f['created_at']) . ' UTC</td>'
                        . '<td style="padding:5px 0;font-weight:700">' . htmlspecialchars("$ps ($ptf)", ENT_QUOTES) . '</td>'
                        . '<td style="padding:5px 0;font-weight:800;color:' . $col . '">' . $f['signal'] . '</td>'
                        . '<td style="padding:5px 0" align="right">' . htmlspecialchars(View::price($f['price'] ?? null), ENT_QUOTES) . $oc . '</td></tr>';
                }
            }
            if (!$lines) {
                continue;   // nothing happened on their coins - no email
            }
            $text = "Your daily signal digest:\n\n" . implode("\n", array_slice($lines, 0, 20))
                . "\n\nSite-wide last 24h: " . (int)($stats['t'] ?? 0) . ' verified signals, '
                . (int)($stats['w'] ?? 0) . " hit target.\n\n--\n" . Database::setting('site_notice');
            $html = Mailer::template('Your daily signal digest',
                '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;color:#344054">'
                . $rowsHtml . '</table>'
                . '<p style="margin:14px 0 0;color:#667085;font-size:12px">Site-wide last 24h: '
                . (int)($stats['t'] ?? 0) . ' verified signals, ' . (int)($stats['w'] ?? 0) . ' hit target.</p>',
                'Open live charts', $site !== '' ? $site . '/charts.php' : '');
            $optOut = MemberAuth::optOutUrl((int)($row['member_id'] ?? 0));
            if (Mailer::send((string)$row['email'], "Daily digest - $siteName",
                    $text . self::footerText($optOut), $html . self::footerHtml($optOut),
                    ['kind' => EmailLog::DIGEST, 'member' => (int)($row['member_id'] ?? 0),
                     'unsubscribe' => $optOut,
                     'context' => count($lines) . ' flip(s) on watched pairs'])) {
                $sent++;
            }
        }
        return $sent;
    }

    /** Send an email for each watched pair that flipped to BUY or SELL. */
    public static function dispatchFlips(): int
    {
        $pdo = Database::pdo();
        // Why an alert never arrived.
        //
        // Every reason this sweep sends nothing is a silent one: mail is not
        // configured, the row is switched off, the pair has never been
        // analysed so there is no verdict to compare against. Each returns
        // zero and reports success, and the member is left refreshing an
        // inbox. They are written down now, once each, with the thing to
        // change - a member saying "I never got an email" should be answerable
        // from the admin panel rather than from a guess.
        if (!Mailer::enabled()) {
            $waiting = (int)$pdo->query("SELECT COUNT(*) FROM member_alerts
                                         WHERE enabled = 1 AND pairs != ''")->fetchColumn();
            if ($waiting > 0) {
                ErrorLog::record(ErrorLog::ALERTS,
                    'Email alerts are switched on but no mail method is configured, so nothing '
                    . 'can be delivered',
                    $waiting . ' member(s) waiting. Settings > Email, "How email is sent".');
            }
            return 0;
        }
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        // Never looser than what the site actually publishes - see
        // Publish::alertFloor(). A member pinged about a signal that is not
        // on the scanner, the coin page or the API is looking for a dead link.
        $minGrade = Publish::alertFloor();
        $sent = 0;

        $rows = $pdo->query(
            'SELECT a.*, m.email, m.tier AS mtier, m.paid_until FROM member_alerts a
             JOIN members m ON m.id = a.member_id WHERE a.enabled = 1'
        )->fetchAll();

        // Look each coin up ONCE, not once per member watching it.
        //
        // The tier of a coin and its current verdict depend on the coin, not on
        // who is subscribed to it, but both were queried inside the per-member
        // per-pair loop. A thousand members watching three hundred coins meant
        // six hundred thousand queries a minute to produce twelve hundred
        // distinct answers, and the bill grew with the subscriber list.
        //
        // The rows are already in memory, so the pre-pass that collects the
        // coins costs nothing but string work.
        $wantSyms = [];
        foreach ($rows as $row) {
            foreach (array_filter(explode(',', (string)$row['pairs'])) as $p) {
                $wantSyms[strtok($p, ':')] = true;
            }
        }
        $symTiers = Scanner::alertTiers();
        $states = Scanner::currentFor(array_keys($wantSyms));

        foreach ($rows as $row) {
            $pairs = array_filter(explode(',', $row['pairs']));
            if (!$pairs) {
                continue;
            }
            $viewer = ($row['mtier'] === 'paid' && (int)$row['paid_until'] > 0 && (int)$row['paid_until'] < time())
                ? 'free' : $row['mtier'];
            $last = json_decode($row['last_sent'] ?: '{}', true) ?: [];
            $changed = false;
            $hits = [];

            foreach ($pairs as $pair) {
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $symTier = $symTiers[$ps] ?? false;
                if ($symTier === false) {
                    ErrorLog::record(ErrorLog::ALERTS,
                        'A watched pair is not an enabled coin, so it can never alert',
                        $pair . ' - add or re-enable it under Coins, or the member should '
                        . 'drop it from their watchlist.');
                    continue;
                }
                if (!MemberAuth::canAccess((string)$symTier, $viewer)) {
                    continue;   // deliberate: the coin is sold above this member's tier
                }
                $sig = $states[$ps . ':' . $ptf] ?? null;
                if (!$sig) {
                    // Nothing has ever analysed this pair on this timeframe, so
                    // there is no verdict to flip from. The member watched it,
                    // the site accepted it, and it was never going to fire.
                    ErrorLog::record(ErrorLog::ALERTS,
                        'A watched pair has never been analysed, so no alert can fire for it',
                        $pair . ' - tick the coin for the scan under Coins and include that '
                        . 'timeframe in Settings > Market data.');
                    continue;
                }
                $prev = $last[$pair] ?? null;
                if (Scanner::shouldAlert($prev, $sig, $ptf)
                    && in_array($sig['signal'], ['BUY', 'SELL'], true)
                    && WebPush::gradePasses($minGrade, (string)($sig['indicators'] ?? ''))) {
                    // The member's own filters: grade, confidence, direction,
                    // quiet hours and daily cap. Previously the admin had one
                    // site-wide minimum grade and members had no control at
                    // all, so the only way to get fewer alerts was to stop
                    // watching pairs entirely.
                    $indsF = json_decode((string)($sig['indicators'] ?? ''), true);
                    [$allowed, $why] = MemberPrefs::alertAllowed((int)$row['member_id'], [
                        'signal' => $sig['signal'],
                        'grade' => is_array($indsF) ? ($indsF['grade'] ?? null) : null,
                        // The signal's type, so the member's type filter has
                        // something to read. Without this the filter would
                        // see 0 on every call and quietly pass everything.
                        'rr_tier' => is_array($indsF) && is_array($indsF['levels'] ?? null)
                            ? (int)($indsF['levels']['rr_tier'] ?? 0) : 0,
                        'confidence' => (float)($sig['confidence'] ?? 0),
                    ]);
                    if (!$allowed) {
                        // Record the flip so the member is not alerted about it
                        // later when the filter no longer applies.
                        $last[$pair] = $sig['key'];
                        $changed = true;
                        continue;
                    }
                    $inds = json_decode((string)($sig['indicators'] ?? ''), true);
                    $L = is_array($inds) && !empty($inds['levels']) ? $inds['levels'] : null;

                    // Fan out to the member's own webhook as well, so the same
                    // flip can drive their bot or automation. Per signal, not
                    // batched - a bot wants one event per event.
                    try {
                        Webhooks::deliver((int)$row['member_id'], [
                            'symbol' => $ps, 'tf' => $ptf, 'signal' => $sig['signal'],
                            // The reference the same call carries everywhere
                            // else, so a member's bot can key on it and match
                            // its own records against a support conversation.
                            'ref' => (string)($sig['ref'] ?? ''),
                            'score' => (float)$sig['score'], 'price' => (float)$sig['price'],
                            'grade' => is_array($indsF) ? ($indsF['grade'] ?? null) : null,
                            'confidence' => (float)($sig['confidence'] ?? 0),
                            'levels' => $L,
                        ]);
                    } catch (\Throwable $e) {
                        // a member's broken endpoint must never stall the sweep
                    }

                    // Held, not sent. See the send below for why.
                    $hits[] = ['sym' => $ps, 'tf' => $ptf, 'sig' => $sig, 'L' => $L,
                               'inds' => $inds, 'pair' => $pair];
                    // Deliberately NOT marked as seen here - see below.
                    continue;
                }
                if ($prev !== $sig['key']) {
                    $last[$pair] = $sig['key'];
                    $changed = true;
                }
            }

            // One run, one email.
            //
            // A member who taps "watch ALL my coins" is watching sixty pairs
            // across three timeframes, and a busy run flips a dozen of them at
            // once. Sending one message per flip put twelve emails in their
            // inbox inside a minute, which reads as spam whatever is in it,
            // gets the domain filtered, and buries the one setup they actually
            // wanted among eleven they did not. Batched, the same run is one
            // message listing every coin that moved, each with its own plan.
            //
            // A single flip still sends the full single-signal email - a
            // "1 new signal" digest would be a worse version of it.
            // Hold them, when the operator has asked for a window.
            //
            // Batching within a run already collapses simultaneous flips, but
            // flips do not arrive politely: one coin turns at 12:00 and another
            // at 12:10, and a ten-minute cron makes that two emails. A window
            // holds new signals until the oldest is old enough, then sends
            // everything waiting as one message - which is the only way "all my
            // coins in one email" can be true when the coins do not all move in
            // the same minute. 0 keeps the per-run behaviour.
            $window = max(0, (int)Database::setting('alert_bundle_min', '0')) * 60;
            if ($window > 0) {
                foreach ($hits as $h) {
                    AlertQueue::push((int)$row['member_id'], 'email', $h['sym'], $h['tf'], $h);
                    // Queued IS handled, for these purposes: the queue is a
                    // table, it survives the run, and it is retried until it
                    // delivers. Marking here is also what stops the next run
                    // detecting the same flip and queueing a second copy of it
                    // - the queue does not de-duplicate, so without this a
                    // ten-minute window would put the same signal in the same
                    // email five times.
                    if (isset($h['pair'], $h['sig']['key'])) {
                        $last[$h['pair']] = $h['sig']['key'];
                        $changed = true;
                    }
                }
                $hits = AlertQueue::due((int)$row['member_id'], 'email', $window);
                // Nothing due yet: the new ones are safely queued, and the
                // marks above stop this run being repeated on the next.
            }

            if ($hits) {
                $siteUrl = Database::setting('site_url');
                $link = $siteUrl !== '' ? $siteUrl . '/' : '';
                $optOut = MemberAuth::optOutUrl((int)$row['member_id']);
                // Which hits actually went out - tracked per-hit rather than
                // as one aggregate flag, because the non-batched branch below
                // sends ONE EMAIL PER HIT in its own loop: with only a single
                // truthy/falsy $ok summarising all of them, 2 successes and 1
                // failure out of 3 individually-sent emails made $ok a truthy
                // count, which the shared block further down read as "it
                // worked" - marking the FAILED hit as delivered (so it's
                // never retried), counting it as sent, and never logging that
                // anything went wrong. A member could be silently missing an
                // alert with zero record anywhere that it happened.
                $deliveredHits = [];
                $failedHits = [];
                if (count($hits) === 1 || Database::setting('alert_email_batch', '1') !== '1') {
                    foreach ($hits as $h) {
                        $dir = $h['sig']['signal'] === 'BUY' ? 'Bullish 📈' : 'Bearish 📉';
                        // ONE SIGNAL, ONE BUTTON, AND IT OPENS THAT SIGNAL.
                        //
                        // A single-signal alert sent the reader to the site
                        // root, where they had to find the coin and the frame
                        // again from a mail that had just named both. Now that
                        // a call carries a reference, the button can resolve
                        // straight to its chart - see the ?ref= handler in
                        // charts.php. Falls back to the dashboard when there is
                        // no reference, which is what every batch mail still
                        // does.
                        $go = ($link !== '' && ($h['sig']['ref'] ?? '') !== '')
                            ? rtrim($link, '/') . '/charts.php?ref=' . rawurlencode((string)$h['sig']['ref'])
                            : $link;
                        $sentOne = Mailer::send(
                            (string)$row['email'],
                            $dir . ' ' . $h['sym'] . ' (' . $h['tf'] . ') — ' . $siteName . ' signal',
                            self::signalText($h, $go) . self::footerText($optOut),
                            Mailer::template($dir . ' ' . $h['sym'] . ' (' . $h['tf'] . ')',
                                self::signalHtml($h, true) . self::footerHtml($optOut),
                                ($go !== $link ? 'Open this signal' : 'Open your dashboard'), $go),
                            ['kind' => EmailLog::ALERT, 'member' => (int)$row['member_id'],
                             'unsubscribe' => $optOut,
                             'context' => $h['sym'] . ' ' . $h['tf'] . ' ' . $h['sig']['signal']]
                        );
                        if ($sentOne) { $deliveredHits[] = $h; } else { $failedHits[] = $h; }
                    }
                } else {
                    // Full plan for the first N, a named one-liner for the rest.
                    //
                    // A full block is about 1.4KB of HTML, so a member watching
                    // a few hundred pairs through a busy run produces a message
                    // that crosses Gmail's ~102KB threshold at around sixty
                    // signals. Past that Gmail shows "[Message clipped]" and
                    // hides the remainder behind a link - so the member reads
                    // the first thirty-odd calls and never learns the rest
                    // existed. Nothing reports this: the send succeeded, the
                    // log says delivered, and the alerts are simply missing.
                    //
                    // Every signal is still NAMED in the email, which is what
                    // "all my coins in one message" has to mean. What the tail
                    // loses is the entry/stop/target table, and that is on the
                    // dashboard - a compact row is 60 bytes against 1,400, so
                    // several hundred of them still fit comfortably.
                    $maxDetail = max(1, min(200, (int)Database::setting('alert_email_max_detail', '25')));
                    $detail = array_slice($hits, 0, $maxDetail);
                    $rest = array_slice($hits, $maxDetail);

                    $body = '<p style="margin:0 0 16px">' . count($hits) . ' of your watched pairs '
                        . 'just flipped to a new call.'
                        . ($rest ? ' The first ' . count($detail) . ' are below in full; the '
                            . 'remaining ' . count($rest) . ' follow with their entry, stop and '
                            . 'first target.'
                            : ' Each one is listed below with the plan it was published with.')
                        . '</p>';
                    $text = count($hits) . " of your watched pairs just flipped to a new call.\n\n";
                    foreach ($detail as $h) {
                        $body .= self::signalHtml($h, false);
                        $text .= self::signalText($h, '') . "\n" . str_repeat('-', 40) . "\n\n";
                    }
                    if ($rest) {
                        // The tail carries its numbers too.
                        //
                        // It used to be the coin, the frame and the direction,
                        // and nothing else: past the twenty-fifth signal a
                        // member was told something had flipped and had to go
                        // and look up what the trade was. That is half an
                        // alert. The entry, the stop and the first target are
                        // what makes it actionable, and they are three numbers
                        // - about 90 bytes in a row rather than the 1,400 a
                        // full block costs, so several hundred of them still
                        // sit comfortably inside the ~102KB where Gmail starts
                        // clipping. What the tail still loses is the reasoning
                        // and the context lines, which is the right thing to
                        // lose: those are on the dashboard, the plan is not
                        // something you should have to go and find.
                        $body .= '<p style="margin:20px 0 8px;font-weight:700;color:#101828">'
                              . 'Also flipped</p>'
                              . '<table role="presentation" cellpadding="0" cellspacing="0" '
                              . 'style="width:100%;font-size:13px;color:#344054">';
                        $text .= "Also flipped:\n";
                        foreach ($rest as $h) {
                            $up = $h['sig']['signal'] === 'BUY';
                            $plan = self::planLine($h);
                            $body .= '<tr><td style="padding:5px 0;border-top:1px solid #eef1f6">'
                                  . '<strong>' . htmlspecialchars($h['sym'], ENT_QUOTES) . '</strong> '
                                  . '<span style="color:#667085">' . htmlspecialchars($h['tf'], ENT_QUOTES)
                                  . '</span>'
                                  . ($plan !== '' ? '<div style="color:#667085;font-size:12px">'
                                      . htmlspecialchars($plan, ENT_QUOTES) . '</div>' : '')
                                  . '</td><td align="right" valign="top" style="padding:5px 0;font-weight:700;'
                                  . 'border-top:1px solid #eef1f6;color:' . ($up ? '#0D8762' : '#D73E35')
                                  . '">' . ($up ? 'BUY' : 'SELL') . '</td></tr>';
                            $text .= '  ' . $h['sym'] . ' ' . $h['tf'] . ' - ' . $h['sig']['signal']
                                  . ($plan !== '' ? ' - ' . $plan : '') . "\n";
                        }
                        $body .= '</table>';
                        $text .= "\n";
                    }
                    $text .= 'Open your dashboard for the full reasoning: '
                        . ($link !== '' ? $link : '(your site)') . "\n\n"
                        . "--\n" . Database::setting('site_notice')
                        . self::footerText($optOut);
                    // The pairs go in the log line, so "I watched all my coins
                    // and got nothing" can be answered by reading one row
                    // instead of asking the member what they saw.
                    $names = array_map(static fn($h) => $h['sym'] . ' ' . $h['tf'], $hits);
                    $ok = Mailer::send(
                        (string)$row['email'],
                        count($hits) . ' new signals — ' . $siteName,
                        $text,
                        Mailer::template(count($hits) . ' new signals',
                            $body . self::footerHtml($optOut), 'Open your dashboard', $link),
                        ['kind' => EmailLog::ALERT, 'member' => (int)$row['member_id'],
                         'unsubscribe' => $optOut,
                         'context' => count($hits) . ' pairs: ' . implode(', ', array_slice($names, 0, 20))
                                    . (count($names) > 20 ? ' +' . (count($names) - 20) . ' more' : '')]
                    );
                    // One combined email, one outcome - every hit it carried
                    // sinks or swims together, unlike the per-hit branch above.
                    if ($ok) { $deliveredHits = $hits; } else { $failedHits = $hits; }
                }
                if ($deliveredHits) {
                    if ($window > 0) {
                        // Only clear what was actually delivered. A send that
                        // failed leaves the queue intact so the next run tries
                        // again rather than losing the alerts silently.
                        AlertQueue::clear((int)$row['member_id'], 'email', $window);
                    }
                    // DELIVERED, so now it is seen.
                    //
                    // This used to be marked at the top of the loop, next to
                    // the flip that produced it, whether or not the email went
                    // anywhere. One refused SMTP login, one timeout, one
                    // over-quota bounce, and that flip was recorded as
                    // already-sent: the next run compares against the new key,
                    // finds no change, and the member never hears about that
                    // signal. Not late - never. Nothing retried it and nothing
                    // reported it, because from the sweep's point of view the
                    // work was done.
                    //
                    // A bundling window protected against this (the queue is
                    // durable and is retried) and the default of zero did not,
                    // so the configuration almost everyone runs was the one
                    // that lost alerts. With a window the marks are made at
                    // the queue above; here they are made on delivery.
                    if ($window === 0) {
                        foreach ($deliveredHits as $h) {
                            if (isset($h['pair'], $h['sig']['key'])) {
                                $last[$h['pair']] = $h['sig']['key'];
                                $changed = true;
                            }
                        }
                    }
                    // The cap counts signals, not envelopes - batching must not
                    // become a way around a member's own daily limit. Only the
                    // ones that actually sent count against it.
                    foreach ($deliveredHits as $_) {
                        MemberPrefs::countAlert((int)$row['member_id']);
                    }
                    $sent += count($deliveredHits);
                }
                if ($failedHits) {
                    // Left unmarked on purpose, so the next run sees the same
                    // flip and tries again. Written down as well: a member
                    // asking "why did I not get that one" should be answerable
                    // from the panel.
                    ErrorLog::record(ErrorLog::ALERTS,
                        'An alert email could not be sent, so it will be retried on the next run',
                        count($failedHits) . ' signal(s) for ' . $row['email']
                        . ' - see the email log for the reason.');
                }
            }

            if ($changed) {
                $pdo->prepare('UPDATE member_alerts SET last_sent = ? WHERE member_id = ?')
                    ->execute([json_encode($last), $row['member_id']]);
            }
        }
        return $sent;
    }

    /** The opt-out line every bulk message carries, in plain text. */
    private static function footerText(string $optOut): string
    {
        return $optOut === '' ? ''
            : "\n\nStop these emails: " . $optOut . "\n";
    }

    /** And in HTML. Small, grey, and unmistakably a way out. */
    private static function footerHtml(string $optOut): string
    {
        return $optOut === '' ? ''
            : '<p style="margin:22px 0 0;padding-top:14px;border-top:1px solid #eef1f6;'
            . 'font-size:12px;color:#98a2b3">You are getting this because you asked to be alerted '
            . 'about pairs you watch. <a href="' . htmlspecialchars($optOut, ENT_QUOTES)
            . '" style="color:#98a2b3">Stop these emails</a>.</p>';
    }

    /**
     * The published targets, however many of them there are.
     *
     * Not every setup produces three: a level set can stop at TP1 when the
     * structure above it does not support more. Reading tp2 and tp3 as though
     * they always exist put a PHP warning in the middle of every such alert.
     */
    private static function targets(array $L): string
    {
        $out = [];
        foreach (['tp1', 'tp2', 'tp3'] as $k) {
            if (isset($L[$k]) && $L[$k] !== '' && $L[$k] !== null) {
                $out[] = View::price($L[$k]);
            }
        }
        return implode(' / ', $out);
    }

    /**
     * The plan on one line, for the tail of a long batch.
     *
     * Entry, stop and the first target - the three numbers you cannot act
     * without. Deliberately not the whole level set: this exists so that a
     * member watching two hundred pairs gets a usable alert for the two
     * hundredth as well as the first, and that only works if the row stays
     * small enough that hundreds of them fit inside one message.
     *
     * Empty when the signal published no levels, rather than a row of dashes.
     */
    private static function planLine(array $h): string
    {
        $L = $h['L'] ?? null;
        if (!is_array($L)) {
            return '';
        }
        $bits = [];
        if (isset($L['entry']) && $L['entry'] !== '' && $L['entry'] !== null) {
            $bits[] = 'entry ' . View::price($L['entry']);
        }
        if (isset($L['stop_loss']) && $L['stop_loss'] !== '' && $L['stop_loss'] !== null) {
            $bits[] = 'stop ' . View::price($L['stop_loss']);
        }
        if (isset($L['tp1']) && $L['tp1'] !== '' && $L['tp1'] !== null) {
            $bits[] = 'TP1 ' . View::price($L['tp1']);
        }
        return implode(' · ', $bits);
    }

    /** One signal as plain text, used alone or as one entry in a batch. */
    private static function signalText(array $h, string $link): string
    {
        $sig = $h['sig'];
        $score = (float)$sig['score'];
        $t = $h['sym'] . ' (' . $h['tf'] . ') flipped to ' . $sig['signal'] . ".\n"
           // The reference goes in first, because it is the line a member
           // quotes back when they write in about this trade. Everything else
           // in the mail describes the call; this one names it.
           . (($sig['ref'] ?? '') !== '' ? 'Signal ID: ' . $sig['ref'] . "\n" : '')
           . 'Score: ' . ($score > 0 ? '+' : '') . $score . "\n"
           . 'Price at signal: ' . View::price($sig['price'] ?? null) . "\n";
        foreach (View::signalContext($h['inds']) as $label => $value) {
            $t .= $label . ': ' . $value . "\n";
        }
        if ($h['L']) {
            $L = $h['L'];
            $tps = self::targets($L);
            $t .= "Trade plan:\n"
                . '  Entry: ' . View::price($L['entry'] ?? null) . "\n"
                . '  Stop loss: ' . View::price($L['stop_loss'] ?? null) . "\n"
                . ($tps !== '' ? '  Targets: ' . $tps . "\n" : '')
                . (!empty($L['rr']) ? '  Risk:Reward ~ 1:' . $L['rr'] . "\n" : '');
        }
        if ($link !== '') {
            // Wording that stays true whichever link this is: the solo alert
            // now sends a reader straight to the signal's own chart, and the
            // batch still sends them to the dashboard.
            $t .= "\nSee the full reasoning: " . $link . "\n\n"

                . "--\n" . Database::setting('site_notice');
        }
        return $t;
    }

    /**
     * One signal as HTML. $solo draws the big directional badge that suits a
     * one-signal email; in a batch each entry gets a compact heading instead,
     * because a dozen 18px badges is a wall, not a list.
     */
    private static function signalHtml(array $h, bool $solo): string
    {
        $sig = $h['sig'];
        $isBuy = $sig['signal'] === 'BUY';
        $score = (float)$sig['score'];
        $col = $isBuy ? '#0D8762' : '#D73E35';
        $bg  = $isBuy ? '#e8f9f0' : '#feecea';
        $sym = htmlspecialchars($h['sym'], ENT_QUOTES);
        $tf  = htmlspecialchars($h['tf'], ENT_QUOTES);

        if ($solo) {
            $out = '<p style="margin:0 0 14px">The automated chart analysis for <strong>' . $sym
                 . '</strong> on the <strong>' . $tf . '</strong> timeframe just flipped:</p>'
                 . '<p style="margin:0 0 18px"><span style="display:inline-block;background-color:' . $bg
                 . ';color:' . $col . ';font-weight:800;font-size:18px;letter-spacing:1px;'
                 . 'padding:10px 26px;border-radius:9px">'
                 . ($isBuy ? 'BUY' : 'SELL') . '</span></p>';
        } else {
            $out = '<p style="margin:22px 0 8px;font-size:15px;font-weight:800;color:#101828">'
                 . $sym . ' <span style="font-weight:600;color:#667085">' . $tf . '</span> '
                 . '<span style="background-color:' . $bg . ';color:' . $col . ';font-size:12px;'
                 . 'font-weight:800;padding:3px 10px;border-radius:999px;margin-left:6px">'
                 . ($isBuy ? 'BUY' : 'SELL') . '</span></p>';
        }
        // Inline styles and a web-safe monospace stack, because an email client
        // has no stylesheet and half of them will not load one. Ink and ground
        // are literal here for the same reason the rest of this template is.
        $refRow = ($sig['ref'] ?? '') !== ''
            ? '<tr><td style="padding:5px 0;color:#667085">Signal ID</td>'
              . '<td align="right" style="font-family:Consolas,Menlo,monospace;font-weight:700;'
              . 'letter-spacing:0.06em;color:#101828">'
              . htmlspecialchars((string)$sig['ref'], ENT_QUOTES) . '</td></tr>'
            : '';
        $out .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:13.5px;color:#344054">'
              . $refRow
              . '<tr><td style="padding:5px 0;color:#667085' . ($refRow !== '' ? ';border-top:1px solid #eef1f6' : '')
              . '">Score</td><td align="right" style="font-weight:700'
              . ($refRow !== '' ? ';border-top:1px solid #eef1f6' : '') . '">'
              . ($score > 0 ? '+' : '') . $score . '</td></tr>'
              . '<tr><td style="padding:5px 0;color:#667085;border-top:1px solid #eef1f6">Price at signal</td>'
              . '<td align="right" style="font-weight:700;border-top:1px solid #eef1f6">'
              . htmlspecialchars(View::price($sig['price'] ?? null), ENT_QUOTES) . '</td></tr>';
        if ($h['L']) {
            $L = $h['L'];
            $tps = self::targets($L);
            $out .= '<tr><td style="padding:5px 0;color:#667085;border-top:1px solid #eef1f6">Entry</td>'
                  . '<td align="right" style="font-weight:700;border-top:1px solid #eef1f6">'
                  . htmlspecialchars(View::price($L['entry'] ?? null), ENT_QUOTES) . '</td></tr>'
                  . '<tr><td style="padding:5px 0;color:#667085;border-top:1px solid #eef1f6">Stop loss</td>'
                  . '<td align="right" style="font-weight:700;color:#D73E35;border-top:1px solid #eef1f6">'
                  . htmlspecialchars((string)($L['stop_loss'] ?? '-'), ENT_QUOTES) . '</td></tr>'
                  . ($tps !== '' ? '<tr><td style="padding:5px 0;color:#667085;border-top:1px solid #eef1f6">Targets</td>'
                      . '<td align="right" style="font-weight:700;color:#0D8762;border-top:1px solid #eef1f6">'
                      . htmlspecialchars($tps, ENT_QUOTES) . '</td></tr>' : '')
                  . (!empty($L['rr']) ? '<tr><td style="padding:5px 0;color:#667085;border-top:1px solid #eef1f6">Risk : Reward</td>'
                      . '<td align="right" style="font-weight:700;border-top:1px solid #eef1f6">1 : '
                      . htmlspecialchars((string)$L['rr'], ENT_QUOTES) . '</td></tr>' : '');
        }
        return $out . '</table>';
    }
}
