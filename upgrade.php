<?php
declare(strict_types=1);

/**
 * Premium upgrade checkout:
 *   1. pick a plan (admin-defined durations & prices)
 *   2. pick a payment method:
 *      - automatic crypto via Cryptomus hosted checkout
 *      - manual methods: follow admin instructions, upload payment proof
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\Gateways;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\Payments;

MemberAuth::start();
$member = MemberAuth::current();

$pdo = Database::pdo();
$siteName = sma_setting('site_name', $config['app_name']);

// A STRANGER GETS TO SEE THE PRICE.
//
// This page used to redirect anyone not signed in straight to the register
// form. The head above it said, in a comment, "deliberately left indexable:
// this is the pricing page, which is a page people search for by name" - and
// the redirect meant no crawler ever saw it and no visitor ever saw a price
// before handing over an email address. Asking someone to open an account to
// find out what something costs is the oldest way there is to lose them, and
// it cost this site its only commercial landing page.
//
// So: no member, no checkout - but the plans, the prices and an honest account
// of what the money buys, ending at the account form rather than starting
// there. Everything below this block still requires a member, exactly as
// before; nothing about the purchase flow changes.
if (!$member) {
    require __DIR__ . '/src/pricing-public.php';
    exit;
}
$error = null;

// siteUrl(), not baseUrl(): the gateway is being told where to call THIS
// SITE back, and baseUrl() answers with the directory of the running script.
$base = \SignalMasterAi\Request::siteUrl();

/**
 * Send the buyer to the gateway - WITHOUT a redirect out of a form POST.
 *
 * THE BUG THIS EXISTS FOR. Checkout used to answer the POST with a 302 to the
 * gateway's own domain, and this site sends "form-action 'self'" in its
 * Content-Security-Policy. A browser applies form-action to the whole
 * navigation a form starts, redirects included - so Chrome refused the
 * submission, aborted it, and left the page exactly as it was with the button
 * still reading "Preparing checkout...". Nothing timed out and nothing was
 * logged, because nothing had failed: the invoice was created, the payment row
 * was written, and the only thing that never happened was the buyer arriving.
 * Reproduced in Chromium; the console said, in full:
 *
 *   Refused to send form data to '.../upgrade.php' because it violates the
 *   following Content Security Policy directive: "form-action 'self'".
 *
 * The POST now ends on this page, which is same-origin and therefore allowed,
 * and the trip to the gateway is an ordinary top-level navigation that
 * form-action does not govern. The visible link is not decoration: if anything
 * ever blocks the automatic hop again, the buyer can still complete the
 * payment by tapping it, instead of staring at a button.
 */
