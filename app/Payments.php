<?php
declare(strict_types=1);

/**
 * Marketplace payments.
 *
 * A payment method is either "manual" — the buyer is shown instructions and an
 * administrator confirms the money arrived — or a gateway, which the buyer is
 * redirected to and which we verify server-side before marking anything paid.
 *
 * An order is never marked paid from a browser redirect alone: every gateway
 * result is re-checked against the provider's own API first.
 */
final class Payments
{
    public const PROVIDERS = [
        'manual' => [
            'name'   => 'Manual / offline',
            'hint'   => 'Bank transfer, cash, a wallet QR — anything you confirm by hand.',
            'fields' => [],
        ],
        'esewa' => [
            'name'   => 'eSewa',
            'hint'   => 'Nepali wallet. Needs a merchant code and secret key. NPR only.',
            'fields' => ['merchant_code' => 'Merchant / product code', 'secret_key' => 'Secret key'],
        ],
        'khalti' => [
            'name'   => 'Khalti',
            'hint'   => 'Nepali wallet. Needs a live secret key. NPR only.',
            'fields' => ['secret_key' => 'Secret key'],
        ],
        'stripe' => [
            'name'   => 'Stripe (cards)',
            'hint'   => 'International cards. Needs a secret key.',
            'fields' => ['secret_key' => 'Secret key (sk_…)'],
        ],
    ];

    /** Gateways that charge the buyer online rather than by hand. */
    public static function isGateway(string $provider): bool
    {
        return $provider !== 'manual' && isset(self::PROVIDERS[$provider]);
    }

    /** @return array<int,array<string,mixed>> methods a buyer may choose */
    public static function active(): array
    {
        try {
            return Database::all('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id');
        } catch (PDOException) {
            return [];
        }
    }

    public static function method(int $id): ?array
    {
        try {
            return Database::one('SELECT * FROM payment_methods WHERE id = :i AND is_active = 1', ['i' => $id]);
        } catch (PDOException) {
            return null;
        }
    }

    /** @return array<string,string> decoded credentials for a method */
    public static function config(array $method): array
    {
        $raw = json_decode((string)($method['config'] ?? ''), true);
        return is_array($raw) ? array_map('strval', $raw) : [];
    }

    /** A method is only usable once every credential its provider needs is present. */
    public static function isConfigured(array $method): bool
    {
        $provider = (string)$method['provider'];
        if (!self::isGateway($provider)) {
            return true;
        }
        $cfg = self::config($method);
        foreach (array_keys(self::PROVIDERS[$provider]['fields'] ?? []) as $key) {
            if (trim($cfg[$key] ?? '') === '') {
                return false;
            }
        }
        return true;
    }

