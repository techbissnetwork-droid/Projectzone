<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Payments;

Auth::requireLogin();
$pdo = Database::pdo();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'gateway') {
        Database::setSetting('cryptomus_merchant_uuid', trim($_POST['merchant_uuid'] ?? ''));
        if (trim($_POST['api_key'] ?? '') !== '') {
            Database::setSetting('cryptomus_api_key', trim($_POST['api_key']));
        }
        // Extra gateways: enable flags + credentials (secrets keep old value
        // when the field is left blank).
        $fields = [
            'gw_cryptomus_enabled',
            'gw_coingate_enabled', 'gw_nowpayments_enabled', 'gw_coinbase_enabled', 'gw_btcpay_enabled',
            'gw_btcpay_allow_private',
            'gw_btcpay_host', 'gw_btcpay_store_id',
        ];
        foreach ($fields as $f) {
            // Ticks save as 1/0. An unticked box sends nothing at all, so it
            // has to be named here rather than read out of $_POST - a checkbox
            // treated as a text field saves the string "on" and never matches
            // the '1' every reader compares against.
            if (str_ends_with($f, '_enabled') || $f === 'gw_btcpay_allow_private') {
                Database::setSetting($f, isset($_POST[$f]) ? '1' : '0');
            } else {
                Database::setSetting($f, rtrim(trim((string)($_POST[$f] ?? '')), '/'));
            }
        }
        // Which gateways had working credentials before this save, so the
        // block below can tell "keys entered for the first time" from "keys
        // that were already there".
        $wasConfigured = [];
        foreach (['cryptomus', 'coingate', 'nowpayments', 'coinbase', 'btcpay'] as $gw) {
            $wasConfigured[$gw] = \SignalMasterAi\Gateways::configured($gw);
        }
        foreach (['gw_coingate_api_key', 'gw_nowpayments_api_key', 'gw_nowpayments_ipn_secret',
                  'gw_coinbase_api_key', 'gw_coinbase_webhook_secret',
                  'gw_btcpay_api_key', 'gw_btcpay_webhook_secret'] as $secret) {
            if (trim((string)($_POST[$secret] ?? '')) !== '') {
                Database::setSetting($secret, trim((string)$_POST[$secret]));
            }
        }
        // ENTERING AN API KEY IS THE DECISION TO USE THE GATEWAY.
        //
        // Two controls stood between an operator and a working checkout - the
        // credentials and a separate "Enabled at checkout" tick - and the
        // second one was invisible in the moment that mattered: somebody
        // pasting a freshly-issued API key has plainly decided to use that
        // processor, and had to notice a checkbox above the field to make it
        // count. Nothing anywhere said the key alone was not enough.
        //
        // Only on the transition from no-credentials to credentials. An
        // operator who deliberately unticks a configured gateway and saves
        // again is expressing the opposite decision, and this must not
        // overrule it.
        $switchedOn = [];
        foreach (['cryptomus', 'coingate', 'nowpayments', 'coinbase', 'btcpay'] as $gw) {
            if (!$wasConfigured[$gw] && \SignalMasterAi\Gateways::configured($gw)
                && Database::setting("gw_{$gw}_enabled") !== '1') {
                Database::setSetting("gw_{$gw}_enabled", '1');
                $switchedOn[] = \SignalMasterAi\Gateways::LABELS[$gw] ?? $gw;
            }
        }
        flash('Gateway settings saved.'
              . ($switchedOn ? ' ' . implode(' and ', $switchedOn)
                               . ' switched on at checkout - untick to stop offering it.' : ''));
        header('Location: billing.php#gateways');
        exit;
    }

    // "I am not using this one." Unticking stops it being offered; this
    // removes the keys as well, for an operator who pasted the wrong account's
    // credentials or is done with a processor. Asked for directly: there was
    // no way to clear a gateway once its fields had anything in them.
    // Answer "can this server pay through this gateway?" from the panel,
    // instead of finding out through a member watching "Preparing checkout..."
    // forever.
    if ($act === 'gw_test') {
        $gw = (string)($_POST['gw'] ?? '');
        if (isset(\SignalMasterAi\Gateways::LABELS[$gw])) {
            // Up to fifteen seconds of waiting on a gateway. Hand the session
            // lock back for it, or every other admin tab stops until it
            // answers - and take it back for the flash message.
            Auth::releaseSession();
            [$ok, $msg] = \SignalMasterAi\Gateways::test($gw);
            Auth::resumeSession();
            flash(\SignalMasterAi\Gateways::name($gw) . ($ok ? ' - OK. ' : ' - FAILED. ') . $msg);
        }
        header('Location: billing.php#gateways');
        exit;
    }

    if ($act === 'gw_clear') {
        $gw = (string)($_POST['gw'] ?? '');
        $keys = [
            'cryptomus'   => ['cryptomus_merchant_uuid', 'cryptomus_api_key'],
            'coingate'    => ['gw_coingate_api_key'],
            'nowpayments' => ['gw_nowpayments_api_key', 'gw_nowpayments_ipn_secret'],
            'coinbase'    => ['gw_coinbase_api_key', 'gw_coinbase_webhook_secret'],
            'btcpay'      => ['gw_btcpay_host', 'gw_btcpay_store_id', 'gw_btcpay_api_key', 'gw_btcpay_webhook_secret'],
        ];
        if (isset($keys[$gw])) {
            foreach ($keys[$gw] as $k) {
                Database::setSetting($k, '');
            }
            Database::setSetting("gw_{$gw}_enabled", '0');
            flash(\SignalMasterAi\Gateways::name($gw) . ' cleared - its keys are gone and it is no '
                  . 'longer offered at checkout.');
        }
        header('Location: billing.php#gateways');
        exit;
    }

    // THE COINS CRYPTOMUS ACCEPTS, MANAGED ON THE CRYPTOMUS CARD.
    //
    // These are payment_methods rows like any other - that is how the checkout
    // finds them and it has not changed - but asking an operator to create one
    // through "Add a payment method" was asking them to file a crypto currency
    // under a form about bank transfers, QR codes and proof-of-payment
    // screenshots. Two unrelated jobs sharing one form, and the wrong one was
    // the default. The row is still a row; the place you make it is now the
    // gateway it belongs to.
    if ($act === 'cm_coin_add') {
        $coin = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['coin'] ?? '')) ?? '');
        if ($coin === '' || strlen($coin) > 12) {
            $error = 'Enter a coin code, e.g. USDT.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM payment_methods WHERE kind = 'cryptomus' AND currency = ?");
            $dup->execute([$coin]);
            if ($existingId = (int)($dup->fetchColumn() ?: 0)) {
                // Already there and switched off is the common case - somebody
                // adding USDT twice means they want USDT on, not two of them.
                $pdo->prepare('UPDATE payment_methods SET enabled = 1 WHERE id = ?')->execute([$existingId]);
                flash($coin . ' was already listed - switched back on.');
            } else {
                $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort),0)+1 FROM payment_methods')->fetchColumn();
                $pdo->prepare("INSERT INTO payment_methods (name, kind, currency, details, sort, ask_image, ask_text)
                               VALUES (?, 'cryptomus', ?, '', ?, 1, '')")
                    ->execute(['Crypto (' . $coin . ')', $coin, $sort]);
                flash($coin . ' added - members can now pay in it through Cryptomus.');
            }
        }
        if ($error === null) {
            header('Location: billing.php#gateways');
            exit;
        }
    }

    if ($act === 'cm_coin_toggle' || $act === 'cm_coin_del') {
        $cid = (int)($_POST['coin_id'] ?? 0);
        $chk = $pdo->prepare("SELECT currency FROM payment_methods WHERE id = ? AND kind = 'cryptomus'");
        $chk->execute([$cid]);
        if ($cur = (string)($chk->fetchColumn() ?: '')) {
            if ($act === 'cm_coin_del') {
                $pdo->prepare('DELETE FROM payment_methods WHERE id = ?')->execute([$cid]);
                flash($cur . ' removed.');
            } else {
                $pdo->prepare('UPDATE payment_methods SET enabled = 1 - enabled WHERE id = ?')->execute([$cid]);
                flash($cur . ' switched.');
            }
        }
        header('Location: billing.php#gateways');
        exit;
    }

    if ($act === 'plan_add') {
        $name = trim($_POST['name'] ?? '');
        // 0 is allowed and means lifetime - the same thing it means in the
        // grant-by-hand form on Members. See Payments::planLength().
        $days = max(0, min(3650, (int)($_POST['days'] ?? 30)));
        $price = max(0.01, min(100000, (float)($_POST['price'] ?? 10)));
        if ($name === '' || mb_strlen($name) > 64) {
            $error = 'Plan name is required (max 64 chars).';
        } else {
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort),0)+1 FROM plans')->fetchColumn();
            $pdo->prepare('INSERT INTO plans (name, days, price_usd, sort) VALUES (?,?,?,?)')
                ->execute([$name, $days, $price, $sort]);
            flash("Plan \"$name\" added.");
            header('Location: billing.php#plans');
            exit;
        }
    }

    if ($act === 'plan_save') {
        foreach ((array)($_POST['pname'] ?? []) as $id => $name) {
            $id = (int)$id;
            $name = trim((string)$name) ?: 'Plan';
            $days = max(0, min(3650, (int)($_POST['pdays'][$id] ?? 30)));   // 0 = lifetime
            $price = max(0.01, min(100000, (float)($_POST['pprice'][$id] ?? 10)));
            $en = isset($_POST['pen'][$id]) ? 1 : 0;
            $pdo->prepare('UPDATE plans SET name = ?, days = ?, price_usd = ?, enabled = ? WHERE id = ?')
                ->execute([mb_substr($name, 0, 64), $days, $price, $en, $id]);
        }
        flash('Plans saved.');
        header('Location: billing.php#plans');
        exit;
    }

    if ($act === 'plan_delete') {
        $pdo->prepare('DELETE FROM plans WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Plan deleted.');
        header('Location: billing.php#plans');
        exit;
    }

    if ($act === 'method_add') {
        $name = trim($_POST['name'] ?? '');
        $kind = ($_POST['kind'] ?? 'manual') === 'cryptomus' ? 'cryptomus' : 'manual';
        $currency = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['currency'] ?? '') ?? '');
        $details = trim($_POST['details'] ?? '');
        $askImage = isset($_POST['ask_image']) ? 1 : 0;
        $askText = mb_substr(trim($_POST['ask_text'] ?? ''), 0, 120);
        if ($name === '' || mb_strlen($name) > 64) {
            $error = 'Method name is required (max 64 chars).';
        } elseif ($kind === 'cryptomus' && $currency === '') {
            $error = 'Automatic methods need a currency code (e.g. USDT).';
        } elseif ($kind === 'manual' && $details === '') {
            $error = 'Manual methods need payment instructions (address / number / link).';
        } elseif ($kind === 'manual' && !$askImage && $askText === '') {
            $error = 'Ask the buyer for at least one thing: a proof image, a custom answer, or both.';
        } else {
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort),0)+1 FROM payment_methods')->fetchColumn();
            $pdo->prepare('INSERT INTO payment_methods (name, kind, currency, details, sort, ask_image, ask_text) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $kind, $currency, $details, $sort,
                           $kind === 'manual' ? $askImage : 1, $kind === 'manual' ? $askText : '']);
            $newId = (int)$pdo->lastInsertId();
            // Optional QR / payment image supplied right at add time.
            try {
                if ($kind === 'manual' && !empty($_FILES['image']['name'])) {
                    $img = Payments::storeMethodImage($newId, $_FILES['image']);
                    $pdo->prepare('UPDATE payment_methods SET image = ? WHERE id = ?')->execute([$img, $newId]);
                }
                flash("Payment method \"$name\" added.");
                header('Location: billing.php#methods');
                exit;
            } catch (Throwable $e) {
                $error = 'Method added, but the image upload failed: ' . $e->getMessage();
            }
        }
    }

    if ($act === 'method_save') {
        $id = (int)($_POST['id'] ?? 0);
        $askImage = isset($_POST['ask_image']) ? 1 : 0;
        $askText = mb_substr(trim($_POST['ask_text'] ?? ''), 0, 120);
        $pdo->prepare('UPDATE payment_methods SET name = ?, currency = ?, details = ?, enabled = ?, ask_image = ?, ask_text = ? WHERE id = ?')
            ->execute([
                mb_substr(trim($_POST['name'] ?? '') ?: 'Method', 0, 64),
                strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['currency'] ?? '') ?? ''),
                trim($_POST['details'] ?? ''),
                isset($_POST['enabled']) ? 1 : 0,
                $askImage,
                $askText,
                $id,
            ]);
        // Image (QR code / payment graphic) shown with the instructions.
        $cur = $pdo->prepare('SELECT image FROM payment_methods WHERE id = ?');
        $cur->execute([$id]);
        $oldImage = (string)($cur->fetchColumn() ?: '');
        try {
            if (!empty($_FILES['image']['name'])) {
                $newName = Payments::storeMethodImage($id, $_FILES['image']);
                $pdo->prepare('UPDATE payment_methods SET image = ? WHERE id = ?')->execute([$newName, $id]);
                if ($oldImage !== '' && is_file(SMA_UPLOAD_DIR . '/' . $oldImage)) {
                    @unlink(SMA_UPLOAD_DIR . '/' . $oldImage);
                }
            } elseif (isset($_POST['remove_image']) && $oldImage !== '') {
                $pdo->prepare("UPDATE payment_methods SET image = '' WHERE id = ?")->execute([$id]);
                if (is_file(SMA_UPLOAD_DIR . '/' . $oldImage)) {
                    @unlink(SMA_UPLOAD_DIR . '/' . $oldImage);
                }
            }
        } catch (Throwable $e) {
            $error = 'Method saved, but the image upload failed: ' . $e->getMessage();
        }
        if ($error === null) {
            flash('Payment method saved.');
            header('Location: billing.php#methods');
            exit;
        }
    }

    if ($act === 'coupon_add') {
        $code = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $_POST['code'] ?? '') ?? '');
        $pct = max(1, min(100, (int)($_POST['percent'] ?? 10)));
        $maxUses = max(0, min(100000, (int)($_POST['max_uses'] ?? 0)));
        $days = max(0, min(3650, (int)($_POST['expires_days'] ?? 0)));
        if (strlen($code) < 2 || strlen($code) > 32) {
            $error = 'Coupon code must be 2-32 characters (letters, numbers, dashes).';
        } else {
            try {
                $pdo->prepare('INSERT INTO coupons (code, percent_off, max_uses, expires_at) VALUES (?,?,?,?)')
                    ->execute([$code, $pct, $maxUses, $days > 0 ? time() + $days * 86400 : 0]);
                flash("Coupon $code created ($pct% off).");
                header('Location: billing.php#coupons');
                exit;
            } catch (Throwable $e) {
                $error = 'That coupon code already exists.';
            }
        }
    }

    if ($act === 'coupon_toggle') {
        $pdo->prepare('UPDATE coupons SET enabled = 1 - enabled WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: billing.php#coupons');
        exit;
    }

    if ($act === 'coupon_delete') {
        $pdo->prepare('DELETE FROM coupons WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Coupon deleted.');
        header('Location: billing.php#coupons');
        exit;
    }

    if ($act === 'key_gen') {
        $count = max(1, min(500, (int)($_POST['count'] ?? 10)));
        $planId = (int)($_POST['plan'] ?? 0);
        $days = max(0, min(3650, (int)($_POST['days'] ?? 30)));   // 0 = lifetime key
        $planName = 'Activation key';
        if ($planId > 0) {
            // A key that names a plan takes that plan's length AT GENERATION
            // TIME and keeps it. Printed cards outlive plan edits, and a key
            // sold as 30 days must not quietly become 7 because the operator
            // repriced the plan afterwards.
            $ps = $pdo->prepare('SELECT name, days FROM plans WHERE id = ?');
            $ps->execute([$planId]);
            if ($p = $ps->fetch()) {
                $planName = (string)$p['name'];
                $days = (int)$p['days'];
            } else {
                $planId = 0;
            }
        }
        $expDays = max(0, min(3650, (int)($_POST['expires_days'] ?? 0)));
        $codes = \SignalMasterAi\Redeem::generate($count, [
            'plan_id'    => $planId,
            'plan_name'  => $planName,
            'days'       => $days,
            'max_uses'   => max(1, min(100000, (int)($_POST['max_uses'] ?? 1))),
            'expires_at' => $expDays > 0 ? time() + $expDays * 86400 : 0,
            'note'       => trim((string)($_POST['note'] ?? '')),
        ]);
        flash(count($codes) . ' key' . (count($codes) === 1 ? '' : 's') . ' generated.');
        // Straight to the new batch, because the only thing anybody wants
        // after generating keys is the list of keys.
        $b = '';
        if ($codes) {
            $b = (string)$pdo->query('SELECT batch FROM redeem_codes ORDER BY id DESC LIMIT 1')->fetchColumn();
        }
        header('Location: billing.php?batch=' . urlencode($b) . '#keys');
        exit;
    }

    if ($act === 'key_toggle') {
        $pdo->prepare('UPDATE redeem_codes SET enabled = 1 - enabled WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: billing.php?batch=' . urlencode((string)($_POST['batch'] ?? '')) . '#keys');
        exit;
    }

    if ($act === 'key_batch') {
        // Batch-wide actions. Cancelling only ever touches keys nobody has
        // redeemed yet - a key already spent is somebody's live subscription,
        // and disabling it here would not take their days back, it would just
        // make the record lie about what happened.
        $batch = (string)($_POST['batch'] ?? '');
        $what = (string)($_POST['what'] ?? '');
        if ($batch !== '' && $what === 'cancel') {
            $st = $pdo->prepare('UPDATE redeem_codes SET enabled = 0 WHERE batch = ? AND used_count = 0');
            $st->execute([$batch]);
            flash($st->rowCount() . ' unused key(s) cancelled.');
        } elseif ($batch !== '' && $what === 'delete') {
            $st = $pdo->prepare('DELETE FROM redeem_codes WHERE batch = ? AND used_count = 0');
            $st->execute([$batch]);
            flash($st->rowCount() . ' unused key(s) deleted.');
        }
        header('Location: billing.php#keys');
        exit;
    }

    if ($act === 'redeem_toggle') {
        Database::setSetting('redeem_enabled', isset($_POST['on']) ? '1' : '0');
        flash('Activation keys ' . (isset($_POST['on']) ? 'enabled' : 'disabled') . '.');
        header('Location: billing.php#keys');
        exit;
    }

    if ($act === 'method_delete') {
        $pdo->prepare('DELETE FROM payment_methods WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Payment method deleted.');
        header('Location: billing.php#methods');
        exit;
    }
}

