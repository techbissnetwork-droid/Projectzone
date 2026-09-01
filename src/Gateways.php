<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Automatic payment gateways - up to five, all configured in Admin > Billing:
 *   cryptomus   - hosted crypto checkout, MD5-signed webhook
 *   coingate    - hosted crypto checkout, webhook verified by API pull
 *   nowpayments - hosted crypto invoice, HMAC-SHA512 IPN
 *   coinbase    - Coinbase Commerce charge, HMAC-SHA256 webhook
 *   btcpay      - self-hosted BTCPay Server (Greenfield API), HMAC webhook
 *
 * Each gateway implements: configured(), createInvoice(), refreshStatus()
 * and webhook handling routed through payment_webhook.php?gw=<name>.
 */
class Gateways
{
    public const LABELS = [
        'cryptomus'   => 'Cryptomus (crypto)',
        'coingate'    => 'CoinGate (crypto)',
        'nowpayments' => 'NOWPayments (crypto)',
        'coinbase'    => 'Coinbase Commerce (crypto)',
        'btcpay'      => 'BTCPay Server (self-hosted crypto)',
    ];

    public static function configured(string $gw): bool
    {
        return match ($gw) {
            'cryptomus'   => Payments::gatewayConfigured(),
            'coingate'    => Database::setting('gw_coingate_api_key') !== '',
            'nowpayments' => Database::setting('gw_nowpayments_api_key') !== '',
            'coinbase'    => Database::setting('gw_coinbase_api_key') !== '',
            'btcpay'      => Database::setting('gw_btcpay_host') !== ''
                          && Database::setting('gw_btcpay_store_id') !== ''
                          && Database::setting('gw_btcpay_api_key') !== '',
            default       => false,
        };
    }

    public static function enabled(string $gw): bool
    {
        // All five the same way now. Cryptomus used to be hard-coded on and
        // "enabled through its currency rows", which meant an operator who had
        // pasted credentials they no longer wanted had no way to say so: the
        // panel went on demanding a currency row for a processor they had
        // decided against, and the only cure was emptying the fields.
        //
        // Default '1', so an install that was already taking Cryptomus keeps
        // taking it. The tick is how you stop.
        return Database::setting("gw_{$gw}_enabled", $gw === 'cryptomus' ? '1' : '0') === '1'
               && self::configured($gw);
    }

    /**
     * Why a gateway cannot appear at checkout, in words. Null when it can.
     *
     * The checkout hides an automatic method whose gateway has no credentials,
     * which is right - offering a button that leads to "Gateway is not
     * configured yet" is worse than offering nothing. What was wrong is that
     * NOTHING SAID SO. An operator added an automatic method, the admin table
     * showed it ENABLED, and the upgrade page said "No payment methods are
     * configured yet - contact the site operator". Two screens, two opposite
     * answers, and no third screen reconciling them.
     *
     * The missing credential is named rather than hinted at, because the two
     * Cryptomus fields save differently - the API key field is deliberately
     * blank-to-keep, so an operator who filled in the merchant UUID, left the
     * key alone and pressed Save has done everything the form asked and still
     * has no gateway.
     */
    public static function blocked(string $gw, bool $named = true): ?string
    {
        $why = self::blockReason($gw);
        if ($why === null) {
            return null;
        }
        // Named for prose - a warning at the top of a page has to say WHICH
        // gateway. Unnamed for a row that already carries the name in its own
        // heading, where repeating it reads as a stutter: "Coinbase Commerce -
        // Coinbase Commerce has no API key yet".
        return $named ? self::name($gw) . ' has a problem: ' . $why : $why;
    }

    /** The gateway's name without the "(crypto)" tail the picker needs. */
    public static function name(string $gw): string
    {
        $label = self::LABELS[$gw] ?? ucfirst($gw);
        return trim(preg_replace('/\s*\(.*\)$/', '', $label) ?? $label);
    }

