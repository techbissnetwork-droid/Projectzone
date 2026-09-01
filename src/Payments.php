<?php
declare(strict_types=1);

namespace SignalMasterAi;

use PDO;

/**
 * Payment handling:
 *  - Automatic: Cryptomus hosted checkout (invoice created via API, member
 *    upgraded automatically by the signed webhook or a status re-check).
 *  - Manual: admin-defined instructions (wallet address / account number /
 *    link); the member uploads proof, the admin approves in the panel.
 * A successful payment extends members.paid_until by the plan's duration.
 */
class Payments
{
    public const UPLOAD_DIR = SMA_UPLOAD_DIR;

    /**
     * How long a hosted invoice stays payable, and how long an order may be
     * reused - ONE number, because they were two and they were equal.
     *
     * The invoice was created with lifetime 3600 and findReusable() matched a
     * pending order for 3600 seconds, so an order made fifty-nine minutes ago
     * was handed straight back to the buyer as a live checkout. It is not
     * live: it is a Cryptomus invoice about to expire, or already expired, and
     * the member presses pay and lands on a dead page. From their side the
     * gateway "does not work"; from the site's side everything succeeded.
     *
     * Reuse now stops at half the lifetime, which leaves a full half-hour to
     * finish paying an invoice that is genuinely still open. Anything older
     * gets a fresh invoice, which is cheap and always works.
     */
    public const INVOICE_LIFETIME = 3600;
    public const REUSE_WINDOW     = 1800;

    /**
     * How long a plan lasts, in words, in one place.
     *
     * days = 0 is "no expiry" - the convention the admin's grant-by-hand form
     * has always used - and it now reaches plans and activation keys too. It
     * gets a name here because six pages print this number and "0 days of
     * premium" on the checkout page is not a typo anybody forgives.
     */
    public static function planLength(int $days, bool $short = false): string
    {
        if ($days <= 0) {
            return 'Lifetime';
        }
        return $short ? $days . 'd' : $days . ' day' . ($days === 1 ? '' : 's');
    }

    public static function gatewayConfigured(): bool
    {
        return Database::setting('cryptomus_merchant_uuid') !== ''
            && Database::setting('cryptomus_api_key') !== '';
    }

