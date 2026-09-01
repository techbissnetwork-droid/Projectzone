<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Public broadcast channels.
 *
 * Telegram delivery already existed, but only as direct messages to members
 * who had linked their account. A public channel is the difference between a
 * notification system and a distribution channel: it is how this category of
 * product is actually discovered, and it costs one extra API call per flip.
 *
 * Supports a Telegram channel (reusing the configured bot) and any Discord or
 * Slack incoming webhook.
 */
class Broadcast
{
    public static function enabled(): bool
    {
        return Database::setting('broadcast_enabled', '0') === '1'
            && (self::telegramTarget() !== '' || self::discordUrl() !== '');
    }

    private static function telegramTarget(): string
    {
        return trim(Database::setting('broadcast_tg_chat'));
    }

    private static function discordUrl(): string
    {
        $u = trim(Database::setting('broadcast_discord_url'));
        return preg_match('#^https://#', $u) ? $u : '';
    }

    /**
     * Announce a flip. Only setups meeting the broadcast grade go out - a
     * public channel that posts every marginal signal trains its audience to
     * ignore it.
     */
    public static function announce(array $signal): bool
    {
        if (!self::enabled()) {
            return false;
        }
        // Never looser than what the site actually publishes - see
        // Publish::broadcastFloor(). This audience does not even have a
        // member account to check the scanner from, so a dead-link post is
        // worse here than anywhere else this floor is applied.
        $minGrade = Publish::broadcastFloor();
        $order = ['C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];
        if (($order[(string)($signal['grade'] ?? 'C')] ?? 0) < ($order[$minGrade] ?? 2)) {
            return false;
        }
        if (!in_array($signal['signal'] ?? '', ['BUY', 'SELL'], true)) {
            return false;
        }
        // One announcement per pair/timeframe/verdict, so a re-analysis that
        // reaches the same conclusion does not re-post.
        $key = 'bc:' . $signal['symbol'] . ':' . $signal['tf'] . ':' . $signal['signal'];
        if (Cache::get($key) !== null) {
            return false;
        }
        Cache::set($key, 1, 6 * 3600);

        $site = rtrim(Database::setting('site_url'), '/');
        $L = $signal['levels'] ?? null;
        $arrow = $signal['signal'] === 'BUY' ? "\u{1F4C8}" : "\u{1F4C9}";
        $lines = [
            $arrow . ' ' . $signal['signal'] . ' — ' . $signal['symbol'] . ' (' . $signal['tf'] . ')',
            'Grade ' . ($signal['grade'] ?? '?') . ' · score ' . ($signal['score'] ?? '?')
                . ' · confidence ' . round((float)($signal['confidence'] ?? 0)) . '%',
        ];
        if (is_array($L)) {
            $lines[] = 'Entry ' . View::price($L['entry'] ?? null) . ' · stop ' . View::price($L['stop_loss'] ?? null)
                     . ' · targets ' . View::price($L['tp1'] ?? null) . ' / ' . View::price($L['tp2'] ?? null)
                     . (isset($L['rr']) && $L['rr'] ? ' · R:R 1:' . $L['rr'] : '');
        }
        if ($site !== '') {
            $lines[] = $site . '/charts.php?symbol=' . rawurlencode((string)$signal['symbol'])
                     . '&tf=' . rawurlencode((string)$signal['tf']);
        }
        $lines[] = Database::setting('site_notice');
        $text = implode("\n", $lines);

        $sent = false;
        if (self::telegramTarget() !== '' && Telegram::enabled()) {
            $sent = Telegram::api('sendMessage', [
                'chat_id' => self::telegramTarget(),
                'text' => $text,
                'disable_web_page_preview' => true,
            ]) !== null || $sent;
        }
        if (self::discordUrl() !== '') {
            $sent = self::postWebhook(self::discordUrl(), $text) || $sent;
        }
        return $sent;
    }

    /** Discord and Slack incoming webhooks both accept a "content"/"text" JSON body. */
    private static function postWebhook(string $url, string $text): bool
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['content' => $text, 'text' => $text]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Sweep recent flips and announce the ones that qualify. Called from cron
     * after the analysis pass.
     */
    public static function dispatch(int $lookbackSeconds = 900): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $rows = Database::pdo()->prepare(
            "SELECT symbol, tf, `signal`, score, confidence, indicators FROM signals
             WHERE `signal` != 'NEUTRAL' AND created_at >= ? ORDER BY created_at DESC LIMIT 30"
        );
        $rows->execute([time() - $lookbackSeconds]);
        $n = 0;
        foreach ($rows->fetchAll() as $r) {
            $inds = json_decode((string)$r['indicators'], true);
            $ok = self::announce([
                'symbol' => $r['symbol'], 'tf' => $r['tf'], 'signal' => $r['signal'],
                'score' => (float)$r['score'], 'confidence' => (float)$r['confidence'],
                'grade' => is_array($inds) ? ($inds['grade'] ?? 'C') : 'C',
                'levels' => is_array($inds) ? ($inds['levels'] ?? null) : null,
            ]);
            if ($ok) {
                $n++;
            }
        }
        return $n;
    }
}