    private static function blockReason(string $gw): ?string
    {
        if (self::configured($gw)) {
            // CRYPTOMUS IS NOT LIVE UNTIL A CURRENCY IS.
            //
            // Its switch is the payment_methods rows, one per coin, so
            // credentials alone put nothing in front of a member. Without this
            // the badge read LIVE AT CHECKOUT directly above a red box saying
            // nothing Cryptomus appears at checkout - the same contradiction
            // this helper was written to end, reintroduced one line lower.
            // Configured but switched off is a decision, not a fault.
            //
            // AND THAT IS THE WHOLE TEST, for all five. This used to demand a
            // currency row for Cryptomus as well, on the belief that its
            // checkout needed to be told which coin. It does not: the invoice
            // is raised in USD and to_currency is optional - createInvoice()
            // has always passed an empty string - so Cryptomus shows its own
            // coin picker and the buyer chooses there, exactly like every
            // other hosted checkout on this list. The extra requirement was
            // invented here, and all it did was hold a correctly-configured
            // gateway off the checkout until the operator guessed what else
            // was wanted.
            return self::enabled($gw) ? null : 'switched off here';
        }
        return match ($gw) {
            'cryptomus' => Database::setting('cryptomus_merchant_uuid') === ''
                ? 'no merchant UUID or API key yet'
                : 'merchant UUID saved, but no API key yet',
            'btcpay'      => 'needs a host, a store ID and an API key',
            default       => 'no API key yet',
        };
    }

    /**
     * Gateways ready to show at checkout as a single button.
     *
     * Cryptomus is in this list now. A member picking it lands on Cryptomus's
     * own page and chooses their coin there, which is what the other four
     * already did and what Cryptomus does when no to_currency is sent.
     *
     * It drops out again when the operator has listed specific coins, because
     * those are already separate buttons: offering "USDT", "BTC" AND a general
     * "Cryptomus" is three buttons for two choices, and a buyer cannot tell
     * what the third one does differently.
     */
    public static function availableExtra(): array
    {
        $out = [];
        foreach (['cryptomus', 'coingate', 'nowpayments', 'coinbase', 'btcpay'] as $gw) {
            if (!self::enabled($gw)) {
                continue;
            }
            if ($gw === 'cryptomus' && self::coinRows() > 0) {
                continue;
            }
            $out[$gw] = self::LABELS[$gw];
        }
        return $out;
    }