    /** Create a payment row. Returns the payment id. */
    public static function createRecord(array $member, array $plan, array $method): int
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO payments (member_id, plan_id, plan_name, plan_days, method_name, kind,
                                   amount_usd, currency, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $member['id'], $plan['id'], $plan['name'], $plan['days'], $method['name'], $method['kind'],
            $plan['price_usd'], $method['currency'], 'pending', time(), time(),
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Create a Cryptomus invoice for a payment row.
     * Returns the hosted checkout URL. Throws on API failure.
     */
    public static function createCryptomusInvoice(int $paymentId, array $plan, string $toCurrency, string $baseUrl): string
    {
        $payload = [
            'amount'       => number_format((float)$plan['price_usd'], 2, '.', ''),
            'currency'     => 'USD',
            'order_id'     => self::orderRef($paymentId),
            'url_callback' => $baseUrl . '/payment_webhook.php',
            'url_return'   => $baseUrl . '/account.php',
            'url_success'  => $baseUrl . '/account.php?paid=1',
            'lifetime'     => self::INVOICE_LIFETIME,
        ];
        if ($toCurrency !== '') {
            $payload['to_currency'] = $toCurrency;
        }
        $res = self::cryptomusRequest('https://api.cryptomus.com/v1/payment', $payload);
        $uuid = $res['result']['uuid'] ?? '';
        $url  = $res['result']['url'] ?? '';
        if ($url === '') {
            throw new \RuntimeException('Gateway did not return a checkout URL');
        }
        Database::pdo()->prepare('UPDATE payments SET gateway_uuid = ?, gateway_url = ?, updated_at = ? WHERE id = ?')
            ->execute([$uuid, $url, time(), $paymentId]);
        return $url;
    }

    /** Re-check a Cryptomus payment's status via the API and sync it. */
    public static function refreshCryptomusStatus(array $payment): string
    {
        $res = self::cryptomusRequest('https://api.cryptomus.com/v1/payment/info', [
            'order_id' => self::orderRef((int)$payment['id'], (int)$payment['created_at']),
        ]);
        $status = (string)($res['result']['payment_status'] ?? 'unknown');
        if (in_array($status, ['paid', 'paid_over'], true) && $payment['status'] !== 'paid') {
            // Same amount check as the webhook takes. A member refreshing the
            // order page must not be a way around it.
            $got = $res['result']['payment_amount_usd'] ?? null;
            self::approveChecked((int)$payment['id'], $payment,
                $got !== null && (float)$got > 0 ? (float)$got : null);
        } elseif (in_array($status, ['cancel', 'fail', 'wrong_amount', 'system_fail'], true)
                  && $payment['status'] !== 'paid') {
            self::setStatus((int)$payment['id'], 'failed');
        } elseif ($status === 'expired' && $payment['status'] !== 'paid') {
            self::setStatus((int)$payment['id'], 'expired');
        }
        return $status;
    }

    /**
     * One authenticated round trip, used only to test the connection.
     *
     * Throws on an unreachable host or a refused credential; returns quietly
     * when Cryptomus answered - including when it answers "no such order",
     * which is the expected reply and is exactly the proof wanted: the
     * signature was checked and accepted before the lookup happened.
     */
    public static function cryptomusProbe(): string
    {
        // THE REAL CALL, not a cheaper one that happens to be nearby.
        //
        // This asked /payment/info, which needs a reachable host and a valid
        // signature and nothing else - so it passed on an install where
        // creating an invoice failed, and a test that says "accepted" while
        // checkout does not work is worse than no test at all. Reported as
        // exactly that.
        //
        // Creating an invoice is the call that breaks, because it is the one
        // carrying an amount and three callback URLs, any of which Cryptomus
        // can refuse. So the test creates one, for one cent, with the same
        // URLs a buyer's checkout would send. It is never paid and expires in
        // five minutes.
        $base = Request::siteUrl();
        $res = self::cryptomusRequest('https://api.cryptomus.com/v1/payment', [
            'amount'       => '1.00',
            'currency'     => 'USD',
            'order_id'     => 'sma-test-' . substr(bin2hex(random_bytes(6)), 0, 12),
            'url_callback' => $base . '/payment_webhook.php',
            'url_return'   => $base . '/account.php',
            'url_success'  => $base . '/account.php?paid=1',
            'lifetime'     => 300,
        ]);
        $url = (string)($res['result']['url'] ?? '');
        if ($url === '') {
            throw new \RuntimeException('Cryptomus accepted the request but returned no checkout URL');
        }
        return $base;
    }

    /** Verify a Cryptomus webhook signature. */
    public static function verifyWebhook(array $data): bool
    {
        $sign = $data['sign'] ?? '';
        if (!is_string($sign) || $sign === '') {
            return false;
        }
        $apiKey = Database::setting('cryptomus_api_key');
        // NO KEY MEANS NO VALID SIGNATURE. Never "no key means every signature
        // is valid", which is what this did.
        //
        // The signature is md5(base64(body) . apiKey). With the key empty -
        // the default, and the state of every install that uses a different
        // gateway or takes payment by hand - that reduces to md5(base64(body)),
        // which anyone can compute from the body they are already sending. So
        // an unconfigured Cryptomus turned this endpoint into: POST a JSON
        // body naming any pending payment, receive a year of premium.
        //
        // Verified before the fix on a test install: a forged callback with no
        // secret whatsoever returned 200 and moved a free member to paid until
        // 2027. The other four gateways all refuse an unset secret already;
        // this was the one that did not.
        if ($apiKey === '') {
            return false;
        }
        unset($data['sign']);
        $expected = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey);
        return hash_equals($expected, $sign);
    }