function sma_gateway_handoff(string $url, string $where): void
{
    $safe = sma_url($url);
    if ($safe === '') {
        return;                     // caller falls through to its error path
    }
    $name = sma_e($where);
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // This used to hand-roll its own tiny stylesheet - system-ui instead of
    // Inter Tight, five hardcoded hex colours standing in for the real dark
    // theme - because it leaves the app for a third-party gateway and never
    // seemed worth wiring into the shared one. Chrome::fonts()/Chrome::css()
    // are a few lines away, the same ones every other DB-free screen draws
    // from (install.php, the admin 503, the "not installed" holding page);
    // using them here means even a page on screen for two seconds before a
    // JS redirect matches the rest of the site instead of approximating it.
    // Doctype/viewport/title stay as this page's own literal text rather
    // than going through Chrome::head(), the same split install.php uses.
    // The spinner and its keyframes are this page's own - Chrome::css() has
    // no loading indicator - so they stay, inline after it.
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>Taking you to ' . $name . '</title>'
       . \SignalMasterAi\Chrome::fonts() . '<style>' . \SignalMasterAi\Chrome::css()
       . '.sp{width:26px;height:26px;border:3px solid var(--surface2);border-top-color:var(--accent);'
       . 'border-radius:50%;margin:0 auto 14px;animation:s 0.9s linear infinite}'
       . '@keyframes s{to{transform:rotate(360deg)}}</style>'
       . '</head><body><div class="sheet"><main class="card"><div class="sp"></div>'
       . '<h2>Taking you to ' . $name . '</h2>'
       . '<p>Your order is created. Finish the payment on their page.<br>'
       . 'If nothing happens in a few seconds, tap the button.</p>'
       . '<a class="btn" href="' . sma_e($safe) . '" rel="noopener">Continue to payment</a>'
       // JSON_HEX_TAG, not JSON_UNESCAPED_SLASHES. This string is a URL a
       // third party put in its API response, and sma_url() only guarantees
       // the scheme - it permits '<' and '>', so a gateway answering with a
       // URL containing "</script>" would have closed this block and run
       // whatever followed. Hex-escaping the tag characters ends that, and the
       // href above is escaped as HTML for the same reason.
       . '</main></div><script' . sma_nonce() . '>location.replace('
       . json_encode($safe, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
       . ');</script>'
       . '</body></html>';
    exit;
}

$plans = $pdo->query('SELECT * FROM plans WHERE enabled = 1 ORDER BY sort, days')->fetchAll();
$methods = $pdo->query('SELECT * FROM payment_methods WHERE enabled = 1 ORDER BY sort, id')->fetchAll();
$gatewayOk = Payments::gatewayConfigured();
// Gateways::enabled(), not just "are there credentials". Cryptomus now has
// an on/off tick like the other four, and a tick that leaves the currency
// rows on the checkout is not a switch, it is a label.
$cryptoOn = Gateways::enabled('cryptomus');
$autoMethods = array_values(array_filter($methods, fn($m) => $m['kind'] === 'cryptomus' && $cryptoOn));
$manualMethods = array_values(array_filter($methods, fn($m) => $m['kind'] === 'manual'));
$extraGateways = Gateways::availableExtra();   // coingate / nowpayments / coinbase / btcpay

// ---- proof upload for an existing manual payment -------------------------
$payView = null;
if (isset($_GET['pay'])) {
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? AND member_id = ?');
    $stmt->execute([(int)$_GET['pay'], $member['id']]);
    $payView = $stmt->fetch() ?: null;
    $stmt->closeCursor();

    // Automatic payments: re-check the gateway whenever the member views a
    // pending order, so premium activates even if the webhook could not
    // reach this server (local installs, firewalled hosts...).
    if ($payView && $payView['kind'] !== 'manual'
        && $payView['status'] === 'pending' && $payView['gateway_uuid'] !== '') {
        // Another call out to a gateway, and the same rule: do not hold this
        // member's session lock while waiting for one. Refreshing a pending
        // order used to freeze their dashboard for as long as the gateway took
        // to answer. Resumed afterwards because the page below mints a CSRF
        // token that has to be saved.
        MemberAuth::releaseSession();
        try {
            Gateways::refreshStatus($payView);
            $stmt->execute([(int)$_GET['pay'], $member['id']]);
            $payView = $stmt->fetch() ?: null;
    $stmt->closeCursor();
        } catch (Throwable $e) {
            // gateway unreachable - keep showing the stored status
        }
        MemberAuth::resumeSession();
    }
}

// A hosted invoice does not live for ever, and this page was happy to keep
// offering one long after it had. Cryptomus keeps an invoice payable for
// Payments::INVOICE_LIFETIME; past that, "Open crypto checkout" is a button to
// a dead page, which is indistinguishable to the buyer from the gateway being
// broken - and it was the same hour-long window in which the old order was
// also being handed back by findReusable().
//
// Computed HERE, not beside the branch that uses it: in PHP's alternative
// syntax an assignment written between two branches belongs to the body of the
// first one, so it never ran and the flag read as null - which is exactly how
// the first attempt at this fix failed its own test.
$gwStale = $payView !== null
           && (int)($payView['created_at'] ?? 0) < time() - Payments::INVOICE_LIFETIME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!MemberAuth::verifyCsrf()) {
        $error = 'Session expired - please try again.';
    } elseif (($_POST['act'] ?? '') === 'redeem') {
        // One call, and whatever it says is what the member reads. The
        // decision about which failures are worth explaining lives in Redeem,
        // not here and not twice - account.php asks the same question.
        [$rOk, $rMsg] = \SignalMasterAi\Redeem::apply((string)($_POST['key'] ?? ''), (int)$member['id']);
        if ($rOk) {
            // Reloaded rather than re-rendered: the member's tier changed
            // underneath this request, and every panel below reads it.
            header('Location: account.php?redeemed=1');
            exit;
        }
        $error = $rMsg;
    } elseif (($_POST['act'] ?? '') === 'proof' && $payView && $payView['kind'] === 'manual'
              && in_array($payView['status'], ['pending', 'awaiting_review'], true)) {
        // What the admin configured this method to ask the buyer for.
        $mStmt = $pdo->prepare('SELECT * FROM payment_methods WHERE name = ? LIMIT 1');
        $mStmt->execute([$payView['method_name']]);
        $mCfg = $mStmt->fetch() ?: [];
    $mStmt->closeCursor();
        $needImage = !isset($mCfg['ask_image']) || (int)$mCfg['ask_image'] === 1;
        $askText = trim((string)($mCfg['ask_text'] ?? ''));
        try {
            $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500);
            if ($askText !== '' && $note === '') {
                throw new RuntimeException('Please fill in: ' . $askText);
            }
            $name = (string)$payView['proof_image'];
            if (!empty($_FILES['proof']['name'])) {
                $name = Payments::storeProof((int)$payView['id'], $_FILES['proof'] ?? []);
            } elseif ($needImage && $name === '') {
                throw new RuntimeException('Please attach your payment screenshot / receipt image.');
            }
            if ($askText !== '' && $note !== '') {
                $note = $askText . ': ' . $note;
            }
            $pdo->prepare('UPDATE payments SET proof_image = ?, note = ?, status = ?, updated_at = ? WHERE id = ?')
                ->execute([$name, $note, 'awaiting_review', time(), $payView['id']]);
            header('Location: upgrade.php?pay=' . $payView['id'] . '&sent=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['act'] ?? '') === 'checkout') {
        $planId = (int)($_POST['plan'] ?? 0);
        $methodRaw = (string)($_POST['method'] ?? '');
        $plan = null;
        $method = null;
        foreach ($plans as $p) {
            if ((int)$p['id'] === $planId) { $plan = $p; }
        }
        // Method is either a payment_methods row id, or "gw:<gateway>" for
        // the extra automatic gateways.
        if (str_starts_with($methodRaw, 'gw:')) {
            $gwName = substr($methodRaw, 3);
            if (isset($extraGateways[$gwName])) {
                $method = ['id' => 0, 'name' => $extraGateways[$gwName], 'kind' => $gwName, 'currency' => ''];
            }
        } else {
            // Matched against the FILTERED lists ($autoMethods/$manualMethods),
            // not the raw $methods query - $methods is every enabled row
            // regardless of $cryptoOn, so an operator who switches Cryptomus
            // off (Gateways::enabled('cryptomus') -> false) without deleting
            // its payment_methods row left that row's id still matchable
            // here. A member who knew or guessed that small integer id could
            // POST it directly and this would still call the live Cryptomus
            // API and hand them a real invoice - completely bypassing the
            // toggle that was supposed to turn the gateway off.
            foreach (array_merge($autoMethods, $manualMethods) as $m) {
                if ((int)$m['id'] === (int)$methodRaw) { $method = $m; }
            }
        }
        // Optional coupon: discount is applied to a copy of the plan so every
        // downstream amount (record + gateway invoice) matches automatically.
        $couponCode = '';
        $couponRow = null;
        $rawCoupon = strtoupper(trim((string)($_POST['coupon'] ?? '')));
        if ($rawCoupon !== '') {
            $couponRow = Payments::validCoupon($rawCoupon);
            if ($couponRow === null) {
                $error = 'That coupon code is not valid (unknown, expired or fully used).';
            } else {
                $couponCode = (string)$couponRow['code'];
            }
        }
        if ($error === null && (!$plan || !$method)) {
            $error = 'Please choose a plan and a payment method.';
        }
        if ($error === null) {
            if ($couponRow !== null) {
                $plan['price_usd'] = round(
                    (float)$plan['price_usd'] * (1 - (int)$couponRow['percent_off'] / 100), 2);
            }
            // NOTHING TO CHARGE MEANS NOTHING TO SEND TO A GATEWAY.
            //
            // The discount was floored at one cent, so a 100% coupon - which
            // the coupon form accepts, it caps at 100 - sent a $0.01 invoice to
            // a crypto gateway. Every one of them has a minimum above that, so
            // the gateway refused it and the member was shown "Checkout failed"
            // for a code the operator created to give access away. The same
            // happens to a plan priced at zero.
            //
            // A free order is not a payment: the record is written at 0.00 and
            // approved on the spot, which is the path activation keys already
            // take, and approve() counts the coupon use and refuses to pay
            // referral credit on a zero amount.
            if ($error === null && (float)$plan['price_usd'] <= 0) {
                // THE COUPON IS TAKEN BEFORE ANYTHING IS GRANTED.
                //
                // A free order is validated and granted inside one request, so
                // the gap between "is this code still usable" and "count the
                // use" is a race anybody can run: fire N checkouts at a
                // hundred-percent-off single-use code and every one of them
                // reads used_count = 0 and every one of them is granted
                // premium. Claiming first makes the database the arbiter -
                // exactly one caller wins - and a loser is told the truth
                // rather than handed an account.
                //
                // Paid orders keep counting the use at approval, where it
                // belongs: counting here would spend a use on every abandoned
                // checkout.
                $claimed = false;
                if ($couponCode !== '') {
                    $claimed = Payments::claimCouponUse($couponCode);
                    if (!$claimed) {
                        $error = 'That coupon has just been fully used.';
                    }
                }
                if ($error === null) {
                    $plan['price_usd'] = 0.0;
                    $freeId = Payments::createRecord($member, $plan, $method);
                    if ($couponCode !== '') {
                        $pdo->prepare('UPDATE payments SET coupon = ? WHERE id = ?')
                            ->execute([$couponCode, $freeId]);
                    }
                    Payments::approve($freeId, $claimed);
                    header('Location: account.php?paid=1');
                    exit;
                }
            }
            // Idempotent checkout: a recent identical pending order is reused
            // instead of creating a duplicate invoice at the gateway.
            $existing = Payments::findReusable((int)$member['id'], (int)$plan['id'],
                (string)$method['kind'], (string)$method['name'], $couponCode);
            if ($existing) {
                // Only ever redirect to an http(s) address. This value is
                // whatever the gateway's API put in its response, and sending
                // a member wherever a third party's JSON says is not something
                // to do on trust - least of all from a page about to take
                // their money.
                $reuse = $existing['kind'] !== 'manual'
                    ? sma_url((string)$existing['gateway_url']) : '';
                // ASK THE GATEWAY WHETHER IT IS STILL ALIVE.
                //
                // Being inside the reuse window is this side's opinion. The
                // gateway's opinion is the one the buyer meets, and a member
                // who lands on "Invoice has been expired - please create new
                // invoice" on a checkout they started ten seconds ago has been
                // sent there by us. One cheap call settles it, and anything the
                // gateway will not take any more falls through to a new
                // invoice instead of being handed over.
                if ($reuse !== '') {
                    // Outside the session lock, like every other call this page
                    // makes to a gateway. This check was added a moment ago and
                    // went straight back to holding the lock across a network
                    // round trip - the fault it was written next to.
                    MemberAuth::releaseSession();
                    try {
                        $state = strtolower(Gateways::refreshStatus($existing));
                        $dead = ['expired', 'cancel', 'canceled', 'cancelled', 'fail', 'failed',
                                 'invalid', 'system_fail', 'wrong_amount', 'expired_invalid'];
                        if (in_array($state, $dead, true)) {
                            // Retired, and the order forgotten, so the code
                            // below builds a fresh invoice rather than putting
                            // the buyer on an order page to press Try again.
                            Payments::setStatus((int)$existing['id'], 'expired');
                            $reuse = '';
                            $existing = null;
                        } elseif (in_array($state, ['paid', 'paid_over', 'finished', 'confirmed', 'settled'], true)) {
                            // It was already paid and the re-check has just
                            // credited it. Sending them to pay again would be
                            // the worst outcome of the three.
                            header('Location: account.php?paid=1');
                            exit;
                        }
                    } catch (Throwable $e) {
                        // Gateway unreachable: keep the old behaviour rather
                        // than block a checkout over a failed status call.
                    }
                }
                if ($existing && $reuse !== '') {
                    sma_gateway_handoff($reuse, \SignalMasterAi\Gateways::name((string)$existing['kind']));
                }
                if ($existing) {
                    header('Location: upgrade.php?pay=' . (int)$existing['id']);
                    exit;
                }
            }
            $paymentId = 0;
            try {
                $paymentId = Payments::createRecord($member, $plan, $method);
                // The other half of the same rule. Talking to a gateway takes
                // up to fifteen seconds on a bad day, and holding the session
                // lock for it freezes every other tab this member has open -
                // including the dashboard poll, which then queues and holds the
                // lock itself the moment this finishes. Nothing below writes to
                // the session; it redirects or throws.
                MemberAuth::releaseSession();
                if ($couponCode !== '') {
                    $pdo->prepare('UPDATE payments SET coupon = ? WHERE id = ?')
                        ->execute([$couponCode, $paymentId]);
                }
                if ($method['kind'] === 'cryptomus') {
                    $url = Payments::createCryptomusInvoice($paymentId, $plan, (string)$method['currency'], $base);
                    sma_gateway_handoff($url, Gateways::name('cryptomus'));
                    throw new RuntimeException('The gateway returned an address this site will not send you to.');
                }
                if ($method['kind'] !== 'manual') {
                    $url = Gateways::createInvoice($method['kind'], $paymentId, $plan, $base);
                    sma_gateway_handoff($url, Gateways::name((string)$method['kind']));
                    throw new RuntimeException('The gateway returned an address this site will not send you to.');
                }
                header('Location: upgrade.php?pay=' . $paymentId);
                exit;
            } catch (Throwable $e) {
                // The page is about to be rendered again, and rendering mints a
                // CSRF token that has to be saved.
                MemberAuth::resumeSession();
                // Don't leave a zombie "pending" order when the gateway call failed.
                if ($paymentId > 0) {
                    Payments::setStatus($paymentId, 'failed');
                }
                // AND TELL THE OPERATOR. A member who cannot pay is the one
                // failure on this site that costs money directly, and it was
                // shown to the buyer and to nobody else - the panel had no
                // record that anybody had ever tried and failed.
                \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::PAYMENT,
                    'Checkout failed: ' . $e->getMessage(),
                    (string)$method['name'] . ', member ' . (int)$member['id']);
                $error = $e->getMessage();
            }
        }
    }
}