    /**
     * Can this server actually use this gateway, right now?
     *
     * The question an operator cannot answer from the panel and cannot ask a
     * member to answer for them. A checkout that hangs on "Preparing
     * checkout..." has exactly two causes - the host cannot reach the
     * gateway's API at all, which is ordinary on shared hosting where outbound
     * HTTPS is closed, or the credentials are refused - and from the buyer's
     * side those look identical. This makes the call from the admin panel and
     * says which.
     *
     * Cryptomus is probed properly: an authenticated request that must fail on
     * its subject (no such order) but must SUCCEED on its signature, so the
     * answer separates "keys wrong" from "keys fine". The others get a
     * reachability probe against their API host, which is the half that
     * actually breaks, and the answer says plainly that it is only that half.
     *
     * @return array{ok:bool,msg:string}
     */
    public static function test(string $gw): array
    {
        if (!self::configured($gw)) {
            return [false, self::blocked($gw, false) ?? 'not configured'];
        }
        try {
            if ($gw === 'cryptomus') {
                $base = Payments::cryptomusProbe();
                // The address is reported back because it is the setting most
                // likely to be wrong and the one nobody thinks to check: a
                // gateway happily creates an invoice pointing its webhook at
                // an address that does not serve this site, and the failure
                // then arrives hours later as "the payment never activated".
                // AND CAN THE GATEWAY REACH US BACK?
                //
                // Creating the invoice proves this server can call out. The
                // other half of a working payment is Cryptomus calling in, to
                // the address we just handed it, and that half fails silently:
                // the buyer pays, the webhook 404s, and premium never turns
                // on. Checked here because this is the moment the address is
                // known and the operator is looking.
                $hook = $base . '/payment_webhook.php';
                $reach = self::urlAnswers($hook);
                return [true, 'Cryptomus created a test invoice - credentials, price and callback '
                            . 'address all accepted. It was told this site is at ' . $base . '. '
                            . ($reach
                                ? 'That address answers when this server calls it.'
                                : 'WARNING: nothing answers at ' . $hook . ', so a payment may never '
                                . 'switch premium on. Check Site URL under Settings points at the '
                                . 'public address of this site.')];
            }
            // THESE NOW CHECK THE KEY, NOT JUST THE HOST.
            //
            // Every one of these used to be an unauthenticated GET - /v2/ping,
            // /v1/status, /api/v1/health - which answers the same for a correct
            // key, a typo'd key and no key at all. That is the test that already
            // caused trouble on Cryptomus: it said "accepted" while checkout was
            // broken. Each gateway has an endpoint that refuses a bad
            // credential, so each one is asked that instead, and BTCPay is asked
            // about the configured store so a wrong store id shows up here
            // rather than at a buyer's checkout.
            [$host, $headers] = match ($gw) {
                'coingate'    => ['https://api.coingate.com/v2/auth/test',
                                  ['Authorization: Token ' . Database::setting('gw_coingate_api_key')]],
                'nowpayments' => ['https://api.nowpayments.io/v1/merchant/coins',
                                  ['x-api-key: ' . Database::setting('gw_nowpayments_api_key')]],
                'coinbase'    => ['https://api.commerce.coinbase.com/charges?limit=1',
                                  ['X-CC-Api-Key: ' . Database::setting('gw_coinbase_api_key'),
                                   'X-CC-Version: 2018-03-22']],
                'btcpay'      => [self::btcpayHost()
                                  . '/api/v1/stores/' . rawurlencode(Database::setting('gw_btcpay_store_id')),
                                  ['Authorization: token ' . Database::setting('gw_btcpay_api_key')]],
                default       => ['', []],
            };
            if ($host === '') {
                return [false, 'No test available for this gateway.'];
            }
            self::http($host, 'GET', $headers);
            return [true, 'Reachable from this server and the credentials were accepted. '
                        . 'Make sure the callback URL at ' . self::name($gw) . ' points at '
                        . Request::siteUrl() . '/payment_webhook.php?gw=' . $gw
                        . ' - that is the half a test cannot prove.'];
        } catch (\Throwable $e) {
            $m = $e->getMessage();
            if (str_contains($m, 'unreachable')) {
                $m .= ' - this server could not open a connection. On shared hosting outbound HTTPS '
                    . 'is often blocked; ask the host to allow it, or use a manual payment method.';
            }
            return [false, $m];
        }
    }

    /** Does this URL answer at all? Used to check our own webhook endpoint. */
    private static function urlAnswers(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $ok = curl_exec($ch) !== false;
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        // What this can and cannot prove, stated so nobody reads more into a
        // pass than is there: a failure means the address does not resolve or
        // nothing is listening, which is the case worth catching - a Site URL
        // pointing at the wrong host, or at localhost. A pass means something
        // answered. On a site with a catch-all rewrite - clean URLs, which is
        // most of them - something answers for every path, so a pass is not
        // proof the webhook file itself is being served.
        return $ok && $code > 0 && $code !== 404;
    }

    /** How many Cryptomus coins the operator has put on the checkout. */
    public static function coinRows(): int
    {
        return (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM payment_methods WHERE kind = 'cryptomus' AND enabled = 1"
        )->fetchColumn();
    }