$plans = $pdo->query('SELECT * FROM plans ORDER BY sort, days')->fetchAll();
$methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort, id')->fetchAll();
$csrf = Auth::csrfToken();
$editM = (int)($_GET['method'] ?? 0);
$gatewayOk = Payments::gatewayConfigured();

admin_header('Billing', 'billing',
    'What is sold, how it is paid for, and the codes that discount or replace a payment. Money that '
    . 'has actually arrived is on <a href="payments.php">Payments</a>.');
show_flash();
if ($error) {
    echo '<div class="error">' . sma_e($error) . '</div>';
}
?>
<?php // Five subjects, one at a time.
      //
      // They were stacked into a single four-thousand-pixel scroll, so
      // reaching the activation keys meant passing plans, methods, gateway
      // credentials and coupons every time - and the page gave no sign it had
      // sections at all. Same strip as Settings, deliberately: one arrangement
      // of this admin panel to learn instead of two.
      //
      // The order is the order the work happens in. You cannot sell without a
      // plan, cannot take money without a method, and the last two are things
      // you do once there is something to discount or give away. ?>
<?php admin_tabs([
    ['id' => 'plans', 'icon' => '', 'label' => 'Plans',
     'lede' => 'What a member can buy: a name, a length in days and a price. 0 days means lifetime.'],
    ['id' => 'methods', 'icon' => '', 'label' => 'Payment methods',
     'lede' => 'How a member actually pays. Manual methods show your instructions and wait for your approval; automatic ones run through a gateway and upgrade the member by themselves.'],
    ['id' => 'gateways', 'icon' => '', 'label' => 'Gateways',
     'lede' => 'Credentials for the checkout processors. Saving a gateway\'s keys starts it being offered at checkout; the badge says whether a member can pay through it today.'],
    ['id' => 'coupons', 'icon' => '', 'label' => 'Coupons',
     'lede' => 'Percentage discounts a buyer types at checkout. A use is only counted when the payment completes.'],
    ['id' => 'keys', 'icon' => '', 'label' => 'Activation keys',
     'lede' => 'Codes that turn on premium by themselves - no payment, no approval. A coupon discounts a purchase; a key replaces one.'],
]); ?>
<?php // Ledes are one line each now, and none of them repeats the section
      // description in the strip above - the same sentence twice, twelve
      // pixels apart, is not emphasis. What is left is the operational detail
      // the strip has no room for. ?>
