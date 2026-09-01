<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Telegram bot alerts - no libraries, plain Bot API over curl.
 *
 * Setup: the admin creates a bot with @BotFather and pastes its token in
 * Settings. Members tap "Connect Telegram" on their account page, which opens
 * t.me/<bot>?start=<one-time code>; the code arrives as a /start message,
 * cron picks it up via getUpdates polling (no webhook configuration needed)
 * and links the chat to the member. Signal flips are then delivered as
 * Telegram messages using the member's existing watched pairs.
 */
class Telegram
{
    public static function enabled(): bool
    {
        return Database::setting('tg_enabled') === '1' && Database::setting('tg_bot_token') !== '';
    }

    /** Raw Bot API call; returns decoded result or null (fail-silent). */
    public static function api(string $method, array $params = []): ?array
    {
        $token = Database::setting('tg_bot_token');
        if ($token === '') {
            return null;
        }
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        return (is_array($data) && ($data['ok'] ?? false)) ? ($data['result'] ?? []) : null;
    }

    /**
     * The bot's @username, as stored. NO NETWORK.
     *
     * This used to call getMe when the setting was empty, and it is read from
     * two pages that render on ordinary traffic: the admin settings page and
     * every member's account page. A token that is saved but wrong - or right,
     * on a host that cannot reach api.telegram.org - resolves to nothing, and
     * nothing was cached, so EVERY load of those two pages made a blocking
     * HTTPS call that was always going to fail. Measured on the settings page:
     * 1.6 seconds, every time. On a host that blackholes the connection rather
     * than refusing it, that is the full 6-second connect timeout on a member's
     * account page, on every visit.
     *
     * The copy beside the call site already said the right thing - "username
     * resolves on first cron run" - so the page was describing behaviour the
     * code was not implementing. Now it is: this reads, resolve() writes, and
     * cron calls resolve().
     */
    public static function botUsername(): string
    {
        return Database::setting('tg_bot_username');
    }

    /**
     * Ask Telegram what the bot is called, and remember the answer.
     *
     * Called from the cron path, never from a page render. The failure is
     * cached too: a wrong token would otherwise make every cron tick pay the
     * timeout again, and an hour is long enough to stop that being a cost
     * while being short enough that fixing the token takes effect on its own.
     */
    public static function resolveUsername(): string
    {
        $u = Database::setting('tg_bot_username');
        if ($u !== '') {
            return $u;
        }
        if (Cache::get('tg_getme_failed') !== null) {
            return '';
        }
        $me = self::api('getMe');
        $u = (string)($me['username'] ?? '');
        if ($u !== '') {
            Database::setSetting('tg_bot_username', $u);
            return $u;
        }
        Cache::set('tg_getme_failed', '1', 3600);
        return '';
    }