    /**
     * Create a hosted-checkout invoice for a payment row.
     * Returns the URL to redirect the member to.
     */
    public static function createInvoice(string $gw, int $paymentId, array $plan, string $baseUrl): string
    {
        $amount = number_format((float)$plan['price_usd'], 2, '.', '');
        $orderId = Payments::orderRef($paymentId);
        $callback = $baseUrl . '/payment_webhook.php?gw=' . $gw;
        $return = $baseUrl . '/account.php?paid=1';

        switch ($gw) {
            case 'cryptomus':
                return Payments::createCryptomusInvoice($paymentId, $plan, '', $baseUrl);

            case 'coingate': {
                // DESCRIPTION IS REQUIRED, AND WAS NOT BEING SENT.
                //
                // CoinGate's create-order reference lists four mandatory
                // fields: price_amount, price_currency, title and description.
                // Three were here. An order missing description is rejected on
                // validation, so CoinGate could never open a checkout - the
                // same shape of failure as the BTCPay webhook: configured,
                // enabled, and unable to complete a single sale.
                //
                // receive_currency was 'USDT', which CoinGate only accepts if
                // that exact payout currency is enabled on the merchant's
                // account; where it is not, the order is refused. DO_NOT_CONVERT
                // is the one value that is always valid - the merchant is
                // credited in whatever the buyer paid - and it matches how the
                // other gateways here are set up: take what the gateway takes,
                // do not make the operator pick.
                $site = Database::setting('site_name', 'SignalMasterAi');
                $res = self::http('https://api.coingate.com/v2/orders', 'POST', [
                    'Authorization: Token ' . Database::setting('gw_coingate_api_key'),
                    'Content-Type: application/x-www-form-urlencoded',
                ], http_build_query([
                    'order_id'         => $orderId,
                    'price_amount'     => $amount,
                    'price_currency'   => 'USD',
                    'receive_currency' => 'DO_NOT_CONVERT',
                    // Their limits are title 3-150 and description 3-500. A
                    // long site name plus a long plan name can pass 150, and
                    // that is a validation failure at checkout, so both are
                    // clamped rather than trusted to be short.
                    'title'            => mb_substr($plan['name'] . ' - ' . $site, 0, 150),
                    'description'      => mb_substr(
                        $plan['name'] . ' premium subscription at ' . $site
                        . ' (' . Payments::planLength((int)$plan['days']) . ')', 0, 500),
                    'callback_url'     => $callback,
                    'success_url'      => $return,
                    'cancel_url'       => $baseUrl . '/upgrade.php',
                ]));
                $url = (string)($res['payment_url'] ?? '');
                $uuid = (string)($res['id'] ?? '');
                break;
            }

            case 'nowpayments': {
                $res = self::http('https://api.nowpayments.io/v1/invoice', 'POST', [
                    'x-api-key: ' . Database::setting('gw_nowpayments_api_key'),
                    'Content-Type: application/json',
                ], json_encode([
                    'price_amount'     => (float)$amount,
                    'price_currency'   => 'usd',
                    'order_id'         => $orderId,
                    'order_description'=> $plan['name'],
                    'ipn_callback_url' => $callback,
                    'success_url'      => $return,
                    'cancel_url'       => $baseUrl . '/upgrade.php',
                ]));
                $url = (string)($res['invoice_url'] ?? '');
                $uuid = (string)($res['id'] ?? '');
                break;
            }

            case 'coinbase': {
                $res = self::http('https://api.commerce.coinbase.com/charges', 'POST', [
                    'X-CC-Api-Key: ' . Database::setting('gw_coinbase_api_key'),
                    'X-CC-Version: 2018-03-22',
                    'Content-Type: application/json',
                ], json_encode([
                    'name'         => $plan['name'] . ' - ' . Database::setting('site_name', 'SignalMasterAi'),
                    'description'  => 'Premium subscription',
                    'pricing_type' => 'fixed_price',
                    'local_price'  => ['amount' => $amount, 'currency' => 'USD'],
                    'metadata'     => ['order_id' => $orderId],
                    'redirect_url' => $return,
                    'cancel_url'   => $baseUrl . '/upgrade.php',
                ]));
                $url = (string)($res['data']['hosted_url'] ?? '');
                $uuid = (string)($res['data']['code'] ?? '');
                break;
            }

            case 'btcpay': {
                $host = self::btcpayHost();
                $store = Database::setting('gw_btcpay_store_id');
                $res = self::http("$host/api/v1/stores/$store/invoices", 'POST', [
                    'Authorization: token ' . Database::setting('gw_btcpay_api_key'),
                    'Content-Type: application/json',
                ], json_encode([
                    'amount'   => $amount,
                    'currency' => 'USD',
                    'metadata' => ['orderId' => $orderId],
                    'checkout' => ['redirectURL' => $return],
                ]));
                $url = (string)($res['checkoutLink'] ?? '');
                $uuid = (string)($res['id'] ?? '');
                break;
            }

            default:
                throw new \RuntimeException('Unknown gateway');
        }

        if ($url === '') {
            throw new \RuntimeException(ucfirst($gw) . ' did not return a checkout URL');
        }
        Database::pdo()->prepare('UPDATE payments SET gateway_uuid = ?, gateway_url = ?, updated_at = ? WHERE id = ?')
            ->execute([$uuid, $url, time(), $paymentId]);
        return $url;
    }