    /**
     * Reuse a recent identical pending order instead of creating a new one -
     * stops double-click / retry checkouts from stacking duplicate invoices
     * (a classic cause of gateway "duplicate order" errors).
     *
     * "Recent" means inside REUSE_WINDOW, which is deliberately shorter than
     * the invoice's own lifetime - see the note on those constants.
     */
    public static function findReusable(int $memberId, int $planId, string $kind, string $methodName, string $coupon = ''): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM payments
             WHERE member_id = ? AND plan_id = ? AND kind = ? AND method_name = ? AND coupon = ?
               AND status = 'pending' AND created_at > ?
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$memberId, $planId, $kind, $methodName, $coupon, time() - self::REUSE_WINDOW]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        if (!$row) {
            return null;
        }
        // AN AUTOMATIC ORDER WITH NO CHECKOUT LINK IS NOT REUSABLE.
        //
        // It is the wreckage of an attempt that died between creating the row
        // and getting a URL back - the request timed out, the host could not
        // reach the gateway, the tab was closed. There is nothing to send the
        // member to, and reusing it did exactly that: every retry for the next
        // hour matched this row, redirected to an order page showing "PENDING"
        // with no button and no explanation, and never called the gateway
        // again. One dead attempt locked the member out of paying for an hour.
        //
        // Reproduced before the fix by planting such a row and pressing
        // Continue to payment: 302 to upgrade.php?pay=NN, and a page whose
        // only control was "Back to account".
        if ($kind !== 'manual' && trim((string)($row['gateway_url'] ?? '')) === '') {
            return null;
        }
        return $row;
    }

    /**
     * Validate a coupon code. Returns the coupon row or null when the code is
     * unknown, disabled, expired or fully used.
     */
    public static function validCoupon(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^[A-Z0-9\-]{2,32}$/', $code)) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM coupons WHERE code = ? AND enabled = 1');
        $stmt->execute([$code]);
        $c = $stmt->fetch();
        $stmt->closeCursor();
        if (!$c) {
            return null;
        }
        if ((int)$c['expires_at'] > 0 && (int)$c['expires_at'] < time()) {
            return null;
        }
        if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return null;
        }
        return $c;
    }

    /**
     * Take one use of a coupon, atomically, or refuse.
     *
     * THE CHECK AND THE COUNT WERE A HUNDRED LINES APART.
     *
     * validCoupon() READ used_count and compared it to max_uses; approve()
     * incremented it later. Between those two, every concurrent checkout sees
     * the same count and every one of them passes - so a single-use code can
     * be spent several times, and a hundred-percent-off single-use code is
     * then several free premium accounts from one link. Nothing about the
     * sequence was atomic, and validCoupon() cannot be made atomic because it
     * runs before anyone has paid: counting there would burn uses on every
     * abandoned checkout.
     *
     * So the limit is enforced where the use is actually taken, in the
     * statement that takes it. The database decides, once, and rowCount() says
     * whether this caller is the one who got it. The same shape as the paid
     * claim in approve() above, for the same reason.
     */
    public static function claimCouponUse(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }
        try {
            $st = Database::pdo()->prepare(
                'UPDATE coupons SET used_count = used_count + 1
                  WHERE code = ? AND enabled = 1
                    AND (expires_at = 0 OR expires_at > ?)
                    AND (max_uses = 0 OR used_count < max_uses)'
            );
            $st->execute([$code, time()]);
            return $st->rowCount() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The reference a gateway is given for an order, and the way back from it.
     *
     * IT USED TO BE "sma-<row id>", AND THAT IS NOT UNIQUE.
     *
     * Cryptomus keys an invoice by its order_id: send one it has already seen
     * and it hands back the invoice it made the first time, rather than making
     * a new one. Payment row ids start again at 1 whenever the database is
     * reinstalled or wiped - which this site's own installer offers - so after
     * a reinstall, order sma-7 asks Cryptomus for an invoice it created weeks
     * ago and gets it back, dead. The buyer arrives at a hosted page reading
     * "Invoice has been expired. Please create new invoice" on a checkout they
     * started ten seconds earlier, and nothing on this side looks wrong: the
     * API answered 200 with a URL.
     *
     * The row's creation time makes it unique without storing anything new,
     * and it is recomputable from the row, so a status re-check asks about the
     * same order the invoice was made for. Old references keep working - the
     * suffix is optional everywhere it is read - so orders taken before this
     * change still settle.
     */
    public static function orderRef(int $paymentId, ?int $createdAt = null): string
    {
        // Read from the row when the caller does not already hold it. NEVER
        // time(): the row is inserted a moment before the invoice is created,
        // so a reference built from "now" would not match the one a later
        // status re-check builds from created_at, and the re-check would ask
        // the gateway about an order it has never heard of.
        if ($createdAt === null) {
            $stmt = Database::pdo()->prepare('SELECT created_at FROM payments WHERE id = ?');
            $stmt->execute([$paymentId]);
            $createdAt = (int)($stmt->fetchColumn() ?: 0);
            $stmt->closeCursor();
        }
        return 'sma-' . $paymentId . '-' . max(0, $createdAt);
    }

    /** The payment id inside a gateway's order reference, or 0. */
    public static function orderIdFromRef(string $ref): int
    {
        return preg_match('/^sma-(\d+)(?:-\d+)?$/', trim($ref), $m) ? (int)$m[1] : 0;
    }

    /** One payment row by id. */
    public static function find(int $paymentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    /**
     * A signature says the gateway sent this. It does not say the buyer paid
     * what was asked.
     *
     * Every webhook here credited on the STATUS WORD alone, so a $249 lifetime
     * order that the gateway reported as settled for $5 - which happens on a
     * merchant-resolved underpayment at Coinbase, and on an invoice marked
     * settled by hand at BTCPay - bought a lifetime account for five dollars.
     * The status was true; nothing was ever compared against the price.
     *
     * Returns null when the figure is safe to credit, or the reason to hold it.
     * A short payment is NOT rejected: real money arrived, and the operator is
     * the one who decides what it buys.
     *
     * A cent of slack because gateways round differently, and a gateway that
     * reports no figure at all (BTCPay's webhook carries none) returns null -
     * this refuses what it can see, it does not invent evidence.
     */
    public static function shortfall(float $expectedUsd, ?float $reportedUsd, string $currency = 'USD'): ?string
    {
        if ($currency !== '' && strtoupper($currency) !== 'USD') {
            return 'the gateway reported ' . strtoupper($currency) . ', not USD';
        }
        if ($reportedUsd === null || $expectedUsd <= 0) {
            return null;
        }
        if ($reportedUsd + 0.01 < round($expectedUsd, 2)) {
            return 'paid $' . number_format($reportedUsd, 2)
                 . ' against a price of $' . number_format($expectedUsd, 2);
        }
        return null;
    }

    /**
     * Credit a payment, unless the amount says a human should look first.
     *
     * One place, because every gateway needs the same decision and three of
     * them arrive by different routes.
     */
    public static function approveChecked(int $paymentId, array $payment, ?float $reportedUsd, string $currency = 'USD'): void
    {
        $why = self::shortfall((float)$payment['amount_usd'], $reportedUsd, $currency);
        if ($why === null) {
            self::approve($paymentId);
            return;
        }
        // Held, not failed: it lands in Admin > Payments under "Awaiting
        // review" with the figures written on it, and one click approves it.
        Database::pdo()->prepare(
            'UPDATE payments SET status = ?, note = ?, updated_at = ? WHERE id = ? AND status != ?'
        )->execute([
            'awaiting_review',
            trim((string)$payment['note'] . "\nHeld for review: " . $why),
            time(), $paymentId, 'paid',
        ]);
        ErrorLog::record(ErrorLog::PAYMENT,
            'Payment #' . $paymentId . ' held - ' . $why,
            'gateway: ' . (string)$payment['kind'] . '. The gateway confirmed a payment smaller than the '
            . 'price. Nothing was credited. Approve it under Payments if you are happy to accept it.');
    }

    /** Mark paid and extend the member's premium access by the plan days. */
    /**
     * @param bool $couponClaimed the caller already took the coupon use, so
     *                            this must not take a second one. Used by the
     *                            free-order path, which has to claim BEFORE
     *                            granting anything - see upgrade.php.
     */
    public static function approve(int $paymentId, bool $couponClaimed = false): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$paymentId]);
        $p = $stmt->fetch();
        $stmt->closeCursor();
        if (!$p || $p['status'] === 'paid') {
            return;
        }
        // Atomic claim: webhooks can arrive multiple times (retries, webhook
        // + return-page re-check racing) - only ONE caller may credit the plan.
        $claim = $pdo->prepare("UPDATE payments SET status = 'paid', updated_at = ? WHERE id = ? AND status != 'paid'");
        $claim->execute([time(), $paymentId]);
        if ($claim->rowCount() === 0) {
            return;   // someone else credited it already
        }
        // A coupon use only counts when the payment actually completes - and
        // the count is a CLAIM, not an increment. See claimCouponUse().
        //
        // This payment is real and already marked paid, so it is credited
        // whatever the claim says; refusing here would take somebody's money
        // and give them nothing. A claim that fails means the coupon went over
        // its limit, which the operator needs to know about, so it is logged
        // rather than swallowed.
        if (($p['coupon'] ?? '') !== '' && !$couponClaimed) {
            if (!self::claimCouponUse((string)$p['coupon'])) {
                ErrorLog::record(ErrorLog::PAYMENT,
                    'Coupon used beyond its limit - the order was credited anyway because it was '
                    . 'already paid. Lower the remaining uses or disable the code.',
                    'coupon: ' . (string)$p['coupon'] . ', payment #' . $paymentId);
            }
        }

        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([(int)$p['member_id']]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return;
        }
        // NEVER TURN UNLIMITED INTO A DATE.
        //
        // paid_until = 0 on a paid account means no end date - a lifetime
        // grant, or an operator who set the tier by hand and left the date
        // empty. This read that as "expired at the epoch" and fell through to
        // "extend from now", so the member with the most valuable account on
        // the site was downgraded to thirty days by the act of buying
        // something. Referrals::grantDays already refused to do this; the
        // payment path, which is the one that matters, did not.
        //
        // Verified before the fix: a lifetime account redeemed a 30-day key
        // and came back with paid_until set to a date a month out.
        if ((int)$p['plan_days'] <= 0) {
            // A LIFETIME PLAN. days = 0 has meant "no expiry" in the admin's
            // grant-by-hand form since it existed; this is the same convention
            // reaching the things that can be sold. Without the branch the
            // arithmetic below reads it as "now plus nothing" and hands the
            // buyer a subscription that expired the instant they paid.
            $pdo->prepare("UPDATE members SET tier = 'paid', paid_until = 0 WHERE id = ?")
                ->execute([$m['id']]);
        } elseif ($m['tier'] === 'paid' && (int)$m['paid_until'] === 0) {
            $pdo->prepare("UPDATE members SET tier = 'paid' WHERE id = ?")->execute([$m['id']]);
        } else {
            // Extend from the current expiry if still active, else from now -
            // as a compare-and-swap retry loop rather than a plain
            // read/compute/write, because paid_until is scoped to the
            // MEMBER, not this one payment: two different payments for the
            // same member (two purchases close together, or two webhook
            // redeliveries for two different orders) can both reach this
            // branch within moments of each other. A plain write lets the
            // slower one silently overwrite the faster one's extension with
            // a value computed from the same stale "before" date - crediting
            // one payment twice and dropping the other payment's days
            // entirely. Retried against the row's own current value, same
            // shape as the payment-status claim just above.
            for ($try = 0; $try < 5; $try++) {
                $curStmt = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
                $curStmt->execute([$m['id']]);
                $cur = $curStmt->fetch();
                $curStmt->closeCursor();
                if (!$cur) {
                    break;
                }
                $oldUntil = (int)$cur['paid_until'];
                $base = ($cur['tier'] === 'paid' && $oldUntil > time()) ? $oldUntil : time();
                $until = $base + ((int)$p['plan_days']) * 86400;
                $cas = $pdo->prepare('UPDATE members SET tier = ?, paid_until = ? WHERE id = ? AND paid_until = ?');
                $cas->execute(['paid', $until, $m['id'], $oldUntil]);
                if ($cas->rowCount() > 0) {
                    break;   // won the race - extension applied on top of the value it read
                }
                // lost the race: another concurrent approve() changed
                // paid_until between our read and our write - retry against
                // whatever it is now instead of clobbering that change.
            }
        }

        // Referral credit on the first completed payment. Idempotent - the
        // link is cleared once paid out, so a second purchase by the same
        // member cannot be credited again.
        //
        // Only when money actually moved. A referral pays the referrer in
        // premium days, so anything that reaches this point at zero dollars -
        // an activation key from a giveaway, a 100%-off coupon - would mint
        // days out of nothing, and both of those are codes that get posted
        // publicly. One sign-up per free code, each one paying a referrer, is
        // a machine for printing subscriptions.
        if ((float)$p['amount_usd'] > 0) {
            try {
                Referrals::creditFirstPayment((int)$m['id']);
            } catch (\Throwable $e) {
                // a referral bonus must never break a confirmed payment
            }
        }
    }

    public static function setStatus(int $paymentId, string $status): void
    {
        Database::pdo()->prepare('UPDATE payments SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, time(), $paymentId]);
    }

    /**
     * Store an image attached to a manual payment method (QR code, payment
     * card, instructions graphic). Returns the stored filename.
     */
    public static function storeMethodImage(int $methodId, array $file): string
    {
        $name = self::validateAndStore($file, 'pm-' . $methodId);
        return $name;
    }

    /** Store an uploaded proof image. Returns the stored filename. */
    public static function storeProof(int $paymentId, array $file): string
    {
        return self::validateAndStore($file, 'proof-' . $paymentId);
    }

    private static function validateAndStore(array $file, string $prefix): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed - please choose an image file.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('Image too large (max 5 MB).');
        }
        $info = @getimagesize($file['tmp_name']);
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
        if ($info === false || !isset($allowed[$info[2]])) {
            throw new \RuntimeException('File must be a JPG, PNG, WEBP or GIF image.');
        }
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0775, true);
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . '/' . $name)) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }
        return $name;
    }

    /**
     * What to actually do about a Cryptomus rejection.
     *
     * The API answers a wrong credential with two words - "Invalid Sign" -
     * and those two words are the end of the road for anybody who does not
     * already know how Cryptomus is put together. The signature is computed
     * from the API key, so "Invalid Sign" almost always means the key is not
     * the one this merchant expects, and the single commonest reason for that
     * is real: a Cryptomus account issues a PAYMENT key and a PAYOUT key, they
     * look alike, they sit next to each other, and only one of them works
     * here. Saying so turns a dead end into a fix.
     */
    private static function cryptomusAdvice(string $msg): string
    {
        $m = strtolower($msg);
        if (str_contains($m, 'sign')) {
            return ' - that means the Payment API key does not match this merchant. Cryptomus issues'
                 . ' a Payment key and a Payout key; this needs the Payment one. Re-copy it in'
                 . ' Admin > Billing > Gateways.';
        }
        if (str_contains($m, 'merchant')) {
            return ' - check the Merchant UUID in Admin > Billing > Gateways; it is the UUID from'
                 . ' your Cryptomus merchant page, not the API key.';
        }
        // The two rejections that only ever happen on the CREATE call, which
        // is why they never showed up in a test that asked a different
        // endpoint. Both are settings, and both name theirs.
        if (str_contains($m, 'url')) {
            return ' - Cryptomus refused the callback address this site gave it. Set Site URL under'
                 . ' Settings to the public https address members use; it must be reachable from'
                 . ' the internet, not localhost or an IP.';
        }
        if (str_contains($m, 'amount') || str_contains($m, 'minimal') || str_contains($m, 'minimum')) {
            return ' - the plan price is outside what Cryptomus will invoice. Raise it under'
                 . ' Billing > Plans.';
        }
        return '';
    }

    private static function cryptomusRequest(string $url, array $payload): array
    {
        $merchant = Database::setting('cryptomus_merchant_uuid');
        $apiKey   = Database::setting('cryptomus_api_key');
        if ($merchant === '' || $apiKey === '') {
            throw new \RuntimeException('Cryptomus is not configured yet (Admin > Billing).');
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $sign = md5(base64_encode($body) . $apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            // A host that cannot reach the gateway must FAIL, visibly, well
            // inside PHP's execution limit - not sit on the request until
            // something else kills it, which leaves the member staring at
            // "Preparing checkout..." with no error ever rendered.
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'merchant: ' . $merchant,
                'sign: ' . $sign,
            ],
        ]);
        $resBody = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($resBody === false) {
            throw new \RuntimeException("Gateway unreachable: $err");
        }
        $res = json_decode($resBody, true);
        if (!is_array($res)) {
            throw new \RuntimeException("Gateway returned invalid JSON (HTTP $code)");
        }
        if ($code !== 200 || (isset($res['state']) && (int)$res['state'] !== 0)) {
            $msg = $res['message'] ?? ($res['errors'] ? json_encode($res['errors']) : "HTTP $code");
            $msg = is_string($msg) ? $msg : json_encode($msg);
            throw new \RuntimeException('Gateway error: ' . $msg . self::cryptomusAdvice($msg));
        }
        return $res;
    }
}
