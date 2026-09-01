<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Outbound webhooks: POST a signal flip to a URL the member controls.
 *
 * This is the cheapest possible integration surface - it makes the site
 * compatible with TradingView-style automation, trading bots, n8n, Zapier and
 * anything else that accepts an HTTP callback, without the site needing to
 * know any of them exist.
 *
 * Because the destination is member-supplied, delivery is treated as a
 * server-side request forgery risk throughout: HTTPS only, public addresses
 * only, redirects disabled, short timeouts, and the resolved IP re-checked at
 * delivery time (a hostname that validated at save time can be re-pointed at
 * 169.254.169.254 afterwards).
 */
class Webhooks
{
    /** Deliver one signal to a member's webhook. Returns true on 2xx. */
    public static function deliver(int $memberId, array $signal): bool
    {
        $prefs = MemberPrefs::get($memberId);
        $url = (string)$prefs['webhook_url'];
        if ($url === '' || !Master::on('alerts', 'webhook')) {
            return false;
        }
        $pinned = self::destinationAllowed($url);
        if ($pinned === []) {
            return false;
        }

        // THE REFERENCE IS THE IDEMPOTENCY KEY, AND IT WAS BEING DROPPED.
        //
        // EmailAlerts passes 'ref' in, this array did not carry it out, so the
        // one field a bot needs to recognise a repeat never left the building.
        // A webhook is delivered at least once by nature - a receiver that
        // times out after acting still gets no 2xx - and without a stable id
        // the only way to tell a retry from a fresh call is a guess about
        // symbol and time. That guess is a duplicate order.
        //
        // Keyed on the published signal, so the same call is the same string
        // in the webhook, the email, the chart and the admin search.
        $payload = json_encode([
            'event'      => 'signal.flip',
            'id'         => $signal['ref'] ?? '',
            'ref'        => $signal['ref'] ?? '',
            'symbol'     => $signal['symbol'] ?? '',
            'timeframe'  => $signal['tf'] ?? '',
            'signal'     => $signal['signal'] ?? '',
            'score'      => $signal['score'] ?? null,
            'grade'      => $signal['grade'] ?? null,
            'confidence' => $signal['confidence'] ?? null,
            'price'      => $signal['price'] ?? null,
            'levels'     => $signal['levels'] ?? null,
            'at'         => time(),
            'site'       => Database::setting('site_url'),
            'disclaimer' => Database::setting('site_notice'),
        ]) ?: '{}';

        // Signed like a payment webhook so the receiver can verify origin.
        $secret = self::secret($memberId);
        $sig = hash_hmac('sha256', $payload, $secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,   // a redirect could point anywhere
            CURLOPT_PROTOCOLS_STR => 'https',
            // Connect to the address we checked, not to whatever the name
            // resolves to a moment later. Without this the validation is
            // advisory: curl does its own lookup, so a record with a one
            // second TTL can answer this check with a public address and the
            // connection itself with 169.254.169.254. TLS still uses the
            // hostname for SNI and certificate matching, so pinning the
            // address costs nothing in trust.
            CURLOPT_RESOLVE => $pinned,
            CURLOPT_USERAGENT => 'SignalMasterAi-Webhook/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-SMA-Signature: sha256=' . $sig,
                'X-SMA-Event: signal.flip',
            ],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $ok = $code >= 200 && $code < 300;
        // Repeated failures are recorded so the member can see the endpoint is
        // dead rather than wondering why nothing arrives.
        $key = 'webhook_fail:' . $memberId;
        if ($ok) {
            Cache::forget($key);
        } else {
            Cache::increment($key, 86400);
        }
        return $ok;
    }

    /** Per-member signing secret, derived once and stored. */
    public static function secret(int $memberId): string
    {
        $key = 'webhook_secret_' . $memberId;
        $s = Database::setting($key);
        if ($s === '') {
            $s = bin2hex(random_bytes(24));
            Database::setSetting($key, $s);
        }
        return $s;
    }

    public static function recentFailures(int $memberId): int
    {
        return (int)(Cache::get('webhook_fail:' . $memberId, 0) ?: 0);
    }

    /**
     * Re-validate the destination at delivery time.
     *
     * Save-time validation is not enough on its own: a hostname that resolved
     * to a public address when the member saved it can be re-pointed at cloud
     * metadata or an internal service later.
     *
     * Returns curl CURLOPT_RESOLVE entries pinning the host to the addresses
     * that passed, or [] when the destination is not allowed. Returning the
     * addresses rather than a bool is what makes the check binding instead of
     * advisory - see the note at the call site.
     */
    private static function destinationAllowed(string $url): array
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return [];
        }
        $port = (int)($parts['port'] ?? 443);
        // parse_url keeps the brackets on a literal IPv6 host, and "[::1]"
        // is not an IP address as far as filter_var is concerned - so an
        // address written that way fell through to a DNS lookup that could
        // never succeed. It was refused, which was the right answer for ::1
        // and the wrong one for every public IPv6 endpoint a member might use.
        $host = trim($parts['host'], '[]');
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach (['A', 'AAAA'] as $type) {
                $recs = @dns_get_record($host, $type === 'A' ? DNS_A : DNS_AAAA) ?: [];
                foreach ($recs as $r) {
                    $ips[] = $r['ip'] ?? ($r['ipv6'] ?? '');
                }
            }
            if (!$ips) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $ips[] = $resolved;
                }
            }
        }
        if (!$ips) {
            return [];   // cannot prove it is safe, so do not send
        }
        foreach ($ips as $ip) {
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [];
            }
        }
        // One entry per address. A literal-IP host resolves to itself, which
        // curl accepts and which keeps the two paths identical.
        // curl wants the bracketed form back for an IPv6 host.
        $name = strtolower($host);
        if (str_contains($name, ':')) {
            $name = '[' . $name . ']';
        }
        $entries = [];
        foreach (array_unique($ips) as $ip) {
            $entries[] = $name . ':' . $port . ':' . $ip;
        }
        return $entries;
    }
}