    /** Re-check a payment against its gateway and sync local status. */
    public static function refreshStatus(array $payment): string
    {
        $gw = (string)$payment['kind'];
        $uuid = (string)$payment['gateway_uuid'];
        $id = (int)$payment['id'];

        switch ($gw) {
            case 'cryptomus':
                return Payments::refreshCryptomusStatus($payment);

            case 'coingate': {
                $res = self::http('https://api.coingate.com/v2/orders/' . urlencode($uuid), 'GET', [
                    'Authorization: Token ' . Database::setting('gw_coingate_api_key'),
                ]);
                $status = (string)($res['status'] ?? 'unknown');
                // CoinGate answers with the priced figure and, when the buyer
                // sent too little, how much is missing.
                $paidUsd = isset($res['price_amount']) ? (float)$res['price_amount'] : null;
                if ($paidUsd !== null && isset($res['underpaid_amount'])) {
                    $paidUsd = max(0.0, $paidUsd - (float)$res['underpaid_amount']);
                }
                self::apply($id, $payment, in_array($status, ['paid'], true),
                    in_array($status, ['invalid', 'canceled'], true), $status === 'expired',
                    $paidUsd, (string)($res['price_currency'] ?? 'USD'));
                return $status;
            }

            case 'nowpayments': {
                // THE API KEY CANNOT READ THE INVOICE.
                //
                // This asked /v1/payment/?invoiceId=... with the API key, and
                // that route answers 401 AUTH_REQUIRED - "Bearer JWT token is
                // required" - to an api-key-only caller. Confirmed against the
                // live API, not just the docs. So every re-check of a
                // NOWPayments order threw "Gateway error", which is what a
                // member saw when they pressed the status button on an order
                // that was in fact fine.
                //
                // /v1/payment/{payment_id} DOES accept the API key, and the IPN
                // carries that payment id - so it is stored when the first IPN
                // lands and used from then on. Before any IPN there is nothing
                // to poll: the invoice has not become a payment yet, so the
                // honest answer is the status we already hold, not an error.
                $ref = (string)($payment['gateway_ref'] ?? '');
                if ($ref === '') {
                    return (string)$payment['status'] === 'paid' ? 'finished' : 'waiting';
                }
                $res = self::http('https://api.nowpayments.io/v1/payment/' . urlencode($ref), 'GET', [
                    'x-api-key: ' . Database::setting('gw_nowpayments_api_key'),
                ]);
                $status = (string)($res['payment_status'] ?? 'waiting');
                self::apply($id, $payment, in_array($status, ['finished', 'confirmed'], true),
                    $status === 'failed', $status === 'expired');
                return $status;
            }

            case 'coinbase': {
                $res = self::http('https://api.commerce.coinbase.com/charges/' . urlencode($uuid), 'GET', [
                    'X-CC-Api-Key: ' . Database::setting('gw_coinbase_api_key'),
                    'X-CC-Version: 2018-03-22',
                ]);
                $timeline = (array)($res['data']['timeline'] ?? []);
                $status = strtoupper((string)(end($timeline)['status'] ?? 'NEW'));
                self::apply($id, $payment, in_array($status, ['COMPLETED', 'RESOLVED', 'CONFIRMED'], true),
                    $status === 'CANCELED', $status === 'EXPIRED');
                return $status;
            }

            case 'btcpay': {
                $host = self::btcpayHost();
                $store = Database::setting('gw_btcpay_store_id');
                $res = self::http("$host/api/v1/stores/$store/invoices/" . urlencode($uuid), 'GET', [
                    'Authorization: token ' . Database::setting('gw_btcpay_api_key'),
                ]);
                $status = (string)($res['status'] ?? 'New');
                self::apply($id, $payment, $status === 'Settled', $status === 'Invalid', $status === 'Expired');
                return $status;
            }
        }
        return 'unknown';
    }