<?php admin_panel('Plans & prices',
    'Unticking <strong>On</strong> hides a plan from checkout without deleting it or touching anyone already on it.', 'plans'); ?>
  <form method="post" action="billing.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="plan_save">
    <?php if (!$plans): ?>
      <?php admin_empty('No plans yet', 'Add one below. Until there is at least one enabled plan, the Upgrade page has nothing to sell.'); ?>
    <?php else: ?>
    <?php // stack: one card per plan on a phone. Five columns of inputs in a
          // sideways-scrolling table meant the price was off the right edge on
          // every phone, and an operator editing a price could not see which
          // plan they were editing. ?>
    <div class="tbl-scroll">
    <table class="grid stack">
      <tr class="head"><th>Name</th><th style="width:90px">Days <span class="hint" style="font-weight:400">0 = lifetime</span></th><th style="width:110px">Price (USD)</th><th style="width:50px">On</th><th style="width:70px">Del</th></tr>
      <?php foreach ($plans as $p): ?>
      <tr>
        <?php // Each cell named by its column and its plan. Four editable fields
              // per row, and a screen reader had nothing to distinguish the
              // price of the monthly plan from the price of the yearly one.
              // The plan's own name is the row label - and it is itself one of
              // the fields, so the name is read from the stored row rather than
              // from whatever is currently typed into the box. ?>
        <?php $pl = sma_e($p['name'] !== '' ? $p['name'] : 'plan ' . (int)$p['id']); ?>
        <td data-label="Name"><input type="text" name="pname[<?= (int)$p['id'] ?>]" aria-label="Name of <?= $pl ?>" value="<?= sma_e($p['name']) ?>" style="max-width:220px;padding:6px 8px"></td>
        <td data-label="Days (0 = lifetime)"><input class="inline" type="number" min="0" max="3650" aria-label="Days for <?= $pl ?>" name="pdays[<?= (int)$p['id'] ?>]" value="<?= (int)$p['days'] ?>"></td>
        <td data-label="Price USD"><input class="inline" type="number" step="0.01" min="0.01" aria-label="Price in USD for <?= $pl ?>" name="pprice[<?= (int)$p['id'] ?>]" value="<?= sma_e((string)$p['price_usd']) ?>" style="width:100px"></td>
        <td data-label="Shown at checkout" style="text-align:center"><input type="checkbox" name="pen[<?= (int)$p['id'] ?>]" aria-label="Show <?= $pl ?> at checkout" <?= $p['enabled'] ? 'checked' : '' ?>></td>
        <td class="act-cell">
          <button class="btn small danger" type="submit" name="act" value="plan_delete"
                  onclick="return this.form.elements.id.value = <?= (int)$p['id'] ?>, confirm('Delete this plan?')"
                  formnovalidate>Del</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
    <input type="hidden" name="id" value="0">
    <?php if ($plans): ?><p style="margin-top:12px"><button class="btn" type="submit">Save plans</button></p><?php endif; ?>
  </form>
  <details class="acc" style="margin-top:14px">
    <summary>Add a new plan</summary>
    <div class="acc-body">
      <form method="post" action="billing.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="plan_add">
        <div class="row2">
          <div><label>New plan name (e.g. "3 Months", "Lifetime")</label><input aria-label="New plan name (e.g. &quot;3 Months&quot;, &quot;Lifetime&quot;)" type="text" name="name"></div>
          <div><label>Duration in days &mdash; <strong>0 = lifetime</strong>, never expires</label>
            <input type="number" name="days" aria-label="Duration in days, 0 = lifetime" value="90" min="0" max="3650"></div>
          <div><label>Price USD</label><input aria-label="Price USD" type="number" name="price" step="0.01" value="59.99"></div>
        </div>
        <p style="margin-top:12px"><button class="btn gray" type="submit">Add plan</button></p>
      </form>
    </div>
  </details>