$csrf = MemberAuth::csrfToken();
$fmtPrice = fn($v) => '$' . number_format((float)$v, 2);
?>
<?php
// HEAD THROUGH View::head(), like every other public page.
//
// This is the pricing page - the one people search for by name, link to and
// paste into a chat - and it was the only page still carrying a hand-written
// head with no meta description and no social tags at all. A link to it in
// Telegram or WhatsApp previewed as a bare URL, which for the page that takes
// the money is the worst place on the site to have that. Everything it was
// missing already existed one function away. The page CSS is handed through
// unchanged rather than retyped.
ob_start();
?>
.up-wrap { max-width: 720px; margin: 30px auto; padding: 0 16px; }
.up-box { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 26px; }
.up-box h1 { font-size: 20px; display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.up-box h1 img { width: 30px; height: 30px; border-radius: 8px; }
.up-box .sub { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
h2.sec { font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 10px; }
.plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.plan-grid label { cursor: pointer; }
.plan-grid input { display: none; }
.plan-card { border: 2px solid var(--border); border-radius: 10px; padding: 14px; text-align: center; background: var(--surface2); }
/* The chosen plan. #14263f is a dark navy written before the light theme
   existed, so in light mode the selected card went dark and kept dark ink -
   the plan name measured 1.2:1, which is invisible. A tint of the accent
   instead, which follows whichever theme is on. */
.plan-grid input:checked + .plan-card {
  border-color: var(--accent);
  background: color-mix(in srgb, var(--accent) 14%, var(--surface2));
}
.plan-card .nm { font-weight: 500; font-size: 15px; }
.plan-card .pr { font-size: 21px; font-weight: 500; color: var(--accent); margin: 6px 0 2px; }
/* --muted is a comment colour; on the tinted selected card it lands at
   4.16:1, just under. Mixed toward the text colour so it stays quiet and
   stays readable, in whichever theme. */
.plan-card .dy { font-size: 11px; color: color-mix(in srgb, var(--muted) 45%, var(--text)); }
.pm-list { display: flex; flex-direction: column; gap: 8px; }
.pm-list label { cursor: pointer; }
.pm-list input { display: none; }
.pm-row { display: flex; align-items: center; gap: 12px; border: 2px solid var(--border); border-radius: 10px; padding: 12px 14px; background: var(--surface2); font-size: 14px; }
/* Same hardcoded navy as the plan card had - see the note there. */
.pm-list input:checked + .pm-row {
  border-color: var(--accent);
  background: color-mix(in srgb, var(--accent) 14%, var(--surface2));
}
/* 11px floor. Ten-point uppercase on a dark background is decoration, and this
   tag carries real information - which payment methods clear instantly. */
.pm-row .tag { margin-left: auto; font-size: 11px; font-weight: 500; padding: 3px 10px; border-radius: 999px; }
/* Chips with their own dark backgrounds, tinted from the theme instead so
   they follow it into light mode rather than staying dark under dark ink. */
/* Ink mixed toward the body colour: the series green on its own green wash
   measured 3.68:1, because the wash lifts the ground the text stands on. */
.tag.auto { background: color-mix(in srgb, var(--up) 16%, var(--surface2));
            color: color-mix(in srgb, var(--up) 45%, var(--text)); }
.tag.manual { background: color-mix(in srgb, var(--warn-border) 18%, var(--surface2));
              color: color-mix(in srgb, var(--warn-text) 55%, var(--text)); }
.btn-big { width: 100%; margin-top: 20px; background: var(--accent); color: #fff; border: none; padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 500; cursor: pointer; }
.err { background: var(--bg-down); border: 1px solid var(--line-down); color: var(--on-down); padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
.okmsg { background: var(--bg-up); border: 1px solid var(--line-up); color: var(--up); padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
.instructions { background: var(--bg); border: 1px dashed var(--border); border-radius: 10px; padding: 14px; font-size: 14px; white-space: pre-wrap; word-break: break-word; line-height: 1.7; }
input[type=file], textarea.note { width: 100%; margin-top: 6px; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 13px; box-sizing: border-box; }
/* Standalone link on its own line, not a word inside a sentence, so it needs
   a real target - it measured 15px tall. Same treatment as account.php. */
.back { display: inline-block; margin-top: 16px; color: var(--muted); font-size: 13px;
  text-decoration: none; padding: 6px 4px; min-height: 24px; }
/* Activation key. The input is monospace and wide-tracked because what goes
   in it is 16 characters somebody is copying off a card, and a proportional
   font makes that job harder than it needs to be. min-width:0 on the input is
   what stops the flex row pushing the page sideways on a 375px screen. */
.key-box { border: 1px dashed var(--accent); border-radius: 10px; padding: 14px; margin-bottom: 18px; background: var(--surface2); }
.key-box label { display: block; font-size: 13px; color: var(--muted); line-height: 1.5; margin-bottom: 8px; }
.key-box strong { color: var(--text); }
.key-row { display: flex; gap: 8px; flex-wrap: wrap; }
.key-row input { flex: 1 1 190px; min-width: 0; padding: 11px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg); color: var(--text); font-size: 15px; text-transform: uppercase;
  font-family: var(--font-mono); letter-spacing: 1px; }
.key-row button { flex: 0 0 auto; background: var(--accent); color: #fff; border: none; padding: 12px 22px;
  border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
.status-line { font-size: 14px; margin: 8px 0; }
<?php
\SignalMasterAi\View::head('Pricing & premium plans', 'Unlock every tracked coin, instant alerts and the full trade plan on each signal. Monthly, yearly and lifetime plans, paid in crypto.', [
    'style' => ob_get_clean(),
]);
?>
<?php \SignalMasterAi\View::topbar(''); ?>
<div class="up-wrap">
  <div class="up-box">
    <h1><a href="index.php" class="brand-link" aria-label="Back to home"><img src="<?= sma_e(sma_logo()) ?>" alt=""></a> Upgrade to Premium</h1>
    <p class="sub">Signed in as <strong><?= sma_e($member['email']) ?></strong> &middot; unlocks all premium coins.</p>
    <?php if ($error): ?><div class="err"><?= sma_e($error) ?></div><?php endif; ?>
    <?php // Somebody who bought lifetime and lands back here by an old link or
          // a menu item should be told they already own it, not shown a price
          // list. The page is left standing rather than redirected: they may
          // have come to read what premium includes, or to redeem a key for
          // somebody else. ?>
    <?php $isLifetime = $member['tier'] === 'paid' && (int)$member['paid_until'] === 0; ?>
    <?php if ($isLifetime): ?>
      <div class="okmsg">You already have <strong>lifetime premium</strong> on this account &mdash;
        there is nothing here you need to buy.</div>
    <?php elseif ($member['tier'] === 'paid'): ?>
      <?php // A TIME-LIMITED PLAN IS NOT A STRANGER'S FIRST VISIT.
            //
            // Unlike the lifetime notice above, this does not stand in place
            // of the pitch below - a month or a year runs out, so buying more
            // is a real purchase and the plan list stays exactly as it was.
            // It just says what today's purchase would be extending, which
            // the page below never mentioned. current() already downgrades an
            // expired paid_until to 'free' before this runs, so this date is
            // always still ahead. ?>
      <div class="okmsg">You are on the <strong>Premium</strong> plan until
        <strong><?= sma_e(gmdate('M j, Y', (int)$member['paid_until'])) ?></strong> UTC.
        Purchasing below extends your current plan.</div>
    <?php endif; ?>

    <?php if ($payView): ?>
      <?php if (isset($_GET['sent'])): ?>
        <div class="okmsg">Proof received! Your payment is awaiting review - premium activates as soon as the admin approves it.</div>
      <?php endif; ?>
      <h2 class="sec">Order #<?= (int)$payView['id'] ?> &middot; <?= sma_e($payView['plan_name']) ?> &middot; <?= $fmtPrice($payView['amount_usd']) ?></h2>
      <p class="status-line">Status:
        <strong><?= sma_e(strtoupper(str_replace('_', ' ', $payView['status']))) ?></strong>
      </p>
      <?php if ($payView['status'] === 'rejected'): ?>
        <?php // A REJECTED ORDER IS NOT A PAYMENT PAGE.
              // This fell through to the manual branch and printed the bank
              // details again under the word REJECTED, with no reason and no
              // way forward - so the member's options were to pay twice into a
              // dead order or to email and ask. The operator's reason is shown
              // if they gave one, and the only button starts a fresh order.
        $reject = '';
        if (preg_match('/Rejected:\s*(.+)$/s', (string)$payView['note'], $rm)) {
            $reject = trim($rm[1]);
        }
        ?>
        <div class="okmsg" style="background:var(--bg-down);border-color:var(--line-down);color:var(--on-down)">
          <strong>This order was not accepted.</strong>
          <?= $reject !== ''
              ? '<br>' . sma_e($reject)
              : '<br>No reason was given. Contact us if you believe your payment went through.' ?>
        </div>
        <p><a class="btn-big" style="display:block;text-align:center;text-decoration:none"
              href="upgrade.php">Start a new order</a></p>
      <?php elseif ($payView['kind'] === 'manual'): ?>
        <?php
        $stmtM = $pdo->prepare('SELECT * FROM payment_methods WHERE name = ? LIMIT 1');
        $stmtM->execute([$payView['method_name']]);
        $mRow = $stmtM->fetch();
        $stmtM->closeCursor();
        ?>
        <h2 class="sec">How to pay - <?= sma_e($payView['method_name']) ?></h2>
        <?php if (!empty($mRow['image'])): ?>
          <p style="text-align:center;margin-bottom:10px">
            <img src="pm-image.php?id=<?= (int)$mRow['id'] ?>" alt="Payment QR / instructions"
                 style="max-width:min(280px,100%);border-radius:10px;border:1px solid var(--border)">
          </p>
        <?php endif; ?>
        <div class="instructions"><?= sma_e($mRow['details'] ?? 'Contact the site operator for payment details.') ?></div>
        <?php if (in_array($payView['status'], ['pending', 'awaiting_review'], true)): ?>
        <?php
        // Buyer submission fields, exactly as the admin configured them.
        $needImage = !isset($mRow['ask_image']) || (int)($mRow['ask_image'] ?? 1) === 1;
        $askText = trim((string)($mRow['ask_text'] ?? ''));
        // Show the stored answer without the "Question: " prefix when editing.
        $noteVal = $payView['note'];
        if ($askText !== '' && str_starts_with((string)$noteVal, $askText . ': ')) {
            $noteVal = substr((string)$noteVal, strlen($askText) + 2);
        }
        ?>
        <form method="post" action="upgrade.php?pay=<?= (int)$payView['id'] ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="proof">
          <h2 class="sec"><?= $payView['status'] === 'awaiting_review' ? 'Update your submission' : 'Confirm your payment' ?></h2>
          <?php if ($needImage): ?>
            <label style="display:block;font-size:13px;color:var(--muted);margin:8px 0 2px">Proof-of-payment image (screenshot / receipt)</label>
            <input type="file" name="proof" accept="image/*" <?= $payView['proof_image'] === '' ? 'required' : '' ?>>
          <?php endif; ?>
          <?php if ($askText !== ''): ?>
            <label style="display:block;font-size:13px;color:var(--muted);margin:10px 0 2px"><?= sma_e($askText) ?> <span style="color:var(--down)">*</span></label>
            <textarea class="note" name="note" rows="2" required placeholder="<?= sma_e($askText) ?>"><?= sma_e((string)$noteVal) ?></textarea>
          <?php else: ?>
            <textarea class="note" name="note" rows="2" placeholder="Optional note: transaction ID, sender name..."><?= sma_e((string)$noteVal) ?></textarea>
          <?php endif; ?>
          <button class="btn-big" type="submit"><?= $needImage ? 'Submit proof' : 'Submit' ?></button>
        </form>
        <?php elseif ($payView['status'] === 'paid'): ?>
          <div class="okmsg">Payment confirmed - premium is active on your account. Enjoy!</div>
        <?php endif; ?>
      <?php elseif ($payView['kind'] !== 'manual' && $payView['gateway_url'] !== ''
                    && $payView['status'] === 'pending' && !$gwStale): ?>
        <?php $gwHref = sma_href($payView['gateway_url']); ?>
        <?php if ($gwHref !== ''): ?>
        <p><a class="btn-big" style="display:block;text-align:center;text-decoration:none" href="<?= $gwHref ?>">Open crypto checkout</a></p>
        <?php else: ?>
        <p class="hint">The payment gateway did not return a usable checkout link. Start the payment
          again, or pick another method.</p>
        <?php endif; ?>
      <?php elseif ($payView['kind'] !== 'manual' && $payView['status'] === 'pending' && $gwStale): ?>
        <div class="okmsg" style="background:var(--bg-warn);border-color:#D8B26A66;color:#D8B26A">
          This checkout has expired &mdash; a crypto invoice stays payable for about an hour.
          Nothing has been charged.
        </div>
        <p><a class="btn-big" style="display:block;text-align:center;text-decoration:none"
              href="upgrade.php">Start a new payment</a></p>
      <?php elseif ($payView['kind'] !== 'manual' && $payView['status'] === 'pending'): ?>
        <?php // NEVER A DEAD END.
              //
              // An automatic order with no checkout link is an attempt that
              // died before the gateway answered - a timeout, a host that
              // cannot reach it, a closed tab. This page had no branch for it,
              // so it printed the order number, the word PENDING and a link
              // back to the account: no button, no reason, nothing to press.
              // Combined with the hour-long reuse of that same row it was a
              // member locked out of paying with no idea why. ?>
        <div class="okmsg" style="background:var(--bg-down);border-color:var(--line-down);color:var(--on-down)">
          This attempt never reached the payment page &mdash; it may have timed out on the way.
          Nothing has been charged.
        </div>
        <p><a class="btn-big" style="display:block;text-align:center;text-decoration:none"
              href="upgrade.php">Start the payment again</a></p>
      <?php elseif ($payView['status'] === 'failed' || $payView['status'] === 'expired'): ?>
        <?php // Same reasoning for the two statuses that end an order: they
              // were shown as a bare word with nothing to do next. ?>
        <div class="okmsg" style="background:var(--bg-down);border-color:var(--line-down);color:var(--on-down)">
          This order <?= $payView['status'] === 'expired' ? 'expired before it was paid' : 'did not go through' ?>.
          Nothing has been charged.
        </div>
        <p><a class="btn-big" style="display:block;text-align:center;text-decoration:none"
              href="upgrade.php">Try again</a></p>
      <?php endif; ?>
      <a class="back" href="account.php">&larr; Back to account</a>

    <?php else: ?>
      <?php // ACTIVATION KEY, above the price list.
            //
            // Above it, not below, and not folded into step 3 with the coupon
            // box. Somebody holding a key has already been paid for; making
            // them read a page of prices first to find out they do not have to
            // pay is how a gift arrives feeling like a sales pitch. It is also
            // its own form rather than a field in the checkout - a key does not
            // need a plan chosen or a payment method picked, and offering
            // those alongside it only invites a member to fill them in and
            // wonder which one they are about to be charged for.
            //
            // Hidden entirely when no redeemable key exists - see
            // Redeem::offered(). ?>
      <?php // And no key box either, for the same reason plus a costlier one:
            // a key applied to an account with no end date is a key spent on
            // nothing. It is consumed, its seat is used up, and the member's
            // access is exactly what it already was. ?>
      <?php if (\SignalMasterAi\Redeem::offered() && !$isLifetime): ?>
        <form method="post" action="upgrade.php" class="key-box">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="redeem">
          <label for="keyfield"><strong>Have an activation key?</strong> Type it here and premium
            switches on straight away &mdash; no payment needed.</label>
          <div class="key-row">
            <input id="keyfield" type="text" name="key" required autocomplete="off" spellcheck="false"
                   placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="24">
            <button type="submit">Activate</button>
          </div>
        </form>
      <?php endif; ?>
      <form method="post" action="upgrade.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="checkout">

        <?php
        // What the money buys, before the money is asked for.
        //
        // This page opened on "1 - Choose your plan": a price list with no
        // statement of what was being priced. Every reason to pay was real and
        // enforced somewhere in the code, and none of it had ever been
        // assembled into something a visitor could read.
        //
        // Derived, never written by hand - see Premium::benefits(). A list
        // maintained by memory eventually sells a feature that was switched
        // off, and doing that on the payment page is not a stale string, it is
        // a false claim about what money buys.
        $benefits = \SignalMasterAi\Premium::benefits();
        ?>
        <?php if ($benefits): ?>
        <div class="reveal">
          <h2 class="sec">What Premium gives you</h2>
          <ul class="perk-list">
            <?php foreach ($benefits as $b): ?>
              <li><span class="perk-ic"><?= $b['icon'] ?></span>
                <span><strong><?= sma_e($b['title']) ?></strong><br><?= sma_e($b['body']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p class="sub">Your free account keeps everything it already has &mdash; the signals, the
            verified record and the alerts. Premium adds the list above.</p>
        </div>
        <?php endif; ?>

        <?php // NOTHING TO SELL SOMEBODY WHO ALREADY OWNS IT OUTRIGHT.
              //
              // A lifetime account was told "You already have lifetime
              // premium" and then shown the price list, the payment methods
              // and a Continue to payment button underneath - which reads as
              // the sentence being wrong, or as a charge about to happen
              // anyway. A time-limited account still sees all of it: a month
              // or a year DOES run out, and extending it is a real purchase.
              //
              // The benefits above stay either way; a member who owns premium
              // is allowed to read what it includes. ?>
        <?php if ($isLifetime): ?>
          <p class="sub" style="margin-top:14px">Your access has no end date, so there is no plan to
            choose and nothing to pay. <a href="account.php" style="color:var(--accent)">Back to your
            account</a>.</p>
        <?php else: ?>
        <div class="reveal">
        <h2 class="sec"><?= $benefits ? 'Choose your plan' : '1 &middot; Choose your plan' ?></h2>
        <?php if (!$plans): ?><p class="sub">No plans available - the admin has not created any yet.</p><?php endif; ?>
        <div class="plan-grid">
          <?php foreach ($plans as $idx => $p): ?>
          <label>
            <input type="radio" name="plan" value="<?= (int)$p['id'] ?>" <?= $idx === 1 || count($plans) === 1 ? 'checked' : '' ?>>
            <div class="plan-card">
              <div class="nm"><?= sma_e($p['name']) ?></div>
              <div class="pr"><?= $fmtPrice($p['price_usd']) ?></div>
              <div class="dy"><?= (int)$p['days'] > 0
                  ? (int)$p['days'] . ' days of premium'
                  : 'Premium, no end date' ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        </div>

        <div class="reveal">
        <h2 class="sec">2 &middot; Choose payment method</h2>
        <div class="pm-list">
          <?php foreach ($autoMethods as $idx => $m): ?>
          <label>
            <input type="radio" name="method" value="<?= (int)$m['id'] ?>" <?= $idx === 0 ? 'checked' : '' ?>>
            <div class="pm-row">
              <span><?= sma_e($m['name']) ?></span>
              <span class="tag auto">AUTOMATIC &middot; INSTANT</span>
            </div>
          </label>
          <?php endforeach; ?>
          <?php $first = !$autoMethods; foreach ($extraGateways as $gwKey => $gwLabel): ?>
          <label>
            <input type="radio" name="method" value="gw:<?= sma_e($gwKey) ?>" <?= $first ? 'checked' : '' ?>>
            <div class="pm-row">
              <span><?= sma_e($gwLabel) ?></span>
              <span class="tag auto">AUTOMATIC &middot; INSTANT</span>
            </div>
          </label>
          <?php $first = false; endforeach; ?>
          <?php foreach ($manualMethods as $m): ?>
          <label>
            <input type="radio" name="method" value="<?= (int)$m['id'] ?>" <?= !$autoMethods ? 'checked' : '' ?>>
            <div class="pm-row">
              <span><?= sma_e($m['name']) ?></span>
              <span class="tag manual">MANUAL &middot; ADMIN REVIEW</span>
            </div>
          </label>
          <?php endforeach; ?>
          <?php if (!$autoMethods && !$extraGateways && !$manualMethods): ?>
            <p class="sub">No payment methods are configured yet - contact the site operator.</p>
          <?php endif; ?>
        </div>
        </div>

        <?php if ($plans && ($autoMethods || $extraGateways || $manualMethods)): ?>
        <div class="reveal">
        <h2 class="sec">3 &middot; Coupon code (optional)</h2>
        <input type="text" name="coupon" placeholder="e.g. LAUNCH20" autocomplete="off"
               style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:14px;box-sizing:border-box;text-transform:uppercase">
        <?php // The button says "Preparing checkout..." and then owns the
              // screen until the server answers. When the server cannot reach
              // the gateway that wait is the whole visible failure - no error,
              // no progress, nothing to press - so after twenty seconds it
              // stops pretending and says what is probably happening. The
              // request is left running: it may still succeed. ?>
        <button class="btn-big" type="submit"
                data-pay-submit data-busy-text="Preparing checkout…" data-slow-note="slowpay">
          Continue to payment</button>
        <p class="hint" id="slowpay" style="display:none;margin-top:10px">This is taking longer than
          usual. The payment gateway may not be reachable from this server &mdash; you can wait, or
          go back and choose another payment method.</p>
        </div>
        <?php endif; ?>
        <?php endif;   // $isLifetime - the whole purchase flow above ?>
      </form>
      <a class="back" href="account.php">&larr; Back to account</a>
    <?php endif; ?>
  </div>
</div>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