    /**
     * Handle an incoming webhook for a gateway. Returns an HTTP status code
     * and echoes a short body; approves/updates the payment as appropriate.
     */
    public static function handleWebhook(string $gw): int
    {
        $raw = file_get_contents('php://input') ?: '';

        switch ($gw) {
            case 'coingate': {
                // Payload is untrusted - confirm by pulling the order from the API.
                //
                // CoinGate sends the callback as form-encoded OR as JSON, and
                // which one is a per-API-App setting in their dashboard - JSON
                // is the format they recommend. Only $_POST was read, so an app
                // configured the recommended way delivered a body PHP never
                // populates $_POST from: no order id, 404 back to CoinGate, and
                // a paid member left pending until they asked why. Read both.
                $orderId = (string)($_POST['order_id'] ?? '');
                if ($orderId === '') {
                    $body = json_decode($raw, true);
                    $orderId = is_array($body) ? (string)($body['order_id'] ?? '') : '';
                }
                $pid = Payments::orderIdFromRef($orderId);
                if ($pid === 0) {
                    return 404;
                }
                $payment = self::payment($pid);
                if ($payment && $payment['kind'] === 'coingate') {
                    try {
                        self::refreshStatus($payment);
                    } catch (\Throwable $e) {
                        return 500;
                    }
                }
                return 200;
            }

            case 'nowpayments': {
                $secret = Database::setting('gw_nowpayments_ipn_secret');
                $sig = (string)($_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '');
                $data = json_decode($raw, true);
                if ($secret === '' || !is_array($data)) {
                    return 400;
                }
                // Sorted at EVERY level, not just the top. NOWPayments signs
                // the payload with every key in order, and a shallow ksort
                // matches right up until they nest an object in it - at which
                // point every callback fails its signature and nobody is
                // credited, for a reason nothing on this site can explain.
                self::ksortDeep($data);
                $expected = hash_hmac('sha512', json_encode($data, JSON_UNESCAPED_SLASHES), $secret);
                if (!hash_equals($expected, $sig)) {
                    return 400;
                }
                if (($m1 = Payments::orderIdFromRef((string)($data['order_id'] ?? ''))) > 0) {
                    $m = [1 => $m1];
                    $status = (string)($data['payment_status'] ?? '');
                    // What actually landed, in the fiat the invoice was priced
                    // in. partially_paid is already not in the paid list, but
                    // 'finished' on a short payment is the case this catches.
                    $got = isset($data['actually_paid_at_fiat']) && (float)$data['actually_paid_at_fiat'] > 0
                        ? (float)$data['actually_paid_at_fiat'] : null;
                    $payment = self::payment((int)$m[1]);
                    if ($payment) {
                        // The one id their API key is allowed to look up. Kept
                        // so a later re-check has something to ask about.
                        $ref = (string)($data['payment_id'] ?? '');
                        if ($ref !== '' && $ref !== (string)($payment['gateway_ref'] ?? '')) {
                            Database::pdo()
                                ->prepare('UPDATE payments SET gateway_ref = ? WHERE id = ?')
                                ->execute([$ref, (int)$m[1]]);
                            $payment['gateway_ref'] = $ref;
                        }
                        self::apply((int)$m[1], $payment,
                            in_array($status, ['finished', 'confirmed'], true),
                            $status === 'failed', $status === 'expired',
                            $got, (string)($data['price_currency'] ?? 'USD'));
                    }
                }
                return 200;
            }

            case 'coinbase': {
                $secret = Database::setting('gw_coinbase_webhook_secret');
                $sig = (string)($_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '');
                if ($secret === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
                    return 400;
                }
                $event = json_decode($raw, true)['event'] ?? [];
                $orderId = (string)($event['data']['metadata']['order_id'] ?? '');
                if (($m1 = Payments::orderIdFromRef($orderId)) > 0) {
                    $m = [1 => $m1];
                    $type = (string)($event['type'] ?? '');
                    $payment = self::payment((int)$m[1]);
                    if ($payment) {
                        // charge:resolved is a merchant marking an underpaid
                        // charge as settled, so the priced figure is exactly
                        // what has to be compared here.
                        $local = $event['data']['pricing']['local'] ?? [];
                        self::apply((int)$m[1], $payment,
                            in_array($type, ['charge:confirmed', 'charge:resolved'], true),
                            $type === 'charge:failed', false,
                            isset($local['amount']) ? (float)$local['amount'] : null,
                            (string)($local['currency'] ?? 'USD'));
                    }
                }
                return 200;
            }

            case 'btcpay': {
                $secret = Database::setting('gw_btcpay_webhook_secret');
                $sig = (string)($_SERVER['HTTP_BTCPAY_SIG'] ?? '');
                // BTCPay sends "sha256=<hex>"; the official PHP client compares
                // the bare digest. Accept both rather than depend on which one
                // a given version sends.
                $mac = hash_hmac('sha256', $raw, $secret);
                if ($secret === ''
                    || (!hash_equals('sha256=' . $mac, $sig) && !hash_equals($mac, $sig))) {
                    return 400;
                }
                $data = json_decode($raw, true) ?: [];
                $type = (string)($data['type'] ?? '');

                // MATCH ON invoiceId, NOT ON METADATA.
                //
                // This looked the payment up by $data['metadata']['orderId'],
                // and a BTCPay webhook has no metadata in it. Their own
                // documented payload carries invoiceId and type, and their
                // example fetches the invoice over the API when it wants the
                // metadata - which is why the docs tell you to send an orderId
                // "which you can use to then link with BTCPay invoiceId".
                //
                // So the lookup never matched, the handler returned 200, and a
                // buyer who paid through BTCPay never had premium switched on.
                // The invoice id is already stored on the payment row at
                // creation time, so the link was there all along and this was
                // reading the wrong end of it.
                $payment = null;
                $id = 0;
                $invoiceId = (string)($data['invoiceId'] ?? '');
                if ($invoiceId !== '') {
                    $payment = self::paymentByUuid('btcpay', $invoiceId);
                    $id = (int)($payment['id'] ?? 0);
                }
                // Metadata is still honoured when present - a future version
                // including it should not stop working.
                if (!$payment && ($m1 = Payments::orderIdFromRef((string)($data['metadata']['orderId'] ?? ''))) > 0) {
                    $id = $m1;
                    $payment = self::payment($id);
                }
                if ($payment) {
                    self::apply($id, $payment,
                        $type === 'InvoiceSettled',
                        $type === 'InvoiceInvalid', $type === 'InvoiceExpired');
                }
                return 200;
            }
        }
        return 404;
    }