    /** One-time deep-link code for a member's connect button. */
    public static function linkCode(int $memberId): string
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT tg_link_code FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $code = (string)($stmt->fetchColumn() ?: '');
        if ($code === '') {
            $code = bin2hex(random_bytes(10));
            $pdo->prepare('UPDATE members SET tg_link_code = ? WHERE id = ?')->execute([$code, $memberId]);
        }
        return $code;
    }

    /**
     * Handle one Telegram update (pure logic - unit-testable). Recognises
     * "/start <code>" and links the chat to the matching member.
     * Returns a human-readable action for logging.
     */
    public static function processUpdate(array $u): string
    {
        $msg = $u['message'] ?? null;
        $chatId = (int)($msg['chat']['id'] ?? 0);
        $text = trim((string)($msg['text'] ?? ''));
        if ($chatId === 0 || $text === '') {
            return 'ignored';
        }
        if (preg_match('/^\/start\s+([a-f0-9]{20})$/', $text, $m)) {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT id, email FROM members WHERE tg_link_code = ?');
            $stmt->execute([$m[1]]);
            $member = $stmt->fetch();
            $stmt->closeCursor();
            if ($member) {
                $pdo->prepare("UPDATE members SET tg_chat_id = ?, tg_link_code = '' WHERE id = ?")
                    ->execute([$chatId, $member['id']]);
                self::api('sendMessage', ['chat_id' => $chatId,
                    'text' => "✅ Linked to " . $member['email'] . "!\nYou will now receive signal alerts for your watched coins here."]);
                return 'linked member ' . $member['id'];
            }
            self::api('sendMessage', ['chat_id' => $chatId,
                'text' => 'This link code is not valid any more - open your account page and tap Connect Telegram again.']);
            return 'unknown code';
        }
        if (str_starts_with($text, '/start')) {
            self::api('sendMessage', ['chat_id' => $chatId,
                'text' => 'To link this chat, open your account page on the website and tap "Connect Telegram".']);
            return 'start without code';
        }
        return 'ignored';
    }

    /** Poll pending updates (cron). No webhook configuration required. */
    public static function poll(): int
    {
        if (!self::enabled()) {
            return 0;
        }
        // The one place that resolves the bot's @username, because this is a
        // background path and the pages that display it are not. It costs
        // nothing once resolved and is negatively cached when it fails.
        self::resolveUsername();
        $offset = (int)Database::setting('tg_updates_offset', '0');
        $updates = self::api('getUpdates', ['offset' => $offset + 1, 'timeout' => 0, 'limit' => 50]);
        if (!is_array($updates)) {
            return 0;
        }
        $n = 0;
        foreach ($updates as $u) {
            $offset = max($offset, (int)($u['update_id'] ?? 0));
            self::processUpdate((array)$u);
            $n++;
        }
        if ($n > 0) {
            Database::setSetting('tg_updates_offset', (string)$offset);
        }
        return $n;
    }

    /** Number of members with a linked chat (admin display). */
    public static function linkedCount(): int
    {
        return (int)Database::pdo()->query('SELECT COUNT(*) FROM members WHERE tg_chat_id != 0')->fetchColumn();
    }

    /**
     * Send a Telegram message for each watched pair that flipped to BUY/SELL.
     * Mirrors EmailAlerts but with its own last-sent state (tg_last_sent) so
     * the two dispatchers never race each other's change detection.
     */
    public static function dispatchFlips(): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $pdo = Database::pdo();
        $tierStmt = $pdo->prepare('SELECT tier FROM symbols WHERE symbol = ? AND enabled = 1'
                                  . Visibility::sql('alerts', 'symbols'));
        // Never looser than what the site actually publishes - see
        // Publish::alertFloor(). A member pinged about a signal that is not
        // on the scanner, the coin page or the API is looking for a dead link.
        $minGrade = Publish::alertFloor();
        $sent = 0;

        $rows = $pdo->query(
            'SELECT a.member_id, a.pairs, a.tg_last_sent, m.tg_chat_id, m.tier AS mtier, m.paid_until
             FROM member_alerts a JOIN members m ON m.id = a.member_id
             WHERE a.enabled = 1 AND m.tg_chat_id != 0'
        )->fetchAll();

        foreach ($rows as $row) {
            $pairs = array_filter(explode(',', $row['pairs']));
            if (!$pairs) {
                continue;
            }
            $viewer = ($row['mtier'] === 'paid' && (int)$row['paid_until'] > 0 && (int)$row['paid_until'] < time())
                ? 'free' : $row['mtier'];
            $last = json_decode($row['tg_last_sent'] ?: '{}', true) ?: [];
            $changed = false;
            $blocks = [];
            // pair => key for flips that have been written into a message but
            // not yet handed to Telegram. Applied when the send succeeds, so a
            // rejected API call leaves the flip looking new and the next run
            // sends it rather than dropping it.
            $pending = [];

            foreach ($pairs as $pair) {
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $tierStmt->execute([$ps]);
                $symTier = $tierStmt->fetchColumn();
                if ($symTier === false || !MemberAuth::canAccess((string)$symTier, $viewer)) {
                    continue;
                }
                $sig = Scanner::current($ps, $ptf);
                if (!$sig) {
                    continue;
                }
                $prev = $last[$pair] ?? null;
                if (Scanner::shouldAlert($prev, $sig, $ptf)
                    && in_array($sig['signal'], ['BUY', 'SELL'], true)
                    && WebPush::gradePasses($minGrade, (string)($sig['indicators'] ?? ''))) {
                    $inds = json_decode((string)($sig['indicators'] ?? ''), true);
                    // A member's own filters - grade, signal type, minimum
                    // confidence, quiet hours, the daily cap - applied here for
                    // the first time. Push and email already ran every flip
                    // through MemberPrefs::alertAllowed(); this dispatcher never
                    // did, so a member who set quiet hours to stop their phone
                    // buzzing at 3am, or capped themselves at five alerts a day,
                    // or excluded 1:1 setups, got all of that honoured on their
                    // other channels and ignored on this one.
                    [$allowed] = MemberPrefs::alertAllowed((int)$row['member_id'], [
                        'signal' => $sig['signal'],
                        'grade' => is_array($inds) ? ($inds['grade'] ?? null) : null,
                        'rr_tier' => is_array($inds) && is_array($inds['levels'] ?? null)
                            ? (int)($inds['levels']['rr_tier'] ?? 0) : 0,
                        'confidence' => (float)($sig['confidence'] ?? 0),
                    ]);
                    if (!$allowed) {
                        $last[$pair] = $sig['key'];
                        $changed = true;
                        continue;
                    }
                    $isBuy = $sig['signal'] === 'BUY';
                    $L = is_array($inds) && !empty($inds['levels']) ? $inds['levels'] : null;
                    $text = ($isBuy ? "📈 *BUY* " : "📉 *SELL* ") . $ps . ' (' . $ptf . ")\n"
                        . 'Price: ' . View::price($sig['price'] ?? null) . '  ·  Score: ' . ($sig['score'] > 0 ? '+' : '') . $sig['score'] . "\n";
                    // The reference, on its own line and in a code span so
                    // Telegram lets it be tapped and copied whole rather than
                    // selected character by character on a phone.
                    if (($sig['ref'] ?? '') !== '') {
                        $text .= 'ID: `' . $sig['ref'] . "`\n";
                    }
                    if ($L) {
                        // Not every level set reaches three targets - reading
                        // tp2/tp3 as though it always does warned on every one
                        // that stopped at TP1.
                        $tps = [];
                        foreach (['tp1', 'tp2', 'tp3'] as $k) {
                            if (isset($L[$k]) && $L[$k] !== '' && $L[$k] !== null) {
                                $tps[] = (string)$L[$k];
                            }
                        }
                        $text .= "\nEntry: " . View::price($L['entry'] ?? null)
                              . "\nStop loss: " . ($L['stop_loss'] ?? '-')
                              . ($tps ? "\nTargets: " . implode(' / ', $tps) : '') . "\n";
                    }
                    // Whether the frames above agree, and how long this is
                    // good for. A message read six hours after a 5m setup
                    // fired describes a trade that no longer exists.
                    foreach (View::signalContext($inds) as $label => $value) {
                        $text .= "\n" . $label . ': ' . $value;
                    }
                    if ($L) {
                        $text .= "\n";
                    }
                    // Held. Marked as seen only once it has actually gone to
                    // Telegram - see the send below.
                    $blocks[] = $text;
                    $pending[$pair] = $sig['key'];
                    continue;
                }
                if ($prev !== $sig['key']) {
                    $last[$pair] = $sig['key'];
                    $changed = true;
                }
            }

            // One run, one message - see EmailAlerts for the reasoning. It
            // matters more here: Telegram rate-limits a burst to one chat and
            // simply drops what goes over, so a member watching all their
            // coins was losing part of the burst without anyone being told.
            // Same window as email, same queue, one channel apart. Telegram
            // needs it at least as much: it rate-limits a burst to one chat and
            // drops the overflow.
            $window = max(0, (int)Database::setting('alert_bundle_min', '0')) * 60;
            if ($window > 0 && $blocks) {
                foreach ($blocks as $bi => $bTxt) {
                    AlertQueue::push((int)$row['member_id'], 'tg', 'block', (string)$bi, ['t' => $bTxt]);
                }
                // In the queue is handled: it is a table, it survives the run,
                // and it is retried until it delivers. It also has to be marked
                // here, because the queue does not de-duplicate - without it
                // the next run would find the same flip and queue it again.
                foreach ($pending as $pPair => $pKey) {
                    $last[$pPair] = $pKey;
                    $changed = true;
                }
                $pending = [];
                $blocks = array_values(array_filter(array_map(
                    static fn($h) => (string)($h['t'] ?? ''),
                    AlertQueue::due((int)$row['member_id'], 'tg', $window)
                )));
            }

            if ($blocks) {
                $site = Database::setting('site_url');
                $batch = Database::setting('alert_tg_batch', '1') === '1' && count($blocks) > 1;
                $tail = ($site !== '' ? "\n" . $site . '/charts.php' : '')
                      . "\n_" . str_replace(['_', '*'], '', Database::setting('site_notice')) . "_";
                // Which pair(s) each entry in $msgs below actually represents -
                // captured now, before the send loop, because $pending gets
                // mutated inside it. Batched: one message, every pending pair.
                // Not batched: one message per pair, in the same order $blocks
                // was built in. Empty for the queued/bundled ($window > 0)
                // path, where $pending was already cleared above and $blocks
                // came from AlertQueue::due() instead - that path's own retry
                // lives in the queue table, not in $pending/$last.
                $pairKeys = array_keys($pending);
                $msgPairs = $batch ? [$pairKeys] : array_map(static fn($k) => [$k], $pairKeys);
                $msgs = $batch
                    ? [count($blocks) . " of your watched pairs just flipped:\n\n"
                       . implode("\n\n", $blocks) . $tail]
                    : array_map(static fn($b) => $b . $tail, $blocks);
                foreach ($msgs as $mi => $m) {
                    $thesePairs = $msgPairs[$mi] ?? [];
                    $ok = self::api('sendMessage', [
                        'chat_id' => (int)$row['tg_chat_id'],
                        'text' => $m,
                        'parse_mode' => 'Markdown',
                        'disable_web_page_preview' => 'true',
                    ]);
                    if ($ok !== null) {
                        $sent += $batch ? count($blocks) : 1;
                        if ($window > 0) {
                            AlertQueue::clear((int)$row['member_id'], 'tg', $window);
                        }
                        // Delivered, so now it is seen - only the pair(s)
                        // THIS message carried, never the whole map. Marking
                        // every still-pending pair here (what this used to
                        // do) meant one message's success in this same loop
                        // silently marked a LATER message's pairs as
                        // delivered too, before that later message had even
                        // been sent - so when it then failed, the failure
                        // branch below found $pending already empty and
                        // logged nothing. The member's flip for that pair
                        // was dropped with zero record anywhere it happened.
                        foreach ($thesePairs as $pPair) {
                            if (array_key_exists($pPair, $pending)) {
                                $last[$pPair] = $pending[$pPair];
                                $changed = true;
                                unset($pending[$pPair]);
                                // The cap counts signals, not envelopes - see
                                // EmailAlerts for the same reasoning. Without
                                // this, a member's daily alert cap only ever
                                // saw their push and email traffic and a batch
                                // of Telegram flips never touched it.
                                MemberPrefs::countAlert((int)$row['member_id']);
                            }
                        }
                    } elseif ($thesePairs) {
                        ErrorLog::record(ErrorLog::ALERTS,
                            'A Telegram alert could not be sent, so it will be retried on the next run',
                            count($thesePairs) . ' signal(s) for chat ' . (int)$row['tg_chat_id']);
                    }
                }
            }

            if ($changed) {
                $pdo->prepare('UPDATE member_alerts SET tg_last_sent = ? WHERE member_id = ?')
                    ->execute([json_encode($last), $row['member_id']]);
            }
        }
        return $sent;
    }
}
