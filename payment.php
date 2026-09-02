<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

/**
 * Starts a gateway payment (POST from the order page) and handles the return
 * (GET from the gateway). Nothing here trusts the browser: every return is
 * re-checked against the provider's own API before an order is marked paid.
 */
$ref   = (string)($_POST['ref'] ?? $_GET['ref'] ?? '');
$token = (string)($_POST['t']   ?? $_GET['t']   ?? '');

$order = Database::one('SELECT o.*, p.title AS product_title FROM orders o
                        LEFT JOIN products p ON p.id = o.product_id
                        WHERE o.reference = :r', ['r' => $ref]);

if (!$order || $order['access_token'] === null || $token === ''
    || !hash_equals((string)$order['access_token'], $token)) {
    http_response_code(404);
    exit('That payment link is not valid.');
}

$method = $order['payment_method_id'] ? Payments::method((int)$order['payment_method_id']) : null;
$back   = Payments::orderUrl($order);

if (!$method || !Payments::isGateway((string)$method['provider'])) {
    Flash::err('This order is not paid online.');
    redirect($back);
}

/* ── already settled ─────────────────────────────────────── */
if (in_array($order['status'], ['paid', 'delivered'], true)) {
    redirect($back);
}

/* ── start: POST from the order page ─────────────────────── */
if (post()) {
    Csrf::check();
    $start = Payments::start($order, $method);
    if (!($start['ok'] ?? false)) {
        Flash::err($start['error'] ?? 'We could not start that payment.');
        redirect($back);
    }
    if (!empty($start['redirect'])) {
        header('Location: ' . $start['redirect']);
        exit;
    }
    /* Some gateways need a form POST rather than a redirect. */
    $form = $start['form'];
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><title>Redirecting to <?= e($method['name']) ?>…</title>
    <style>body{background:#06070A;color:#EEF1F6;font:15px/1.6 system-ui,sans-serif;display:grid;
      place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px}
      button{margin-top:16px;padding:12px 22px;border-radius:100px;border:0;background:#F2F5FA;
      color:#060810;font:500 15px system-ui;cursor:pointer}</style></head><body>
    <form method="post" action="<?= e($form['action']) ?>" id="go">
      <?php foreach ($form['fields'] as $k => $v): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
      <?php endforeach; ?>
      <p>Taking you to <?= e($method['name']) ?>…</p>
      <button type="submit">Continue</button>
    </form>
    <script>document.getElementById('go').submit();</script>
    </body></html><?php
    exit;
}

/* ── return: GET from the gateway ────────────────────────── */
$result = (string)($_GET['result'] ?? 'return');

if ($result === 'cancel' || $result === 'failure') {
    Payments::logAttempt($order, $method, 'cancelled', null, 'Buyer returned as ' . $result);
    Flash::err('That payment was not completed. You can try again below.');
    redirect($back);
}

$check = Payments::verify($order, $method, $_GET);
if ($check['ok'] ?? false) {
    Payments::markPaid($order, $method, (string)$check['reference']);
    Flash::ok('Payment confirmed. Thank you.');
} else {
    Flash::err($check['error'] ?? 'We could not confirm that payment yet. If money left your account, contact us with your reference and we will sort it out.');
}
redirect($back);