    // ------------------------------------------------------------ helpers

    /**
     * The payment a gateway's own reference belongs to.
     *
     * Every gateway is handed an order_id it echoes back, except BTCPay, whose
     * webhook carries only its invoiceId - so that id, stored when the invoice
     * was created, is the link. Scoped by kind so two gateways cannot collide
     * on a reference that happens to match.
     */
    private static function paymentByUuid(string $kind, string $uuid): ?array
    {
        if ($uuid === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payments WHERE kind = ? AND gateway_uuid = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$kind, $uuid]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    /**
     * BTCPay is the only gateway whose address the operator types, which makes
     * it the only one that can be aimed at this server's own network. A typo -
     * or a hostile settings write - would otherwise turn checkout into a
     * request-forgery tool pointed at 127.0.0.1 or a cloud metadata address.
     *
     * A self-hosted BTCPay ON the same private network is a real deployment,
     * though, so this is a default and not a law: Admin > Billing has a tick
     * for it. Refusing outright would have broken installs that work today.
     */
    public static function btcpayHost(): string
    {
        $url = rtrim(Database::setting('gw_btcpay_host'), '/');
        if (Database::setting('gw_btcpay_allow_private', '0') === '1') {
            return $url;
        }
        $p = parse_url($url);
        $host = (string)($p['host'] ?? '');
        if (!in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException(
                'The BTCPay server address must be a full http(s) address, e.g. https://btcpay.yourdomain.com');
        }
        $bare = trim($host, '[]');
        $ips = filter_var($bare, FILTER_VALIDATE_IP) ? [$bare] : (gethostbynamel($bare) ?: []);
        if (!$ips) {
            throw new \RuntimeException('The BTCPay server address does not resolve: ' . $host);
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException(
                    'The BTCPay server address points inside this server\'s own network (' . $ip . '). '
                    . 'If your BTCPay really is on the local network, tick "BTCPay is on my private network" '
                    . 'under Admin > Billing.');
            }
        }
        return $url;
    }