<?php admin_panel_end(); ?>

<?php admin_panel('Payment methods',
    'Manual payments wait for you on <a href="payments.php">Payments</a>. The automatic rows are the coins each gateway accepts - add and remove those on the gateway itself, under <a href="#gateways">Gateways</a>.', 'methods'); ?>
  <?php
  // What is actually reachable from the checkout, said here rather than left
  // for the operator to discover by opening the upgrade page as a member.
  //
  // An automatic method is hidden at checkout unless its gateway has
  // credentials. This table said ENABLED regardless, so a saved Cryptomus
  // method and a working one looked identical, and the only place the truth
  // appeared was the member-facing page saying there were no payment methods
  // at all.
  $blockedWhy = \SignalMasterAi\Gateways::blocked('cryptomus');
  $autoSaved = array_filter($methods, fn($m) => $m['kind'] === 'cryptomus' && (int)$m['enabled'] === 1);
  ?>
  <?php if ($autoSaved && $blockedWhy !== null): ?>
    <div class="error" style="margin-bottom:14px">
      <strong><?= count($autoSaved) ?> automatic method<?= count($autoSaved) === 1 ? '' : 's' ?>
      saved here <?= count($autoSaved) === 1 ? 'is' : 'are' ?> not reachable at checkout</strong>
      &mdash; <?= sma_e($blockedWhy) ?>. Members see &ldquo;no payment methods&rdquo; until that is
      filled in under <a href="#gateways">Automatic gateways</a> below.
    </div>
  <?php endif; ?>
  <?php if (!$methods): ?>
    <?php admin_empty('No payment methods yet', 'Add one below - a bank transfer, a wallet address, or an automatic gateway currency. Without one, a member reaching checkout has nothing to click.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid stack">
    <tr class="head"><th>Name</th><th>Type</th><th>Currency / details</th><th>Status</th><th style="width:150px">Actions</th></tr>
    <?php foreach ($methods as $m): ?>
    <tr>
      <td data-label="Name"><strong><?= sma_e($m['name']) ?></strong></td>
      <td data-label="Type"><span class="badge <?= $m['kind'] === 'cryptomus' ? 'on' : 'neutral' ?>"><?= $m['kind'] === 'cryptomus' ? 'AUTOMATIC' : 'MANUAL' ?></span></td>
      <td data-label="<?= $m['kind'] === 'cryptomus' ? 'Currency' : 'Details' ?>" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= $m['kind'] === 'cryptomus' ? sma_e($m['currency']) : sma_e(mb_strimwidth($m['details'], 0, 60, '…')) ?>
      </td>
      <?php // Three states, not two. "Enabled" only means the operator ticked
            // a box; whether a member can reach it is a different question and
            // it is the one that matters. ?>
      <?php $hidden = $m['kind'] === 'cryptomus' && (int)$m['enabled'] === 1 && $blockedWhy !== null; ?>
      <td data-label="Status"><span class="badge <?= $hidden ? 'neutral' : ($m['enabled'] ? 'on' : 'off') ?>"><?=
            $hidden ? 'NOT LIVE' : ($m['enabled'] ? 'ENABLED' : 'DISABLED') ?></span>
        <?php if ($hidden): ?>
          <span class="hint" style="display:block"><?= sma_e($blockedWhy) ?></span>
        <?php endif; ?></td>
      <td class="act-cell">
        <a class="btn small gray" href="billing.php?method=<?= (int)$m['id'] ?>#methods">Edit</a>
        <form class="inline-form" method="post" action="billing.php"
              onsubmit="return confirm('Delete method <?= sma_e($m['name']) ?>?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="method_delete">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn small danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>

  <?php if ($editM): foreach ($methods as $m): if ((int)$m['id'] === $editM): ?>
  <div style="border:1px dashed var(--border);border-radius:10px;padding:4px 16px 16px;margin-top:14px">
    <h2>Edit: <?= sma_e($m['name']) ?></h2>
    <form method="post" action="billing.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="method_save">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <div class="row2">
        <div><label>Name shown to members</label><input aria-label="Name shown to members" type="text" name="name" value="<?= sma_e($m['name']) ?>"></div>
        <?php if ($m['kind'] === 'cryptomus'): ?>
        <div><label>Currency code (USDT, BTC, ETH...)</label><input aria-label="Currency code (USDT, BTC, ETH...)" type="text" name="currency" value="<?= sma_e($m['currency']) ?>"></div>
        <?php else: ?>
        <input type="hidden" name="currency" value="">
        <?php endif; ?>
        <div><label>Enabled</label><input aria-label="Enabled" type="checkbox" name="enabled" <?= $m['enabled'] ? 'checked' : '' ?> style="width:auto"></div>
      </div>
      <?php if ($m['kind'] === 'manual'): ?>
      <label>Payment instructions shown to the member (wallet address / bank account / UPI number / payment link - anything)</label>
      <textarea name="details" rows="5"><?= sma_e($m['details']) ?></textarea>
      <p style="margin:14px 0 0;font-weight:600;font-size:13px">What must the buyer submit back?</p>
      <label class="chk"><input type="checkbox" name="ask_image" <?= !empty($m['ask_image']) ? 'checked' : '' ?>>
        Ask for a proof-of-payment image (screenshot / receipt)</label>
      <label>Ask a custom question - text box shown to the buyer (leave empty to skip)</label>
      <input aria-label="Ask a custom question - text box shown to the buyer (leave empty to skip)" type="text" name="ask_text" value="<?= sma_e((string)($m['ask_text'] ?? '')) ?>"
             placeholder="e.g. Transaction ID / Sender name / Reference number">
      <label>Image shown with the instructions (QR code, payment card... JPG/PNG/WEBP/GIF, max 5 MB)</label>
      <?php if ($m['image'] !== ''): ?>
        <p style="margin:6px 0">
          <img src="../pm-image.php?id=<?= (int)$m['id'] ?>" alt="" style="max-width:180px;max-height:180px;border-radius:8px;border:1px solid var(--border)"><br>
          <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;color:var(--text)">
            <input type="checkbox" name="remove_image" style="width:auto"> Remove current image
          </label>
        </p>
      <?php endif; ?>
      <input type="file" name="image" accept="image/*" style="max-width:520px">
      <?php else: ?>
      <input type="hidden" name="details" value="">
      <?php endif; ?>
      <p style="margin-top:12px">
        <button class="btn" type="submit">Save method</button>
        <a class="btn gray" href="billing.php">Cancel</a>
      </p>
    </form>
  </div>
  <?php endif; endforeach; endif; ?>

  <?php // id so the Cryptomus warning can link straight here rather than
        // saying "go to Payment methods and find the add form". ?>
  <details class="acc" style="margin-top:14px" id="addmethod" <?= !$methods ? 'open' : '' ?>>
    <summary>Add a manual payment method</summary>
    <div class="acc-body">
      <form method="post" action="billing.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="method_add">
        <div class="row2">
          <div><label>New method name</label><input aria-label="New method name" type="text" name="name" placeholder="Bank transfer / PayPal me / UPI"></div>
          <?php // This form is about bank transfers, wallet addresses, UPI
                // numbers and QR codes - things a human checks and approves.
                // It also carried an "Automatic (Cryptomus currency)" option,
                // which is not a payment method in that sense at all: it is
                // which coins one gateway accepts. Filing it here meant an
                // operator adding USDT had to find a form about proof-of-
                // payment screenshots and change its type first, and the
                // instructions elsewhere had to keep saying "add a payment
                // method above per coin". Coins now live on the Cryptomus card
                // under Gateways, where the credentials for them are.
                //
                // The kind field stays, fixed to manual, because method_add
                // still reads it and a form that silently relies on a default
                // is a form that breaks the day the default moves. ?>
          <input type="hidden" name="kind" value="manual">
        </div>
        <div id="detBox">
          <label>Payment instructions (address / account number / link + any steps)</label>
          <textarea aria-label="Payment instructions (address / account number / link + any steps)" name="details" rows="4" placeholder="Send $AMOUNT to wallet: Txxxxxx..."></textarea>
          <label>Image shown with the instructions - QR code, payment card... (optional, JPG/PNG/WEBP/GIF, max 5 MB)</label>
          <input type="file" name="image" accept="image/*" style="max-width:520px">
          <p style="margin:14px 0 0;font-weight:600;font-size:13px">What must the buyer submit back?</p>
          <label class="chk"><input type="checkbox" name="ask_image" checked>
            Ask for a proof-of-payment image (screenshot / receipt)</label>
          <label>Ask a custom question - text box shown to the buyer (leave empty to skip)</label>
          <input aria-label="Ask a custom question - text box shown to the buyer (leave empty to skip)" type="text" name="ask_text" placeholder="e.g. Transaction ID / Sender name / Reference number">
        </div>
        <p style="margin-top:12px"><button class="btn gray" type="submit">Add payment method</button></p>
      </form>
    </div>
  </details>
<?php admin_panel_end(); ?>

<?php admin_panel('Automatic gateways',
    'The tick beside each one switches a working gateway off without deleting its keys.', 'gateways'); ?>
  <p class="hint">The webhook each one calls is <code>payment_webhook.php</code>; the URL to paste
    into the processor's dashboard is shown beside its fields.</p>
  <?php
  // One badge, one question: can a member pay through this today?
  //
  // It used to read CONFIGURED / NOT CONFIGURED, which answers a different
  // question - credentials are on file - and an operator who has ticked
  // "Enabled at checkout" and saved no key reads CONFIGURED nowhere and
  // NOT CONFIGURED next to a ticked box, with no statement of what is
  // missing. The reason comes from Gateways::blocked() so this badge, the
  // warning above the methods table and the health check cannot disagree.
  $gwState = static function (string $gw): string {
      $why = \SignalMasterAi\Gateways::blocked($gw, false);
      return $why === null
          ? '<span class="badge on">LIVE AT CHECKOUT</span>'
          : '<span class="badge off">NOT LIVE</span> <span class="hint why">' . sma_e($why) . '</span>';
  };
  ?>
  <form method="post" action="billing.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="gateway">
    <?php // Which gateway a "Clear these credentials" button is about. The
          // buttons submit this same form with act=gw_clear, so the form needs
          // somewhere to carry the answer. ?>
    <input type="hidden" name="gw" value="">
    <input type="hidden" name="coin_id" value="0">

    <details class="acc" <?= $gatewayOk ? 'open' : '' ?>>
      <?php // "Configured" was the wrong question. An operator reads a badge to
            // find out whether members can pay, and credentials on file is only
            // half of that. ?>
      <summary>Cryptomus <?= $gwState('cryptomus') ?></summary>
      <div class="acc-body">
        <p class="hint">Create a free merchant account at cryptomus.com and paste your Merchant UUID
          and Payment API key. That is the whole setup: members get a Cryptomus button and choose
          their coin on Cryptomus's own page.</p>
        <?php // The same tick as the other four, and for the same reason: an
              // operator has to be able to say "not this one" without emptying
              // fields. This was the only gateway without it, so its warning
              // could not be answered - only obeyed. ?>
        <label class="chk"><input type="checkbox" name="gw_cryptomus_enabled" <?= Database::setting('gw_cryptomus_enabled', '1') === '1' ? 'checked' : '' ?>> Offer this at checkout</label>
        <span class="hint" style="display:block;margin:-4px 0 8px">Untick if you are not using
          Cryptomus &mdash; the panel stops asking you to finish setting it up.</span>
        <div class="row2">
          <div><label>Merchant UUID</label>
            <input aria-label="Merchant UUID" type="text" name="merchant_uuid" value="<?= sma_e(Database::setting('cryptomus_merchant_uuid')) ?>"></div>
          <div><label>Payment API key <?= Database::setting('cryptomus_api_key') !== '' ? '(saved - leave blank to keep)' : '' ?></label>
            <input type="password" name="api_key" value="" autocomplete="new-password"></div>
        </div>
        <?php // WHY THIS ONE HAS NO "ENABLED AT CHECKOUT" TICK.
              //
              // The other four are single buttons: credentials plus a switch.
              // Cryptomus is per-currency, so its switch is the currency rows
              // themselves - enabling one is what puts a Cryptomus option in
              // front of a member. That is defensible but it was never
              // written down anywhere, so the obvious reading of this card was
              // "I filled in the keys, it is on", and an operator with correct
              // credentials and no currency row sees exactly what an operator
              // with no credentials sees: nothing.
              $cmRows = array_filter($methods, fn($m) => $m['kind'] === 'cryptomus' && (int)$m['enabled'] === 1);
              ?>
        <?php
        $cmAny = array_filter($methods, fn($m) => $m['kind'] === 'cryptomus');
        $cmOn = Database::setting('gw_cryptomus_enabled', '1') === '1';
        ?>
        <?php // THE COIN LIST, on the card it belongs to.
              //
              // One chip per coin, each one a payment_methods row. Click a chip
              // to switch that coin off without losing it; the cross removes
              // it. Adding is a box and a button, not a trip to another
              // section to pick the right option out of a dropdown about bank
              // transfers. ?>
        <?php // ONLY WHEN THERE IS SOMETHING TO MANAGE.
              //
              // Not shown at all on an install with no coin rows, which is
              // every install that has just entered its credentials. A
              // collapsed line still asks to be read and answered, and the
              // honest answer here is "there is nothing for you to do" - so
              // the line should not be there. Setup is the two fields and the
              // tick above; that is the whole card now.
              //
              // It appears the moment coin rows exist, so an install carrying
              // rows from an earlier build can see them, switch them off and
              // remove them - and can add more while it has any. An install
              // with none has no way in, which is deliberate: per-coin buttons
              // are a display preference the operator has said they do not
              // want offered, and the checkout is complete without them. ?>
        <?php if ($cmAny): ?>
        <details class="acc" style="margin-top:14px" open>
        <summary>Coins shown as their own buttons <span class="hint"
          style="display:inline;font-weight:400">optional &mdash; <?= $cmRows
            ? (int)count($cmRows) . ' on' : 'all off' ?></span></summary>
        <div class="acc-body">
        <p class="hint" style="margin:0 0 6px">You do not need any of these. With none switched on,
          members get one <strong>Cryptomus</strong> button and pick their coin on Cryptomus's own
          page &mdash; every coin your account supports. A coin listed here replaces that general
          button with one of its own.</p>
          <div class="coin-chips">
            <?php foreach ($cmAny as $c): ?>
              <span class="coin-chip<?= (int)$c['enabled'] === 1 ? ' on' : '' ?>">
                <button type="submit" name="act" value="cm_coin_toggle" formnovalidate
                        onclick="this.form.coin_id.value=<?= (int)$c['id'] ?>"
                        title="<?= (int)$c['enabled'] === 1 ? 'Switch this coin off' : 'Switch this coin back on' ?>"><?= sma_e((string)$c['currency']) ?></button>
                <button type="submit" name="act" value="cm_coin_del" class="x" formnovalidate
                        onclick="return this.form.coin_id.value=<?= (int)$c['id'] ?>, confirm('Remove <?= sma_e((string)$c['currency']) ?> from checkout?')"
                        title="Remove">&times;</button>
              </span>
            <?php endforeach; ?>
          </div>
          <p class="hint" style="margin:6px 0 0">Click a coin to switch it off at checkout;
            &times; removes it. <?= $cmRows
                ? '<strong>' . count($cmRows) . '</strong> on right now, shown instead of the general Cryptomus button.'
                : 'All off - members get the general Cryptomus button.' ?></p>
        <div class="key-add">
          <input type="text" name="coin" placeholder="USDT" maxlength="12" autocomplete="off"
                 style="text-transform:uppercase;max-width:150px">
          <button class="btn small gray" type="submit" name="act" value="cm_coin_add" formnovalidate>Add coin</button>
        </div>
        </div>
        </details>
        <?php endif; ?>

        <?php // Clearing is separate from unticking on purpose: one stops
              // offering it, the other forgets the account. An operator who
              // pasted the wrong merchant's credentials needs the second. ?>
        <p style="margin-top:10px"><button class="btn small" type="submit" name="act" value="gw_test" formnovalidate
                onclick="this.form.gw.value = 'cryptomus'; this.textContent = 'Testing…'">Test connection</button>
        <span class="hint">Calls the gateway from this server and reports what came back.</span></p>
        <p class="hint" style="margin-top:10px">Pasted the wrong account?
          <button class="btn small gray" type="submit" name="act" value="gw_clear" formnovalidate
                  onclick="return this.form.gw.value = 'cryptomus', confirm('Forget the Cryptomus merchant UUID and API key?')">Clear these credentials</button></p>
      </div>
    </details>

    <?php
    $siteUrl = Database::setting('site_url', 'https://your-site');
    $kept = fn(string $k) => Database::setting($k) !== '' ? '(saved - leave blank to keep)' : '';
    $gwOpen = fn(string $gw) => \SignalMasterAi\Gateways::configured($gw) ? 'open' : '';
    ?>
    <details class="acc" <?= $gwOpen('coingate') ?>>
    <summary>CoinGate <?= $gwState('coingate') ?></summary>
    <div class="acc-body">
    <div class="gw">
      <div class="gw-fields">
        <label class="chk"><input type="checkbox" name="gw_coingate_enabled" <?= Database::setting('gw_coingate_enabled') === '1' ? 'checked' : '' ?>> Offer this at checkout</label>
        <?php // Ticked for you the first time credentials are saved, so this is
              // a way to switch a working gateway OFF - not a second hoop
              // between an operator and a checkout that works. ?>
        <span class="hint" style="display:block;margin:-4px 0 8px">Ticks itself when you save the
          credentials below. Untick to stop offering it without deleting the keys.</span>
        <label>API token <?= $kept('gw_coingate_api_key') ?></label>
        <input type="password" name="gw_coingate_api_key" value="" autocomplete="new-password">
      </div>
      <div class="hook"><b>Webhook URL</b> Set this in your CoinGate order settings:
        <code><?= sma_e($siteUrl) ?>/payment_webhook.php?gw=coingate</code></div>
      <p style="margin-top:10px"><button class="btn small" type="submit" name="act" value="gw_test" formnovalidate
                onclick="this.form.gw.value = 'coingate'; this.textContent = 'Testing…'">Test connection</button>
        <span class="hint">Calls the gateway from this server and reports what came back.</span></p>
        <p class="hint" style="margin-top:8px">Pasted the wrong account?
        <button class="btn small gray" type="submit" name="act" value="gw_clear" formnovalidate
                onclick="return this.form.gw.value = 'coingate', confirm('Forget the CoinGate credentials?')">Clear these credentials</button></p>
    </div>
    </div>
    </details>

    <details class="acc" <?= $gwOpen('nowpayments') ?>>
    <summary>NOWPayments <?= $gwState('nowpayments') ?></summary>
    <div class="acc-body">
    <div class="gw">
      <div class="gw-fields">
        <label class="chk"><input type="checkbox" name="gw_nowpayments_enabled" <?= Database::setting('gw_nowpayments_enabled') === '1' ? 'checked' : '' ?>> Offer this at checkout</label>
        <?php // Ticked for you the first time credentials are saved, so this is
              // a way to switch a working gateway OFF - not a second hoop
              // between an operator and a checkout that works. ?>
        <span class="hint" style="display:block;margin:-4px 0 8px">Ticks itself when you save the
          credentials below. Untick to stop offering it without deleting the keys.</span>
        <label>API key <?= $kept('gw_nowpayments_api_key') ?></label>
        <input type="password" name="gw_nowpayments_api_key" value="" autocomplete="new-password">
        <label>IPN secret <?= $kept('gw_nowpayments_ipn_secret') ?></label>
        <input type="password" name="gw_nowpayments_ipn_secret" value="" autocomplete="new-password">
      </div>
      <div class="hook"><b>IPN callback URL</b> Set this in your NOWPayments dashboard:
        <code><?= sma_e($siteUrl) ?>/payment_webhook.php?gw=nowpayments</code></div>
      <p style="margin-top:10px"><button class="btn small" type="submit" name="act" value="gw_test" formnovalidate
                onclick="this.form.gw.value = 'nowpayments'; this.textContent = 'Testing…'">Test connection</button>
        <span class="hint">Calls the gateway from this server and reports what came back.</span></p>
        <p class="hint" style="margin-top:8px">Pasted the wrong account?
        <button class="btn small gray" type="submit" name="act" value="gw_clear" formnovalidate
                onclick="return this.form.gw.value = 'nowpayments', confirm('Forget the NOWPayments credentials?')">Clear these credentials</button></p>
    </div>
    </div>
    </details>

    <details class="acc" <?= $gwOpen('coinbase') ?>>
    <summary>Coinbase Commerce <?= $gwState('coinbase') ?></summary>
    <div class="acc-body">
    <div class="gw">
      <div class="gw-fields">
        <label class="chk"><input type="checkbox" name="gw_coinbase_enabled" <?= Database::setting('gw_coinbase_enabled') === '1' ? 'checked' : '' ?>> Offer this at checkout</label>
        <?php // Ticked for you the first time credentials are saved, so this is
              // a way to switch a working gateway OFF - not a second hoop
              // between an operator and a checkout that works. ?>
        <span class="hint" style="display:block;margin:-4px 0 8px">Ticks itself when you save the
          credentials below. Untick to stop offering it without deleting the keys.</span>
        <label>API key <?= $kept('gw_coinbase_api_key') ?></label>
        <input type="password" name="gw_coinbase_api_key" value="" autocomplete="new-password">
        <label>Webhook shared secret <?= $kept('gw_coinbase_webhook_secret') ?></label>
        <input type="password" name="gw_coinbase_webhook_secret" value="" autocomplete="new-password">
      </div>
      <div class="hook"><b>Webhook endpoint URL</b> Add this in Coinbase Commerce settings:
        <code><?= sma_e($siteUrl) ?>/payment_webhook.php?gw=coinbase</code></div>
      <p style="margin-top:10px"><button class="btn small" type="submit" name="act" value="gw_test" formnovalidate
                onclick="this.form.gw.value = 'coinbase'; this.textContent = 'Testing…'">Test connection</button>
        <span class="hint">Calls the gateway from this server and reports what came back.</span></p>
        <p class="hint" style="margin-top:8px">Pasted the wrong account?
        <button class="btn small gray" type="submit" name="act" value="gw_clear" formnovalidate
                onclick="return this.form.gw.value = 'coinbase', confirm('Forget the Coinbase Commerce credentials?')">Clear these credentials</button></p>
    </div>
    </div>
    </details>

    <details class="acc" <?= $gwOpen('btcpay') ?>>
    <summary>BTCPay Server <?= $gwState('btcpay') ?></summary>
    <div class="acc-body">
    <div class="gw">
      <div class="gw-fields">
        <label class="chk"><input type="checkbox" name="gw_btcpay_enabled" <?= Database::setting('gw_btcpay_enabled') === '1' ? 'checked' : '' ?>> Offer this at checkout</label>
        <?php // Ticked for you the first time credentials are saved, so this is
              // a way to switch a working gateway OFF - not a second hoop
              // between an operator and a checkout that works. ?>
        <span class="hint" style="display:block;margin:-4px 0 8px">Ticks itself when you save the
          credentials below. Untick to stop offering it without deleting the keys.</span>
        <label>Server URL</label>
        <input aria-label="Server URL" type="text" name="gw_btcpay_host" value="<?= sma_e(Database::setting('gw_btcpay_host')) ?>" placeholder="https://btcpay.yourdomain.com">
        <label>Store ID</label>
        <input aria-label="Store ID" type="text" name="gw_btcpay_store_id" value="<?= sma_e(Database::setting('gw_btcpay_store_id')) ?>">
        <?php // BTCPay is the only gateway whose address you type, so it is the
              // only one that could be aimed at this server's own network by a
              // typo. Addresses like 127.0.0.1 or 192.168.x.x are refused unless
              // you tick this - which self-hosting on a LAN legitimately needs. ?>
        <label class="chk"><input type="checkbox" name="gw_btcpay_allow_private"
          <?= Database::setting('gw_btcpay_allow_private') === '1' ? 'checked' : '' ?>>
          BTCPay is on my private network (allow a LAN or localhost address)</label>
        <label>Greenfield API key <?= $kept('gw_btcpay_api_key') ?></label>
        <input type="password" name="gw_btcpay_api_key" value="" autocomplete="new-password">
        <label>Webhook secret <?= $kept('gw_btcpay_webhook_secret') ?></label>
        <input type="password" name="gw_btcpay_webhook_secret" value="" autocomplete="new-password">
      </div>
      <div class="hook"><b>Webhook URL</b> Create this webhook in your BTCPay store settings:
        <code><?= sma_e($siteUrl) ?>/payment_webhook.php?gw=btcpay</code></div>
      <p style="margin-top:10px"><button class="btn small" type="submit" name="act" value="gw_test" formnovalidate
                onclick="this.form.gw.value = 'btcpay'; this.textContent = 'Testing…'">Test connection</button>
        <span class="hint">Calls the gateway from this server and reports what came back.</span></p>
        <p class="hint" style="margin-top:8px">Pasted the wrong account?
        <button class="btn small gray" type="submit" name="act" value="gw_clear" formnovalidate
                onclick="return this.form.gw.value = 'btcpay', confirm('Forget the BTCPay Server credentials?')">Clear these credentials</button></p>
    </div>
    </div>
    </details>

    <p style="margin-top:14px"><button class="btn" type="submit">Save gateway settings</button></p>
    <p class="hint">Payments also self-verify when the member returns from checkout, so premium
      activates even if a webhook can't reach this server.</p>
  </form>
<?php admin_panel_end(); ?>

<?php admin_panel('Coupons',
    'A code discounts a plan; it does not pay for one. To hand out premium with no payment at all, use <a href="#keys">Activation keys</a>.', 'coupons'); ?>
  <?php $coupons = $pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(); ?>
  <?php if (!$coupons): ?>
    <?php admin_empty('No coupons', 'Create one below if you want a launch or referral discount. Checkout works fine without any.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid stack">
    <tr class="head"><th>Code</th><th>Discount</th><th>Used</th><th>Expires</th><th>Status</th><th style="width:130px">Actions</th></tr>
    <?php foreach ($coupons as $c): ?>
    <tr>
      <td data-label="Code"><strong><?= sma_e($c['code']) ?></strong></td>
      <td data-label="Discount"><?= (int)$c['percent_off'] ?>% off</td>
      <td data-label="Used"><?= (int)$c['used_count'] ?><?= (int)$c['max_uses'] > 0 ? ' / ' . (int)$c['max_uses'] : '' ?></td>
      <td data-label="Expires"><?= (int)$c['expires_at'] > 0 ? gmdate('M j, Y', (int)$c['expires_at']) : 'never' ?></td>
      <td data-label="Status"><span class="badge <?= $c['enabled'] ? 'on' : 'off' ?>"><?= $c['enabled'] ? 'ACTIVE' : 'OFF' ?></span></td>
      <td class="act-cell">
        <form class="inline-form" method="post" action="billing.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="coupon_toggle">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn small gray" type="submit"><?= $c['enabled'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form class="inline-form" method="post" action="billing.php" onsubmit="return confirm('Delete coupon <?= sma_e($c['code']) ?>?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="coupon_delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn small danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
  <details class="acc" style="margin-top:12px" <?= !$coupons ? 'open' : '' ?>>
    <summary>Create a coupon</summary>
    <div class="acc-body">
      <form method="post" action="billing.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="coupon_add">
        <div class="row2">
          <div><label>Code (shown to buyers, e.g. LAUNCH20)</label><input aria-label="Code (shown to buyers, e.g. LAUNCH20)" type="text" name="code" style="text-transform:uppercase"></div>
          <div><label>Discount % (1-100)</label><input aria-label="Discount % (1-100)" type="number" name="percent" value="20" min="1" max="100"></div>
        </div>
        <div class="row2">
          <div><label>Max uses (0 = unlimited; a use counts only when the payment completes)</label>
            <input aria-label="Max uses (0 = unlimited; a use counts only when the payment completes)" type="number" name="max_uses" value="0" min="0"></div>
          <div><label>Expires in days (0 = never)</label><input aria-label="Expires in days (0 = never)" type="number" name="expires_days" value="0" min="0"></div>
        </div>
        <p style="margin-top:12px"><button class="btn gray" type="submit">Create coupon</button></p>
      </form>
    </div>
  </details>
<?php admin_panel_end(); ?>

<?php // ACTIVATION KEYS.
      //
      // Kept next to coupons because both are codes, and kept in a separate
      // panel because they are not the same thing and an operator who mixes
      // them up gives away a subscription instead of a discount. The one-line
      // description says the difference in the words that matter: a coupon
      // makes a payment cheaper, a key IS the payment.
      $keysOn = Database::setting('redeem_enabled', '1') === '1';
      $viewBatch = trim((string)($_GET['batch'] ?? ''));
      $batches = \SignalMasterAi\Redeem::batches(40);
      ?>
<?php admin_panel('Activation keys',
    'Generate them in advance and hand them out - by hand, in a reseller pack, or as a giveaway. Each one carries its own plan length.', 'keys'); ?>

  <form class="inline-form" method="post" action="billing.php" style="margin-bottom:14px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="redeem_toggle">
    <?php
    // WHY IS THERE NO KEY BOX ON MY UPGRADE PAGE?
    //
    // Because there is nothing to redeem: the member-facing box appears only
    // while at least one key is still usable, so a site that has never
    // generated any shows a coupon field and nothing else. That is right - a
    // box that can only ever answer "not recognised" is worse than no box -
    // but it was decided silently, and the operator looking for the feature
    // they just switched on had no way to find out. Now it says so, with the
    // number it is deciding from.
    $usableKeys = (int)$pdo->query(
        'SELECT COUNT(*) FROM redeem_codes WHERE enabled = 1 AND used_count < max_uses
           AND (expires_at = 0 OR expires_at > ' . time() . ')')->fetchColumn();
    ?>
    <?php if ($keysOn): ?>
      <span class="badge on">ACCEPTED</span>
      <span class="hint" style="margin:0 8px"><?= $usableKeys > 0
          ? 'Members see the key box on the upgrade and account pages &mdash; ' . $usableKeys
            . ' key' . ($usableKeys === 1 ? '' : 's') . ' still usable.'
          : 'No usable keys exist yet, so the key box is hidden from members. Generate some below and it appears.' ?></span>
      <button class="btn small gray" type="submit">Stop accepting keys</button>
    <?php else: ?>
      <span class="badge off">OFF</span>
      <span class="hint" style="margin:0 8px">Keys already handed out will be refused.</span>
      <input type="hidden" name="on" value="1">
      <button class="btn small" type="submit">Start accepting keys</button>
    <?php endif; ?>
  </form>

  <?php if ($viewBatch !== ''): ?>
    <?php
    $bStmt = $pdo->prepare('SELECT * FROM redeem_codes WHERE batch = ? ORDER BY id');
    $bStmt->execute([$viewBatch]);
    $bCodes = $bStmt->fetchAll();
    ?>
    <?php if ($bCodes): ?>
      <?php // The keys themselves, in one box, one per line.
            //
            // A table of fifty codes with a copy button on each is fifty
            // clicks; what an operator actually does with a batch is paste it
            // into a spreadsheet, a mail merge or a marketplace listing. The
            // used ones are marked in place rather than hidden, so the same
            // box still answers "which of these have gone?" later on. ?>
      <h3 style="font-size:14px;margin:0 0 6px">Batch <?= sma_e($viewBatch) ?>
        &middot; <?= sma_e((string)$bCodes[0]['plan_name']) ?>
        &middot; <?= \SignalMasterAi\Payments::planLength((int)$bCodes[0]['days']) ?> each</h3>
      <textarea id="keydump" readonly rows="<?= max(3, min(18, count($bCodes))) ?>"
                style="width:100%;box-sizing:border-box;font-family:var(--font-mono);font-size:13px;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"
      ><?php foreach ($bCodes as $c) {
            echo sma_e(\SignalMasterAi\Redeem::format((string)$c['code']))
               . ((int)$c['used_count'] > 0 ? '   (used)' : ((int)$c['enabled'] === 0 ? '   (cancelled)' : ''))
               . "\n";
        } ?></textarea>
      <p style="margin:8px 0 4px">
        <button class="btn small gray" type="button"
                onclick="var t=document.getElementById('keydump');t.select();document.execCommand('copy');this.textContent='Copied'">Copy all</button>
        <a class="btn small gray" href="billing.php#keys" style="text-decoration:none">Close</a>
      </p>
      <table class="grid stack" style="margin-top:10px">
        <tr class="head"><th>Key</th><th>Used</th><th>Expires</th><th>Status</th><th style="width:90px">Action</th></tr>
        <?php foreach ($bCodes as $c): ?>
        <tr>
          <td data-label="Key" style="font-family:var(--font-mono)"><?= sma_e(\SignalMasterAi\Redeem::format((string)$c['code'])) ?></td>
          <td data-label="Used"><?= (int)$c['used_count'] ?> / <?= (int)$c['max_uses'] ?></td>
          <td data-label="Expires"><?= (int)$c['expires_at'] > 0 ? gmdate('M j, Y', (int)$c['expires_at']) : 'never' ?></td>
          <td data-label="Status"><span class="badge <?= $c['enabled'] ? 'on' : 'off' ?>"><?= $c['enabled'] ? 'LIVE' : 'CANCELLED' ?></span></td>
          <td class="act-cell">
            <form class="inline-form" method="post" action="billing.php">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="act" value="key_toggle">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input type="hidden" name="batch" value="<?= sma_e($viewBatch) ?>">
              <button class="btn small gray" type="submit"><?= $c['enabled'] ? 'Cancel' : 'Restore' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <?php admin_empty('That batch is gone', 'It was deleted, or the link is stale.'); ?>
    <?php endif; ?>

  <?php elseif (!$batches): ?>
    <?php admin_empty('No keys yet', 'Generate a batch below when you need to sell, gift or make good a subscription without taking a payment through the site.'); ?>
  <?php else: ?>
    <?php // stack: on a phone each batch becomes its own labelled card rather
          // than a six-column table scrolled sideways with the actions off the
          // edge - the same treatment the payments table gets. ?>
    <div class="tbl-scroll">
    <table class="grid stack">
      <tr class="head"><th>Batch</th><th>Plan</th><th>Keys</th><th>Redeemed</th><th>Created</th><th style="width:190px">Actions</th></tr>
      <?php foreach ($batches as $b): ?>
      <tr>
        <?php // THE LABEL LEADS, THE ID FOLLOWS.
              //
              // "B260809014053EFB9" is a machine's name for a batch. The
              // operator's name for it is "Reseller A", which is what they
              // typed and what they will be looking for - so that goes first
              // in full weight and the id sits under it, small. The two are in
              // one wrapper rather than loose in the cell: on a phone each
              // loose child becomes another column in the row, which is how
              // the id ended up beside the label with both cut off. ?>
        <td data-label="Batch">
          <span class="cellwrap">
            <?php $bNote = trim((string)($b['note'] ?? '')); ?>
            <?php if ($bNote !== ''): ?><strong><?= sma_e($bNote) ?></strong><?php endif; ?>
            <span class="hint" style="font-family:var(--font-mono);margin:0"><?= sma_e((string)$b['batch']) ?></span>
          </span></td>
        <td data-label="Plan"><?= sma_e((string)$b['plan_name']) ?> &middot; <?= \SignalMasterAi\Payments::planLength((int)$b['days'], true) ?></td>
        <?php // "Left" is the number the two buttons below act on, so it goes
              // beside the total rather than being worked out by subtraction. ?>
        <td data-label="Keys"><span class="cellwrap"><strong><?= (int)$b['n'] ?></strong>
          <span class="hint" style="margin:0"><?= (int)$b['unused'] ?> unused</span></span></td>
        <td data-label="Redeemed"><?= (int)$b['used'] ?></td>
        <td data-label="Created"><?= (int)$b['created_at'] > 0 ? gmdate('M j, Y', (int)$b['created_at']) : '—' ?></td>
        <td class="act-cell">
          <a class="btn small" href="billing.php?batch=<?= urlencode((string)$b['batch']) ?>#keys" style="text-decoration:none">View keys</a>
          <?php // Offered only when there is something to cancel. Every batch
                // carried this button, including ones where every key had
                // already been redeemed - a control that cannot change
                // anything, on the row where it looks most alarming. ?>
          <?php if ((int)$b['unused'] > 0): ?>
          <form class="inline-form" method="post" action="billing.php"
                onsubmit="return confirm('Cancel the <?= (int)$b['unused'] ?> unused key(s) in this batch? Keys already redeemed keep working.')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="key_batch">
            <input type="hidden" name="what" value="cancel">
            <input type="hidden" name="batch" value="<?= sma_e((string)$b['batch']) ?>">
            <button class="btn small gray" type="submit">Cancel <?= (int)$b['unused'] ?> unused</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>

  <details class="acc" style="margin-top:12px" <?= !$batches ? 'open' : '' ?>>
    <summary>Generate keys</summary>
    <div class="acc-body">
      <form method="post" action="billing.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="key_gen">
        <div class="row2">
          <div><label>How many keys (1-500)</label><input aria-label="How many keys (1-500)" type="number" name="count" value="10" min="1" max="500"></div>
          <div><label>Plan the key activates</label>
            <select aria-label="Plan the key activates" name="plan">
              <option value="0">Custom length &mdash; use the days box</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= sma_e($p['name']) ?> (<?= \SignalMasterAi\Payments::planLength((int)$p['days']) ?>)</option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="row2">
          <div><label>Days of premium &mdash; used when the plan is "Custom". <strong>0 = a lifetime key</strong></label>
            <input type="number" name="days" value="30" min="0" max="3650"></div>
          <div><label>People per key (1 = single use, the normal case)</label>
            <input aria-label="People per key (1 = single use, the normal case)" type="number" name="max_uses" value="1" min="1" max="100000"></div>
        </div>
        <div class="row2">
          <div><label>Key expires in days (0 = never expires)</label>
            <input aria-label="Key expires in days (0 = never expires)" type="number" name="expires_days" value="0" min="0"></div>
          <div><label>Label for your own reference (e.g. "Reseller A", "March giveaway")</label>
            <input aria-label="Label for your own reference (e.g. &quot;Reseller A&quot;, &quot;March giveaway&quot;)" type="text" name="note" maxlength="190"></div>
        </div>
        <p class="hint" style="margin-top:10px">The plan's length is copied onto the keys as they are
          made, so editing the plan later never changes a key you already handed out. Expiry is about the
          <em>key</em> &mdash; how long someone has to redeem it &mdash; not about the premium it grants.</p>
        <p style="margin-top:12px"><button class="btn gray" type="submit">Generate keys</button></p>
      </form>
    </div>
  </details>

  <?php // Who used what. The row that answers a dispute, and the one place an
        // operator can see a key being shared around before the seats run out. ?>
  <?php
  $uses = $pdo->query(
      'SELECT u.created_at, c.code, c.days, m.email
         FROM redeem_uses u
         JOIN redeem_codes c ON c.id = u.code_id
         LEFT JOIN members m ON m.id = u.member_id
        ORDER BY u.id DESC LIMIT 15')->fetchAll();
  ?>
  <?php if ($uses): ?>
    <details class="acc" style="margin-top:10px">
      <summary>Recent redemptions <span class="hint">(<?= count($uses) ?>)</span></summary>
      <div class="acc-body">
        <div class="tbl-scroll">
        <table class="grid stack">
          <tr class="head"><th>When</th><th>Key</th><th>Member</th><th>Granted</th></tr>
          <?php foreach ($uses as $u): ?>
          <tr>
            <td data-label="When"><?= gmdate('M j, H:i', (int)$u['created_at']) ?></td>
            <td data-label="Key" style="font-family:var(--font-mono)"><?= sma_e(\SignalMasterAi\Redeem::format((string)$u['code'])) ?></td>
            <td data-label="Member"><?= sma_e((string)($u['email'] ?? 'deleted account')) ?></td>
            <td data-label="Granted"><?= \SignalMasterAi\Payments::planLength((int)$u['days']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
      </div>
    </details>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