    public static function logAttempt(array $order, ?array $method, string $status, ?string $ref = null, ?string $message = null, mixed $payload = null): int
    {
        return Database::insert('payment_attempts', [
            'order_id'    => (int)$order['id'],
            'method_id'   => $method ? (int)$method['id'] : null,
            'provider'    => $method ? (string)$method['provider'] : 'manual',
            'status'      => $status,
            'amount'      => (float)$order['amount'],
            'currency'    => (string)$order['currency'],
            'gateway_ref' => $ref,
            'message'     => $message === null ? null : mb_substr($message, 0, 400),
            'payload'     => $payload === null ? null : mb_substr(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '', 0, 8000),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Mark an order paid. Idempotent: calling it twice leaves one paid order
     * and a sale count derived from the orders table, never incremented blindly.
     */
    public static function markPaid(array $order, ?array $method, string $reference): void
    {
        if (in_array($order['status'], ['paid', 'delivered'], true)) {
            return;
        }
        Database::update('orders', [
            'status'            => 'paid',
            'payment_method'    => $method['name'] ?? $order['payment_method'],
            'payment_method_id' => $method['id'] ?? $order['payment_method_id'],
            'payment_ref'       => $reference,
            'paid_at'           => now(),
            'updated_at'        => now(),
        ], (int)$order['id']);

        if ($order['product_id']) {
            Database::run(
                "UPDATE products SET sales_count =
                   (SELECT COUNT(*) FROM orders WHERE product_id = :p AND status IN ('paid','delivered'))
                 WHERE id = :p2",
                ['p' => (int)$order['product_id'], 'p2' => (int)$order['product_id']]
            );
        }
        log_activity('order.paid', 'order', (int)$order['id'], $order['reference'] . ' · ' . $reference);
    }

    /** Where the gateway sends the buyer back to. */
    public static function returnUrl(array $order, string $result = 'return'): string
    {
        return url('payment.php?ref=' . urlencode((string)$order['reference'])
            . '&t=' . urlencode((string)$order['access_token']) . '&result=' . $result);
    }

    public static function orderUrl(array $order): string
    {
        return url('order.php?ref=' . urlencode((string)$order['reference'])
            . '&t=' . urlencode((string)$order['access_token']));
    }

    /**
     * Start a payment.
     * @return array{ok:bool, redirect?:string, form?:array{action:string,fields:array<string,string>}, error?:string}
     */
    public static function start(array $order, array $method): array
    {
        $provider = (string)$method['provider'];
        if (!self::isConfigured($method)) {
            return ['ok' => false, 'error' => 'This payment method is not fully configured yet.'];
        }
        return match ($provider) {
            'esewa'  => self::esewaStart($order, $method),
            'khalti' => self::khaltiStart($order, $method),
            'stripe' => self::stripeStart($order, $method),
            default  => ['ok' => false, 'error' => 'This method is confirmed by hand, not online.'],
        };
    }

    /**
     * Verify a return from a gateway against that gateway's own API.
     * @return array{ok:bool, reference?:string, error?:string}
     */
    public static function verify(array $order, array $method, array $request): array
    {
        return match ((string)$method['provider']) {
            'esewa'  => self::esewaVerify($order, $method, $request),
            'khalti' => self::khaltiVerify($order, $method, $request),
            'stripe' => self::stripeVerify($order, $method, $request),
            default  => ['ok' => false, 'error' => 'Nothing to verify for a manual method.'],
        };
    }

    /* ══════════════════ eSewa (ePay v2) ══════════════════ */

    public static function esewaSignature(string $total, string $uuid, string $productCode, string $secret): string
    {
        $message = 'total_amount=' . $total . ',transaction_uuid=' . $uuid . ',product_code=' . $productCode;
        return base64_encode(hash_hmac('sha256', $message, $secret, true));
    }

    private static function esewaBase(array $method): string
    {
        return (int)$method['is_test'] === 1
            ? 'https://rc-epay.esewa.com.np'
            : 'https://epay.esewa.com.np';
    }

    private static function esewaStart(array $order, array $method): array
    {
        $cfg   = self::config($method);
        $total = number_format((float)$order['amount'], 2, '.', '');
        $uuid  = (string)$order['reference'];
        $code  = $cfg['merchant_code'];

        self::logAttempt($order, $method, 'started', $uuid, 'Redirected to eSewa');

        return ['ok' => true, 'form' => [
            'action' => self::esewaBase($method) . '/api/epay/main/v2/form',
            'fields' => [
                'amount'                   => $total,
                'tax_amount'               => '0',
                'total_amount'             => $total,
                'transaction_uuid'         => $uuid,
                'product_code'             => $code,
                'product_service_charge'   => '0',
                'product_delivery_charge'  => '0',
                'success_url'              => self::returnUrl($order, 'success'),
                'failure_url'              => self::returnUrl($order, 'failure'),
                'signed_field_names'       => 'total_amount,transaction_uuid,product_code',
                'signature'                => self::esewaSignature($total, $uuid, $code, $cfg['secret_key']),
            ],
        ]];
    }

    private static function esewaVerify(array $order, array $method, array $request): array
    {
        $cfg   = self::config($method);
        $total = number_format((float)$order['amount'], 2, '.', '');

        /* eSewa returns a base64 JSON blob; treat it as a hint only and confirm
           the outcome with the status API before trusting anything. */
        $hint = [];
        if (!empty($request['data'])) {
            $decoded = json_decode((string)base64_decode((string)$request['data'], true), true);
            if (is_array($decoded)) {
                $hint = $decoded;
            }
        }

        $url = self::esewaBase($method) . '/api/epay/transaction/status/?'
             . http_build_query([
                 'product_code'     => $cfg['merchant_code'],
                 'total_amount'     => $total,
                 'transaction_uuid' => (string)$order['reference'],
             ]);
        [$body, $err] = self::http('GET', $url);
        if ($err !== null) {
            self::logAttempt($order, $method, 'failed', null, 'Status check failed: ' . $err, $hint);
            return ['ok' => false, 'error' => 'We could not reach eSewa to confirm the payment.'];
        }
        $status = json_decode((string)$body, true);
        if (!is_array($status) || ($status['status'] ?? '') !== 'COMPLETE') {
            self::logAttempt($order, $method, 'failed', $status['ref_id'] ?? null,
                'eSewa reported ' . (string)($status['status'] ?? 'no status'), $status ?: $hint);
            return ['ok' => false, 'error' => 'eSewa has not completed this payment.'];
        }
        /* Guard against a completed payment for a different amount. */
        if (number_format((float)($status['total_amount'] ?? 0), 2, '.', '') !== $total) {
            self::logAttempt($order, $method, 'failed', $status['ref_id'] ?? null, 'Amount mismatch', $status);
            return ['ok' => false, 'error' => 'The amount paid does not match this order.'];
        }
        $ref = (string)($status['ref_id'] ?? $order['reference']);
        self::logAttempt($order, $method, 'paid', $ref, 'Confirmed by eSewa status API', $status);
        return ['ok' => true, 'reference' => $ref];
    }

    /* ══════════════════ Khalti (ePayment) ══════════════════ */

    private static function khaltiBase(array $method): string
    {
        return (int)$method['is_test'] === 1
            ? 'https://dev.khalti.com/api/v2'
            : 'https://khalti.com/api/v2';
    }

    private static function khaltiStart(array $order, array $method): array
    {
        $cfg = self::config($method);
        [$body, $err] = self::http(
            'POST',
            self::khaltiBase($method) . '/epayment/initiate/',
            json_encode([
                'return_url'          => self::returnUrl($order, 'return'),
                'website_url'         => rtrim(url(), '/'),
                'amount'              => (int)round((float)$order['amount'] * 100), // paisa
                'purchase_order_id'   => (string)$order['reference'],
                'purchase_order_name' => mb_substr((string)($order['product_title'] ?? 'Order'), 0, 100),
                'customer_info'       => [
                    'name'  => (string)$order['buyer_name'],
                    'email' => (string)$order['buyer_email'],
                    'phone' => (string)($order['buyer_phone'] ?? ''),
                ],
            ], JSON_UNESCAPED_SLASHES),
            ['Authorization: Key ' . $cfg['secret_key'], 'Content-Type: application/json']
        );
        if ($err !== null) {
            self::logAttempt($order, $method, 'failed', null, 'Initiate failed: ' . $err);
            return ['ok' => false, 'error' => 'We could not reach Khalti. Please try again.'];
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data) || empty($data['payment_url']) || empty($data['pidx'])) {
            self::logAttempt($order, $method, 'failed', null, 'Unexpected initiate response', $data);
            return ['ok' => false, 'error' => 'Khalti did not start this payment.'];
        }
        self::logAttempt($order, $method, 'started', (string)$data['pidx'], 'Redirected to Khalti', $data);
        return ['ok' => true, 'redirect' => (string)$data['payment_url']];
    }

    private static function khaltiVerify(array $order, array $method, array $request): array
    {
        $cfg = self::config($method);

        /* The reference is taken from what we recorded when this order started
           its payment, never from the return URL. A pidx in the query string is
           accepted only when it is one of this order's own, so a completed
           payment cannot be replayed against a second order of the same price —
           which the amount check alone would not catch. */
        $mine = array_column(Database::all(
            "SELECT gateway_ref FROM payment_attempts
             WHERE order_id = :o AND provider = 'khalti' AND gateway_ref IS NOT NULL
             ORDER BY id DESC",
            ['o' => (int)$order['id']]
        ), 'gateway_ref');
        $asked = (string)($request['pidx'] ?? '');
        $pidx  = ($asked !== '' && in_array($asked, $mine, true)) ? $asked : (string)($mine[0] ?? '');

        if ($pidx === '') {
            return ['ok' => false, 'error' => 'This payment has no Khalti reference to check.'];
        }
        if ($asked !== '' && $asked !== $pidx) {
            self::logAttempt($order, $method, 'failed', $asked, 'Returned pidx does not belong to this order');
            return ['ok' => false, 'error' => 'That payment reference does not belong to this order.'];
        }
        [$body, $err] = self::http(
            'POST',
            self::khaltiBase($method) . '/epayment/lookup/',
            json_encode(['pidx' => $pidx]),
            ['Authorization: Key ' . $cfg['secret_key'], 'Content-Type: application/json']
        );
        if ($err !== null) {
            self::logAttempt($order, $method, 'failed', $pidx, 'Lookup failed: ' . $err);
            return ['ok' => false, 'error' => 'We could not reach Khalti to confirm the payment.'];
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'Completed') {
            self::logAttempt($order, $method, 'failed', $pidx,
                'Khalti reported ' . (string)($data['status'] ?? 'no status'), $data);
            return ['ok' => false, 'error' => 'Khalti has not completed this payment.'];
        }
        if ((int)($data['total_amount'] ?? 0) !== (int)round((float)$order['amount'] * 100)) {
            self::logAttempt($order, $method, 'failed', $pidx, 'Amount mismatch', $data);
            return ['ok' => false, 'error' => 'The amount paid does not match this order.'];
        }
        self::logAttempt($order, $method, 'paid', $pidx, 'Confirmed by Khalti lookup', $data);
        return ['ok' => true, 'reference' => $pidx];
    }

    /* ══════════════════ Stripe Checkout ══════════════════ */

    private static function stripeStart(array $order, array $method): array
    {
        $cfg      = self::config($method);
        $currency = strtolower((string)$order['currency']);
        /* Stripe takes the smallest currency unit; these have no minor unit. */
        $zeroDecimal = ['jpy','krw','vnd','clp','isk','ugx','xaf','xof','xpf','bif','djf','gnf','kmf','mga','pyg','rwf','vuv'];
        $unit = in_array($currency, $zeroDecimal, true)
            ? (int)round((float)$order['amount'])
            : (int)round((float)$order['amount'] * 100);

        [$body, $err] = self::http(
            'POST',
            'https://api.stripe.com/v1/checkout/sessions',
            http_build_query([
                'mode'                                   => 'payment',
                'success_url'                            => self::returnUrl($order, 'return') . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'                             => self::returnUrl($order, 'cancel'),
                'client_reference_id'                    => (string)$order['reference'],
                'customer_email'                         => (string)$order['buyer_email'],
                'line_items' => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => $unit,
                        'product_data' => ['name' => mb_substr((string)($order['product_title'] ?? 'Order'), 0, 120)],
                    ],
                ]],
                'metadata' => ['order_reference' => (string)$order['reference']],
            ]),
            ['Authorization: Bearer ' . $cfg['secret_key'], 'Content-Type: application/x-www-form-urlencoded']
        );
        if ($err !== null) {
            self::logAttempt($order, $method, 'failed', null, 'Session failed: ' . $err);
            return ['ok' => false, 'error' => 'We could not reach Stripe. Please try again.'];
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data) || empty($data['url']) || empty($data['id'])) {
            self::logAttempt($order, $method, 'failed', null, 'Unexpected session response', $data);
            return ['ok' => false, 'error' => 'Stripe did not start this payment.'];
        }
        self::logAttempt($order, $method, 'started', (string)$data['id'], 'Redirected to Stripe', ['id' => $data['id']]);
        return ['ok' => true, 'redirect' => (string)$data['url']];
    }

    private static function stripeVerify(array $order, array $method, array $request): array
    {
        $cfg = self::config($method);
        $sid = (string)($request['session_id'] ?? '');
        if ($sid === '') {
            $sid = (string)Database::value(
                "SELECT gateway_ref FROM payment_attempts
                 WHERE order_id = :o AND provider = 'stripe' AND gateway_ref IS NOT NULL
                 ORDER BY id DESC LIMIT 1",
                ['o' => (int)$order['id']], ''
            );
        }
        if ($sid === '') {
            return ['ok' => false, 'error' => 'This payment has no Stripe session to check.'];
        }
        [$body, $err] = self::http(
            'GET',
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sid),
            null,
            ['Authorization: Bearer ' . $cfg['secret_key']]
        );
        if ($err !== null) {
            self::logAttempt($order, $method, 'failed', $sid, 'Session check failed: ' . $err);
            return ['ok' => false, 'error' => 'We could not reach Stripe to confirm the payment.'];
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data) || ($data['payment_status'] ?? '') !== 'paid') {
            self::logAttempt($order, $method, 'failed', $sid,
                'Stripe reported ' . (string)($data['payment_status'] ?? 'no status'), $data);
            return ['ok' => false, 'error' => 'Stripe has not completed this payment.'];
        }
        /* The session must belong to this order. */
        if ((string)($data['client_reference_id'] ?? '') !== (string)$order['reference']) {
            self::logAttempt($order, $method, 'failed', $sid, 'Session belongs to another order', $data);
            return ['ok' => false, 'error' => 'That payment does not belong to this order.'];
        }
        $ref = (string)($data['payment_intent'] ?? $sid);
        self::logAttempt($order, $method, 'paid', $ref, 'Confirmed by Stripe session lookup', ['id' => $sid]);
        return ['ok' => true, 'reference' => $ref];
    }

    /* ══════════════════ transport ══════════════════ */

    /** @return array{0:?string,1:?string} [body, error] */
    private static function http(string $verb, string $url, ?string $body = null, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            return [null, 'The cURL extension is not installed on this server.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($verb === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$body);
        }
        $out  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out === false || $err !== '') {
            return [null, $err !== '' ? $err : 'no response'];
        }
        if ($code >= 400) {
            return [null, 'HTTP ' . $code . ' — ' . mb_substr((string)$out, 0, 200)];
        }
        return [(string)$out, null];
    }
}