    /** ksort, all the way down. */
    private static function ksortDeep(array &$a): void
    {
        ksort($a);
        foreach ($a as &$v) {
            if (is_array($v)) {
                self::ksortDeep($v);
            }
        }
        unset($v);
    }

    private static function payment(int $id): ?array
    {
        return Payments::find($id);
    }

    /**
     * $reportedUsd is what the gateway says arrived, where it says so. Null
     * means the gateway sent no figure - BTCPay's webhook carries none - and
     * the status word is then all there is to go on.
     */
    private static function apply(int $id, array $payment, bool $paid, bool $failed, bool $expired,
                                  ?float $reportedUsd = null, string $currency = 'USD'): void
    {
        if ($paid && $payment['status'] !== 'paid') {
            Payments::approveChecked($id, $payment, $reportedUsd, $currency);
        } elseif ($failed && $payment['status'] !== 'paid') {
            Payments::setStatus($id, 'failed');
        } elseif ($expired && $payment['status'] !== 'paid') {
            Payments::setStatus($id, 'expired');
        }
    }

    private static function http(string $url, string $method, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            // Same reasoning as the Cryptomus caller: a host that cannot reach
            // the gateway has to fail inside PHP's execution limit, or the
            // buyer sits on "Preparing checkout..." until something else kills
            // the request and no error is ever rendered.
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($res === false) {
            throw new \RuntimeException("Gateway unreachable: $err");
        }
        $data = json_decode($res, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Gateway returned invalid JSON (HTTP $code)");
        }
        if ($code >= 400) {
            $msg = $data['message'] ?? $data['reason'] ?? $data['error'] ?? json_encode($data);
            throw new \RuntimeException('Gateway error: ' . (is_string($msg) ? $msg : json_encode($msg)));
        }
        return $data;
    }
}
