<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Pure-PHP Web Push:
 *  - VAPID (RFC 8292): ES256 JWT signed with a P-256 key generated on first
 *    use and stored in settings.
 *  - Payload encryption (RFC 8291, aes128gcm): ECDH + HKDF + AES-128-GCM
 *    using OpenSSL only - no external libraries.
 *
 * Subscriptions live in push_subs together with the watched pairs and the
 * last signal sent per pair; cron.php calls dispatchFlips() so notifications
 * go out even when nobody has the site open.
 */
class WebPush
{
    /** Ensure VAPID keys exist; returns the public key (base64url, raw P-256 point). */
    public static function publicKey(): string
    {
        $pub = Database::setting('vapid_public');
        if ($pub !== '') {
            return $pub;
        }
        $res = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($res === false) {
            return '';
        }
        openssl_pkey_export($res, $privPem);
        $det = openssl_pkey_get_details($res);
        $raw = "\x04" . str_pad($det['ec']['x'], 32, "\0", STR_PAD_LEFT)
                      . str_pad($det['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $pub = self::b64url($raw);
        Database::setSetting('vapid_public', $pub);
        Database::setSetting('vapid_private_pem', $privPem);
        return $pub;
    }

    // ---------------------------------------------------------- subscriptions

    public static function saveSubscription(array $sub, array $pairs, int $memberId): bool
    {
        $endpoint = (string)($sub['endpoint'] ?? '');
        $p256dh   = (string)($sub['keys']['p256dh'] ?? '');
        $auth     = (string)($sub['keys']['auth'] ?? '');
        if (!preg_match('#^https://#', $endpoint) || $p256dh === '' || $auth === '') {
            return false;
        }
        $pdo = Database::pdo();
        $hash = hash('sha256', $endpoint);
        $pairsCsv = implode(',', array_slice($pairs, 0, 12));
        $stmt = $pdo->prepare('UPDATE push_subs SET p256dh = ?, auth = ?, member_id = ?, pairs = ?, endpoint = ? WHERE endpoint_hash = ?');
        $stmt->execute([$p256dh, $auth, $memberId, $pairsCsv, $endpoint, $hash]);
        if ($stmt->rowCount() === 0) {
            $pdo->prepare(
                'INSERT INTO push_subs (endpoint_hash, endpoint, p256dh, auth, member_id, pairs, last_sent, created_at)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$hash, $endpoint, $p256dh, $auth, $memberId, $pairsCsv, '{}', time()]);
        }
        return true;
    }

    public static function syncPairs(string $endpoint, array $pairs): void
    {
        Database::pdo()->prepare('UPDATE push_subs SET pairs = ? WHERE endpoint_hash = ?')
            ->execute([implode(',', array_slice($pairs, 0, 12)), hash('sha256', $endpoint)]);
    }

    public static function removeSubscription(string $endpoint): void
    {
        Database::pdo()->prepare('DELETE FROM push_subs WHERE endpoint_hash = ?')
            ->execute([hash('sha256', $endpoint)]);
    }

    /** All pairs watched by any push subscription (for cron analysis targets). */
    public static function watchedPairs(): array
    {
        $out = [];
        foreach (Database::pdo()->query('SELECT pairs FROM push_subs') as $row) {
            foreach (array_filter(explode(',', $row['pairs'])) as $p) {
                $out[$p] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Compare each subscription's watched pairs against the latest stored
     * signals and push a notification for every flip to BUY or SELL.
     * Returns [sent, removed] counters.
     */
    public static function dispatchFlips(): array
    {
        $pdo = Database::pdo();
        // Never looser than what the site actually publishes - see
        // Publish::alertFloor(). A member pinged about a signal that is not
        // on the scanner, the coin page or the API is looking for a dead link.
        $minGrade = Publish::alertFloor();
        $memberTier = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
        $sent = 0;
        $removed = 0;

        $subs = $pdo->query('SELECT * FROM push_subs')->fetchAll();
        // One lookup per coin, not one per subscription per coin - see the note
        // on Scanner::currentFor(). Subscriptions outnumber coins by orders of
        // magnitude and every one of them was re-asking the same questions.
        $wantSyms = [];
        foreach ($subs as $sub) {
            foreach (array_filter(explode(',', (string)$sub['pairs'])) as $p) {
                $wantSyms[strtok($p, ':')] = true;
            }
        }
        $symTiers = Scanner::alertTiers();
        $states = Scanner::currentFor(array_keys($wantSyms));

        foreach ($subs as $sub) {
            $pairs = array_filter(explode(',', $sub['pairs']));
            if (!$pairs) {
                continue;
            }
            // Viewer tier for this subscription (member plans can expire).
            $viewer = 'guest';
            if ((int)$sub['member_id'] > 0) {
                $memberTier->execute([(int)$sub['member_id']]);
                $m = $memberTier->fetch();
            $memberTier->closeCursor();
            if ($m) {
                    $viewer = ($m['tier'] === 'paid' && (int)$m['paid_until'] > 0 && (int)$m['paid_until'] < time())
                        ? 'free' : $m['tier'];
                }
            }
            $last = json_decode($sub['last_sent'] ?: '{}', true) ?: [];
            $changed = false;

            foreach ($pairs as $pair) {
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $symTier = $symTiers[$ps] ?? false;
                if ($symTier === false || !MemberAuth::canAccess((string)$symTier, $viewer)) {
                    continue;
                }
                $sig = $states[$ps . ':' . $ptf] ?? null;
                if (!$sig) {
                    continue;
                }
                $prev = $last[$pair] ?? null;
                if (Scanner::shouldAlert($prev, $sig, $ptf)
                    && in_array($sig['signal'], ['BUY', 'SELL'], true)
                    && self::gradePasses($minGrade, (string)$sig['indicators'])) {
                    // A logged-in subscriber's personal alert filters apply
                    // here too: grade, confidence, direction, quiet hours and
                    // the daily cap. Anonymous subscriptions keep the
                    // site-wide behaviour.
                    if ((int)$sub['member_id'] > 0) {
                        $indsF = json_decode((string)$sig['indicators'], true);
                        [$allowed] = MemberPrefs::alertAllowed((int)$sub['member_id'], [
                            'signal' => $sig['signal'],
                            'grade' => is_array($indsF) ? ($indsF['grade'] ?? null) : null,
                            // The signal's type, so the member's type filter
                            // has something to read. Without it the filter
                            // sees 0 on every call and passes everything.
                            'rr_tier' => is_array($indsF) && is_array($indsF['levels'] ?? null)
                                ? (int)($indsF['levels']['rr_tier'] ?? 0) : 0,
                            'confidence' => (float)($sig['confidence'] ?? 0),
                        ]);
                        if (!$allowed) {
                            $last[$pair] = $sig['key'];
                            $changed = true;
                            continue;
                        }
                    }
                    $dir = $sig['signal'] === 'BUY' ? "\u{1F4C8} Bullish" : "\u{1F4C9} Bearish";
                    $score = (float)$sig['score'];
                    // A push has room for one extra line, so it gets the one
                    // that decays: how long the setup is good for. A phone
                    // buzzing about a 5m setup is often read hours later.
                    $ctx = View::signalContext(json_decode((string)($sig['indicators'] ?? ''), true));
                    $valid = isset($ctx['Setup valid for']) ? "\nValid for " . $ctx['Setup valid for'] : '';
                    $ok = self::send($sub, json_encode([
                        'title' => "$dir — $ps ($ptf)",
                        'body'  => $sig['signal'] . ' signal · score ' . ($score > 0 ? '+' : '') . $score
                                 . ' · price ' . View::price($sig['price'] ?? null) . $valid
                                 . "\n" . Database::setting('site_notice'),
                        'tag'   => "sma-$pair",
                        'url'   => './',
                    ], JSON_UNESCAPED_UNICODE));
                    if ($ok === null) {
                        $removed++;
                        continue 2;   // subscription deleted (gone/expired)
                    }
                    if ($ok) {
                        $sent++;
                        if ((int)$sub['member_id'] > 0) {
                            MemberPrefs::countAlert((int)$sub['member_id']);
                        }
                    } else {
                        // Sent nowhere, so not seen. The mark below is what
                        // tells the next run there is nothing new here, and
                        // making it after a failed push meant one refused
                        // request lost that flip for that device permanently.
                        // Left unmarked, the next run finds the same flip and
                        // pushes it again.
                        continue;
                    }
                }
                if ($prev !== $sig['key']) {
                    $last[$pair] = $sig['key'];
                    $changed = true;
                }
            }
            if ($changed) {
                $pdo->prepare('UPDATE push_subs SET last_sent = ? WHERE id = ?')
                    ->execute([json_encode($last), $sub['id']]);
            }
        }
        return [$sent, $removed];
    }

    /**
     * Quality gate: with alert_min_grade set to B, A or A+, only setups of
     * that grade or better trigger a notification (missing grade passes).
     *
     * Shared by push, email and Telegram so the site-wide floor means the same
     * thing on every channel. A+ used to be missing here while a member could
     * choose it for themselves in their own filters, which left an operator
     * unable to set the site floor as strictly as one of their members could.
     */
    public static function gradePasses(string $minGrade, string $indicatorsJson): bool
    {
        $rank = ['C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];
        if (!isset($rank[$minGrade]) || $minGrade === 'C') {
            return true;                   // 'any', or anything unrecognised
        }
        $grade = (string)((json_decode($indicatorsJson, true)['grade'] ?? ''));
        if ($grade === '') {
            return true;                   // graded before grades existed
        }
        return ($rank[$grade] ?? 0) >= $rank[$minGrade];
    }

    // ---------------------------------------------------------- protocol

    /**
     * Encrypt + deliver one push message.
     * Returns true on success, false on transient failure, null if the
     * subscription is dead and has been removed.
     */
    public static function send(array $sub, string $payload): ?bool
    {
        try {
            $body = self::encrypt($payload, (string)$sub['p256dh'], (string)$sub['auth']);
            $jwt = self::vapidJwt((string)$sub['endpoint']);
        } catch (\Throwable $e) {
            return false;
        }
        if ($jwt === '') {
            return false;
        }

        $ch = curl_init((string)$sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($body),
                'TTL: 86400',
                'Urgency: high',
                'Authorization: vapid t=' . $jwt . ', k=' . self::publicKey(),
            ],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code === 404 || $code === 410) {
            self::removeSubscription((string)$sub['endpoint']);
            return null;
        }
        return $code >= 200 && $code < 300;
    }

    /** RFC 8291 aes128gcm encryption. Public for the self-test script. */
    public static function encrypt(string $payload, string $p256dhB64, string $authB64): string
    {
        $uaPub = self::b64urlDecode($p256dhB64);      // 65-byte uncompressed point
        $authSecret = self::b64urlDecode($authB64);   // 16 bytes
        if (strlen($uaPub) !== 65 || $uaPub[0] !== "\x04") {
            throw new \RuntimeException('Bad p256dh key');
        }

        // Ephemeral application-server keypair
        $as = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asDet = openssl_pkey_get_details($as);
        $asPub = "\x04" . str_pad($asDet['ec']['x'], 32, "\0", STR_PAD_LEFT)
                        . str_pad($asDet['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // ECDH shared secret with the browser's public key
        $ecdh = openssl_pkey_derive(self::rawPubToPem($uaPub), $as);
        if ($ecdh === false) {
            throw new \RuntimeException('ECDH failed');
        }

        $salt = random_bytes(16);
        $ikm   = hash_hkdf('sha256', $ecdh, 32, 'WebPush: info' . "\0" . $uaPub . $asPub, $authSecret);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // Single record: payload + 0x02 delimiter (last record)
        $tag = '';
        $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ct === false) {
            throw new \RuntimeException('AES-GCM failed');
        }

        // aes128gcm content-coding header: salt(16) | rs(4) | idlen(1) | keyid(65)
        return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
    }

    /** VAPID ES256 JWT for the endpoint's origin. */
    public static function vapidJwt(string $endpoint): string
    {
        self::publicKey();   // ensure keys exist
        $privPem = Database::setting('vapid_private_pem');
        if ($privPem === '') {
            return '';
        }
        $u = parse_url($endpoint);
        $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');

        $header  = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = self::b64url(json_encode([
            'aud' => $aud,
            'exp' => time() + 12 * 3600,
            'sub' => Database::setting('vapid_subject', 'mailto:admin@localhost'),
        ]));
        $signing = $header . '.' . $claims;

        $der = '';
        if (!openssl_sign($signing, $der, $privPem, OPENSSL_ALGO_SHA256)) {
            return '';
        }
        return $signing . '.' . self::b64url(self::derSigToRaw($der));
    }

    // ---------------------------------------------------------- helpers

    /** Wrap a raw uncompressed P-256 point into a SubjectPublicKeyInfo PEM. */
    public static function rawPubToPem(string $raw): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Convert an OpenSSL DER ECDSA signature to raw 64-byte R||S. */
    private static function derSigToRaw(string $der): string
    {
        $off = 2;
        if (ord($der[1]) & 0x80) {
            $off += ord($der[1]) & 0x7f;   // long-form length
        }
        $rl = ord($der[$off + 1]);
        $r = substr($der, $off + 2, $rl);
        $off = $off + 2 + $rl;
        $sl = ord($der[$off + 1]);
        $s = substr($der, $off + 2, $sl);
        $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
        return $r . $s;
    }

    public static function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/')) ?: '';
    }
}
