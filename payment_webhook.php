<?php
declare(strict_types=1);

/**
 * Payment gateway webhook router.
 *   payment_webhook.php               -> Cryptomus (default, back-compat)
 *   payment_webhook.php?gw=coingate   -> CoinGate
 *   payment_webhook.php?gw=nowpayments-> NOWPayments IPN
 *   payment_webhook.php?gw=coinbase   -> Coinbase Commerce
 *   payment_webhook.php?gw=btcpay     -> BTCPay Server
 * Every handler verifies the gateway's signature (or re-pulls the order from
 * the gateway API) before touching a payment.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\ErrorLog;
use SignalMasterAi\Gateways;
use SignalMasterAi\Payments;

$gw = (string)($_GET['gw'] ?? 'cryptomus');

if ($gw !== 'cryptomus') {
    $code = Gateways::handleWebhook($gw);
    if ($code !== 200) {
        // A rejected callback is a member who paid and was not upgraded. It
        // answered the gateway and vanished; now it is on a list.
        ErrorLog::record(ErrorLog::PAYMENT, 'Webhook rejected (HTTP ' . $code . ')', 'gateway: ' . $gw);
    }
    http_response_code($code);
    exit($code === 200 ? 'ok' : 'rejected');
}

// ---- Cryptomus (signed JSON body) ------------------------------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data) || !Payments::verifyWebhook($data)) {
    // Two different failures, said differently, because the fixes are
    // different: a mismatched key is a configuration error, and an ABSENT key
    // means this endpoint is being called on a site that does not use this
    // gateway - which is either a stale webhook at the gateway or somebody
    // trying it on.
    $configured = \SignalMasterAi\Database::setting('cryptomus_api_key') !== '';
    ErrorLog::record(ErrorLog::PAYMENT,
        $configured
            ? 'Webhook rejected - signature did not verify'
            : 'Webhook rejected - this gateway has no API key configured, so no callback can be trusted',
        $configured
            ? 'gateway: cryptomus. Usually the payment API key in Admin > Plans & gateways '
              . 'does not match the one at the gateway.'
            : 'gateway: cryptomus. Nothing here can be credited until a key is set under '
              . 'Admin > Plans & gateways. If you do not use Cryptomus, this callback did not '
              . 'come from a payment you took.');
    http_response_code(400);
    exit('bad signature');
}

$orderId = (string)($data['order_id'] ?? '');
$status  = (string)($data['status'] ?? '');
$paymentId = Payments::orderIdFromRef($orderId);
if ($paymentId === 0) {
    http_response_code(404);
    exit('unknown order');
}

// Fetched once, up front, so every branch below can check what this payment
// already is before changing it - not just the 'paid' one. The other four
// gateways (Gateways::apply()) already guard their failed/expired branches
// with "...&& $payment['status'] !== 'paid'"; this one didn't, which meant a
// stale or out-of-order redelivery reporting cancel/fail/wrong_amount/expired
// for an order this endpoint had ALREADY marked paid would flip it back to
// unpaid - and the very next 'paid' event for that same order (a retry, or a
// member pressing "refresh status") would pass approve()'s own idempotency
// check again and credit the membership a second time for one real payment.
$p = Payments::find($paymentId);
if ($p === null) {
    http_response_code(404);
    exit('unknown order');
}

if (in_array($status, ['paid', 'paid_over'], true)) {
    // WHAT ARRIVED, NOT WHAT WAS ASKED FOR. A signature proves Cryptomus sent
    // the message; it does not prove the buyer sent the price. Cryptomus
    // reports the settled figure in USD, so it is compared before anything is
    // credited, and a short one is held for the operator instead of activating
    // premium. paid_over - they sent more - passes, as it should.
    Payments::approveChecked($paymentId, $p,
        isset($data['payment_amount_usd']) && (float)$data['payment_amount_usd'] > 0
            ? (float)$data['payment_amount_usd'] : null);
} elseif (in_array($status, ['cancel', 'fail', 'wrong_amount', 'system_fail'], true) && $p['status'] !== 'paid') {
    Payments::setStatus($paymentId, 'failed');
} elseif ($status === 'expired' && $p['status'] !== 'paid') {
    Payments::setStatus($paymentId, 'expired');
}

echo 'ok';
