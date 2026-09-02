<?php
declare(strict_types=1);

/** Member login / registration / account page. */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;

MemberAuth::start();

$siteName = sma_setting('site_name', $config['app_name']);
$regEnabled = sma_setting('registration_enabled', '1') === '1';
$error = null;
$notice = null;

// Where to send a signed-out visitor once they are in - e.g. portfolio.php
// sends them here with ?next=portfolio.php rather than to their dashboard.
// An allowlist of exact filenames, never a raw pass-through: this value ends
// up in a Location header, and honouring an arbitrary "next" is an open
// redirect. Read once, up front, so every success path below can use it;
// $_POST wins over $_GET so a hidden field carries it across the login form's
// own submit, which PHP does not do on its own.
$nextAllowed = ['charts.php', 'portfolio.php', 'account.php', 'backtest.php', 'performance.php', 'upgrade.php'];
$next = (string)($_POST['next'] ?? $_GET['next'] ?? '');
$next = in_array($next, $nextAllowed, true) ? $next : '';

if (isset($_GET['logout'])) {
    MemberAuth::logout();
    header('Location: account.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!MemberAuth::verifyCsrf()) {
        $error = 'Session expired - please try again.';
    } elseif (($_POST['act'] ?? '') === 'register' && $regEnabled) {
        // Registration damping, tracked per IP server-side (a bot dropping
        // its cookies cannot reset it): max 5 registrations / 10 minutes.
        $ipKey = 'regip:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
        if (\SignalMasterAi\Cache::increment($ipKey, 600) > 5) {
            $error = 'Too many registration attempts. Try again in a few minutes.';
        } else {
            [$ok, $msg, $newId, $needsCode] = MemberAuth::register(
                (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            // The address is already somebody's. Show the same screen with
            // the same words, create nothing, and tell the real owner that
            // someone tried to sign up as them - which is the one person who
            // should hear about it.
            if (MemberAuth::registerWasDecoy()) {
                unset($_SESSION['verify_member']);
                $_SESSION['verify_decoy'] = 1;
                $_SESSION['verify_decoy_email'] = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $decoyEmail = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $decoySent = \SignalMasterAi\MemberVerify::notifyExisting($decoyEmail);
                // Word for word what a real registration says, including when
                // the mailer is down - see MemberVerify::notifyExisting().
                $_SESSION[$decoySent ? 'verify_notice' : 'verify_error'] = $decoySent
                    ? \SignalMasterAi\MemberVerify::sentMessage($decoyEmail)
                    : \SignalMasterAi\MemberVerify::sendFailedMessage();
                header('Location: account.php?tab=verify');
                exit;
            }
            if ($needsCode && $newId > 0) {
                // Created (or already waiting) but not logged in: mail the
                // code and hand over to the verify step. The id lives in the
                // session, never in the URL - a verify link that carries an
                // account id is a link anyone can edit.
                $_SESSION['verify_member'] = $newId;
                unset($_SESSION['verify_decoy']);
                [$sent, $sendMsg] = \SignalMasterAi\MemberVerify::issue($newId);
                // A send that failed must not be reported in the green box -
                // "on its way" and "could not be sent" are not the same news.
                $_SESSION[$sent ? 'verify_notice' : 'verify_error'] = $sendMsg;
                // The trip to the verify tab is not the login itself, so next
                // has to ride the query string here or it is gone by the time
                // the code is actually entered.
                header('Location: account.php?tab=verify' . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
                exit;
            }
            if ($ok) {
                // Credit the referrer who sent this visitor, if any.
                \SignalMasterAi\Referrals::attach(MemberAuth::currentId());
                header('Location: ' . ($next !== '' ? $next : 'charts.php'));
                exit;
            }
            $error = $msg;
        }
    } elseif (($_POST['act'] ?? '') === 'verify_otp') {
        $pendingId = (int)($_SESSION['verify_member'] ?? 0);
        if ($pendingId <= 0 && !empty($_SESSION['verify_decoy'])) {
            // Decoy: there is no code and there is nothing to verify. It has
            // to fail the way a wrong code fails, or the difference between
            // the two screens answers the question the decoy exists to refuse.
            \SignalMasterAi\Cache::increment(
                'otpip:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16), 900);
            $error = 'That code is not right.';
        } elseif ($pendingId <= 0) {
            $error = 'That verification step has expired - please register or log in again.';
        } else {
            // Damped per IP as well as per account: the per-account counter in
            // MemberVerify stops guessing at one address, this stops a script
            // walking a list of them.
            $ipKey = 'otpip:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
            if (\SignalMasterAi\Cache::increment($ipKey, 900) > 30) {
                $error = 'Too many attempts. Try again in a few minutes.';
            } else {
                [$vOk, $vMsg] = \SignalMasterAi\MemberVerify::check($pendingId, (string)($_POST['code'] ?? ''));
                if ($vOk) {
                    unset($_SESSION['verify_member'], $_SESSION['verify_notice'],
                          $_SESSION['verify_error']);
                    MemberAuth::loginById($pendingId);
                    // Referral credit waits for verification, so a code nobody
                    // types never pays anyone for the introduction.
                    \SignalMasterAi\Referrals::attach($pendingId);
                    header('Location: ' . ($next !== '' ? $next : 'charts.php'));
                    exit;
                }
                $error = $vMsg;
            }
        }
    } elseif (($_POST['act'] ?? '') === 'resend_otp') {
        $pendingId = (int)($_SESSION['verify_member'] ?? 0);
        if ($pendingId <= 0 && !empty($_SESSION['verify_decoy'])) {
            $notice = \SignalMasterAi\MemberVerify::sentMessage(
                (string)($_SESSION['verify_decoy_email'] ?? ''));
        } elseif ($pendingId <= 0) {
            $error = 'That verification step has expired - please register or log in again.';
        } else {
            [$sOk, $sMsg] = \SignalMasterAi\MemberVerify::issue($pendingId);
            if ($sOk) {
                $notice = $sMsg;
            } else {
                $error = $sMsg;
            }
        }
    } elseif (($_POST['act'] ?? '') === 'forgot') {
        // Password reset request. Same message regardless of whether the email
        // exists (no account enumeration); throttled per IP (3 per hour).
        $ipKey = 'pwr:' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
        if (\SignalMasterAi\Cache::increment($ipKey, 3600) > 3) {
            $error = 'Too many reset requests. Try again later.';
        } elseif (!\SignalMasterAi\Mailer::enabled()) {
            $error = 'Password reset by email is not available on this site yet - contact the operator.';
        } else {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $stmt = Database::pdo()->prepare('SELECT id, email FROM members WHERE email = ?');
            $stmt->execute([$email]);
            $m = $stmt->fetch();
            $stmt->closeCursor();
            if ($m) {
                $token = bin2hex(random_bytes(24));
                Database::pdo()->prepare('UPDATE members SET reset_token = ?, reset_expires = ? WHERE id = ?')
                    ->execute([hash('sha256', $token), time() + 3600, $m['id']]);
                // An install that has not learned its own address yet would
                // otherwise mail a link beginning "/account.php" - not a URL.
                $site = rtrim((string)sma_setting('site_url'), '/');
                if ($site === '') {
                    $site = \SignalMasterAi\Request::baseUrl();
                }
                $link = $site . '/account.php?reset=' . $token;
                \SignalMasterAi\Mailer::send((string)$m['email'],
                    'Password reset - ' . sma_setting('site_name', 'SignalMasterAi'),
                    "Someone (hopefully you) asked to reset the password for this account.\n\n"
                    . "Open this link within 1 hour to choose a new password:\n$link\n\n"
                    . 'If this was not you, simply ignore this email.',
                    \SignalMasterAi\Mailer::template('Reset your password',
                        '<p style="margin:0">Someone (hopefully you) asked to reset your password. The link below works for <strong>1 hour</strong>. If this was not you, just ignore this email.</p>',
                        'Choose a new password', $link),
                    // Transactional: no unsubscribe header, because a password
                    // reset is not something anyone opted into or can opt out
                    // of. Logged all the same - "the reset email never came" is
                    // the most common support question there is.
                    ['kind' => \SignalMasterAi\EmailLog::ACCOUNT, 'member' => (int)$m['id'],
                     'context' => 'password reset link']);
            }
            $flashMsg = 'If that email has an account, a reset link is on its way - check the inbox and spam folder.';
        }
    } elseif (($_POST['act'] ?? '') === 'reset_do') {
        $token = (string)($_POST['token'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        $stmt = Database::pdo()->prepare('SELECT id FROM members WHERE reset_token = ? AND reset_expires > ?');
        $stmt->execute([hash('sha256', $token), time()]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m || !preg_match('/^[a-f0-9]{48}$/', $token)) {
            $error = 'This reset link is invalid or has expired - request a new one.';
        } elseif (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            // verified = 1: the link that got them here was delivered to
            // that mailbox, which is the same thing the registration code
            // proves. Without this a member who lost their code could reset
            // the password and still be held at the verify screen forever.
            //
            // pw_changed: ends every session opened with the old password.
            // This is the request that exists for "someone got into my
            // account", and it is not an answer while their cookie still works.
            Database::pdo()->prepare(
                "UPDATE members SET password_hash = ?, reset_token = '', reset_expires = 0,
                        verified = 1, pw_changed = ? WHERE id = ?"
            )->execute([password_hash($new, PASSWORD_DEFAULT), time(), $m['id']]);
            \SignalMasterAi\MemberAuth::logout();
            $flashMsg = 'Password changed - you can log in with it now. Any other device that was '
                      . 'signed in to this account has been signed out.';
        }
    } elseif (($_POST['act'] ?? '') === 'api_regen') {
        $cm = MemberAuth::current();
        if ($cm && $cm['tier'] === 'paid') {
            $newTok = bin2hex(random_bytes(24));
            Database::pdo()->prepare('UPDATE members SET api_token = ? WHERE id = ?')
                ->execute([hash('sha256', $newTok), $cm['id']]);
            $_SESSION['api_token_show'] = $newTok;   // shown once, stored hashed
            header('Location: account.php');
            exit;
        }
    } elseif (($_POST['act'] ?? '') === 'prefs') {
        // Strategy profile, risk sizing and alert filters. Every value is
        // range-checked in MemberPrefs::save(); these drive money decisions
        // and alert volume, so nothing here is trusted as submitted.
        $cm = MemberAuth::current();
        if ($cm) {
            // sanitiseWebhook() silently returns '' for anything it rejects
            // (http://, a private-range host, ...) - save() would then store
            // that blank indistinguishably from "left empty". Checked here,
            // before save(), so a rejection can say so instead of reporting
            // the same "Settings saved" as everything else on this form.
            $webhookRaw = trim((string)($_POST['webhook_url'] ?? ''));
            $webhookRejected = $webhookRaw !== ''
                && \SignalMasterAi\MemberPrefs::sanitiseWebhook($webhookRaw) === '';
            \SignalMasterAi\MemberPrefs::save((int)$cm['id'], [
                'profile'        => (string)($_POST['profile'] ?? 'balanced'),
                'timezone'       => (string)($_POST['timezone'] ?? ''),
                // account_size, risk_pct and leverage are deliberately absent:
                // the form no longer carries them, and save() merges over the
                // stored values, so listing them here with a default of zero
                // would empty a member's wallet every time they saved a
                // notification preference.
                'min_grade'      => (string)($_POST['min_grade'] ?? 'any'),
                'min_confidence' => (float)($_POST['min_confidence'] ?? 0),
                'directions'     => (string)($_POST['directions'] ?? 'both'),
                // Checkboxes, so an empty POST means "none ticked". The
                // sanitiser reads none and all three as the same thing - every
                // type - which is what an untouched account has always had.
                'alert_types'    => array_map('intval', (array)($_POST['alert_types'] ?? [])),
                'quiet_from'     => (int)($_POST['quiet_from'] ?? -1),
                'quiet_to'       => (int)($_POST['quiet_to'] ?? -1),
                'max_alerts_day' => (int)($_POST['max_alerts_day'] ?? 0),
                'webhook_url'    => (string)($_POST['webhook_url'] ?? ''),
                // An unticked checkbox posts nothing, so its absence IS the
                // "off" - which is why this reads the key's presence rather
                // than its value.
                'trade_email'    => isset($_POST['trade_email']) ? '1' : '0',
                // Blank means "use the site weight", so empty fields are
                // dropped rather than stored as zero (which would silently
                // switch every rule off).
                'rule_weights'   => array_filter(
                    (array)($_POST['rule_weights'] ?? []),
                    fn($v) => is_string($v) && trim($v) !== '' && is_numeric($v)
                ),
                'disabled_categories' => (array)($_POST['disabled_categories'] ?? []),
            ]);
            if ($webhookRejected) {
                // Carried across the redirect the same way verify_error/
                // verify_notice already are, so the risk section can say what
                // was typed and why it did not save.
                $_SESSION['webhook_error'] = 'That webhook URL was not saved - it must be an https:// '
                    . 'address, not a private or local one.';
                $_SESSION['webhook_bad_value'] = $webhookRaw;
                header('Location: account.php#risk');
                exit;
            }
            header('Location: account.php?saved=1#risk');
            exit;
        }
    } elseif (($_POST['act'] ?? '') === 'redeem') {
        // Same call as upgrade.php, same words back. The member reaches this
        // page from a renewal reminder as often as from the price list, so the
        // key box has to exist in both places; the logic behind it exists once.
        $cm = MemberAuth::current();
        if ($cm) {
            [$rOk, $rMsg] = \SignalMasterAi\Redeem::apply((string)($_POST['key'] ?? ''), (int)$cm['id']);
            if ($rOk) {
                header('Location: account.php?redeemed=1');
                exit;
            }
            $error = $rMsg;
        }
    } elseif (($_POST['act'] ?? '') === 'tg_unlink') {
        $cm = MemberAuth::current();
        if ($cm) {
            Database::pdo()->prepare("UPDATE members SET tg_chat_id = 0, tg_link_code = '' WHERE id = ?")
                ->execute([$cm['id']]);
            header('Location: account.php');
            exit;
        }
    } elseif (($_POST['act'] ?? '') === 'login') {
        // Brute-force damping, same policy as the admin login - and counted
        // server-side for the same reason: a failure count kept in $_SESSION
        // is a count the attacker holds, and dropping the cookie resets it.
        $em = (string)($_POST['email'] ?? '');
        [$allowed, $retry] = \SignalMasterAi\RateLimit::loginCheck('mem', $em);
        if (!$allowed) {
            $error = 'Too many failed attempts. Try again in ' . (int)ceil($retry / 60) . ' minutes.';
        } elseif (MemberAuth::login($em, (string)($_POST['password'] ?? ''))) {
            \SignalMasterAi\RateLimit::loginPassed('mem', $em);
            header('Location: ' . ($next !== '' ? $next : 'charts.php'));
            exit;
        } elseif (($pendId = MemberAuth::pendingVerifyId()) > 0) {
            // Right password, unverified address. The credentials were good,
            // so this is not a failed attempt to hold against them.
            \SignalMasterAi\RateLimit::loginPassed('mem', $em);
            $_SESSION['verify_member'] = $pendId;
            [$sent, $sendMsg] = \SignalMasterAi\MemberVerify::issue($pendId);
            $_SESSION[$sent ? 'verify_notice' : 'verify_error'] =
                'This address still needs verifying. ' . $sendMsg;
            header('Location: account.php?tab=verify' . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
            exit;
        } else {
            \SignalMasterAi\RateLimit::loginFailed('mem', $em);
            $error = 'Invalid email or password.';
        }
    }
}

$member = MemberAuth::current();

// A rejected webhook URL, handed across the redirect above.
$webhookBadValue = null;
if (($_SESSION['webhook_error'] ?? '') !== '') {
    $error = (string)$_SESSION['webhook_error'];
    $webhookBadValue = (string)($_SESSION['webhook_bad_value'] ?? '');
    unset($_SESSION['webhook_error'], $_SESSION['webhook_bad_value']);
}

// A logged-in member who still owes a code sees the code form, not their
// account. The gate in MemberAuth::start() sends them here from everywhere
// else; if this page then showed them their dashboard, the lock would have a
// hole in exactly the shape of the page it redirects to.
$mustVerify = $member !== null && \SignalMasterAi\MemberVerify::pending($member);
if ($mustVerify) {
    $_SESSION['verify_member'] = (int)$member['id'];
    $member = null;                 // render the tabs, not the account panel
}

// Returning from the crypto checkout: sync any pending automatic payments so
// premium activates immediately, without waiting for the webhook.
if ($member && isset($_GET['paid'])) {
    $stmt = Database::pdo()->prepare(
        "SELECT * FROM payments WHERE member_id = ? AND kind != 'manual'
         AND status = 'pending' AND gateway_uuid != '' ORDER BY created_at DESC LIMIT 3"
    );
    $stmt->execute([$member['id']]);
    foreach ($stmt->fetchAll() as $p) {
        try {
            \SignalMasterAi\Gateways::refreshStatus($p);
        } catch (Throwable $e) {
            // gateway unreachable - webhook or the next visit will catch it
        }
    }
    $member = MemberAuth::current();   // re-read: tier may have changed
}

$csrf = MemberAuth::csrfToken();
$tabRaw = (string)($_GET['tab'] ?? 'login');
$tab = in_array($tabRaw, ['login', 'register', 'forgot', 'verify'], true) ? $tabRaw : 'login';
if ($mustVerify) {
    $tab = 'verify';                // no way to click past it
}
// The verify step is only reachable while an account is actually waiting on
// one, so a bookmarked ?tab=verify falls back to the login form.
$pendingVerify = (int)($_SESSION['verify_member'] ?? 0);
if ($tab === 'verify' && $pendingVerify <= 0 && empty($_SESSION['verify_decoy'])) {
    $tab = 'login';
}
if ($tab === 'verify') {
    if (($_SESSION['verify_error'] ?? '') !== '' && $error === null) {
        $error = (string)$_SESSION['verify_error'];
    }
    if (($_SESSION['verify_notice'] ?? '') !== '' && $error === null && $notice === null) {
        $notice = (string)$_SESSION['verify_notice'];
    }
    unset($_SESSION['verify_notice'], $_SESSION['verify_error']);
}
$pendingEmail = '';
if ($tab === 'verify') {
    if ($pendingVerify > 0) {
        $st = Database::pdo()->prepare('SELECT email FROM members WHERE id = ?');
        $st->execute([$pendingVerify]);
        $pendingEmail = (string)($st->fetchColumn() ?: '');
    } else {
        // Decoy: the address came from the form rather than from a member row,
        // and the screen has to print it either way. Leaving it out is a
        // fifty-byte difference between "this address is free" and "this
        // address is taken", which is the whole question the decoy refuses.
        $pendingEmail = (string)($_SESSION['verify_decoy_email'] ?? '');
    }
}
$resetToken = preg_match('/^[a-f0-9]{48}$/', (string)($_GET['reset'] ?? '')) ? (string)$_GET['reset'] : '';
$flashMsg = $flashMsg ?? null;
?>
<?php
// HEAD THROUGH View::head(). Same reason as the pricing page: hand-written,
// and missing the description and social tags. It stays noindex - a login
// form to a stranger and a personal page to everyone else - but a link
// shared to it should still say what the site is.
ob_start();
?>
.acct-wrap { max-width: 420px; margin: 40px auto; padding: 0 16px; }
.acct-box { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 26px; }
.acct-box h1 { font-size: 19px; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; }
.acct-box h1 img { width: 30px; height: 30px; border-radius: 8px; }
.acct-box p.sub { color: var(--muted); font-size: 13px; margin-bottom: 14px; }
.tabs { display: flex; gap: 6px; margin-bottom: 16px; }
.tabs a { flex: 1; text-align: center; padding: 9px; border-radius: 8px; text-decoration: none;
  color: var(--muted); background: var(--surface2); border: 1px solid var(--border); font-size: 14px; }
.tabs a.on { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
.acct-box label { display: block; font-size: 13px; color: var(--muted); margin: 12px 0 4px; }
/* Text fields fill the box. Checkboxes and radios do NOT.
   This rule had no type filter, so every radio on the page was stretched to
   the full width of its card - and a 13px dot in a 278px box renders centred,
   which is why the three strategy-profile options had their radio floating
   above the label instead of beside it. It reads as a broken card rather than
   a choice. Same for the alert-filter checkboxes below it. */
.acct-box input:not([type="checkbox"]):not([type="radio"]) {
  width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg); color: var(--text); font-size: 14px; box-sizing: border-box; }
/* 18px, not the browser's 13. "width:auto" here was only undoing the 100%
   above, and it left every checkbox on this page at the default size - which a
   375px sweep counted 81 of, on the screen where a member sets the filters
   that decide which alerts they get. This rule out-specifies the sheet's, so
   it has to carry the size itself. */
.acct-box input[type="checkbox"], .acct-box input[type="radio"] {
  width: 18px; height: 18px; accent-color: var(--accent); }
/* --accent-fill, not --accent: white on the lighter accent is 3.75:1 and this
   button is 14px. See the note in style.css. */
.acct-box .btn { width: 100%; margin-top: 18px; background: var(--accent-fill); color: #fff; border: none;
  padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
.err { background: var(--bg-down); border: 1px solid var(--line-down); color: var(--on-down); padding: 10px 12px;
  border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
/* The plan, as numbers a member can act on. A bar only where there is
   something to run out of - "Unlimited" with a progress bar beside it would be
   a bar that can never move, which reads as a fault. */
.plan-limits { margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px; }
.plan-limits h3 { margin: 0 0 10px; font-size: 13px; letter-spacing: .3px;
  text-transform: uppercase; color: var(--muted); font-weight: 500; }
.plan-row { display: grid; grid-template-columns: 1fr auto; gap: 2px 10px;
  align-items: baseline; padding: 7px 0; border-bottom: 1px solid var(--border); }
.plan-row:last-child { border-bottom: 0; }
.pl-name { font-size: 13px; color: var(--muted); min-width: 0; }
.pl-val { font-size: 13px; color: var(--muted); white-space: nowrap; }
.pl-val b { color: var(--text); font-weight: 500; }
.pl-bar { grid-column: 1 / -1; height: 4px; border-radius: 999px;
  background: var(--surface2); overflow: hidden; margin-top: 4px; }
.pl-bar i { display: block; height: 100%; background: var(--accent); border-radius: 999px; }
.plan-row.full .pl-bar i { background: var(--warn-text); }
.plan-row.full .pl-val b { color: var(--warn-text); }
.tier-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 500; }
.tier-badge.free { background: var(--bg-up); color: var(--up); }
.tier-badge.paid { background: var(--bg-warn); color: var(--warn-text); }
.perk { display: flex; gap: 10px; font-size: 13px; color: var(--muted); margin: 8px 0; }
/* These are standalone links on their own line - log out, back to log in,
   forgot password - not words inside a sentence, so the WCAG 2.2 exception for
   text in a block does not cover them. Measured at 375px they were 15px tall.
   Padding rather than a fixed height: the row grows by the same 5px above and
   below whatever the text is, and the link stays centred in it. */
.back {
  display: block; text-align: center; margin-top: 16px; color: var(--muted);
  font-size: 13px; text-decoration: none; padding: 6px 4px; min-height: 24px;
}
/* Activation key. Same box as the upgrade page - the member should recognise
   it as the same thing in both places. min-width:0 on the input is what keeps
   the flex row inside a 375px screen instead of pushing the page sideways. */
.key-box { border: 1px dashed var(--accent); border-radius: 10px; padding: 14px; margin-top: 18px; background: var(--surface2); }
.key-box label { display: block; font-size: 13px; color: var(--muted); line-height: 1.5; margin-bottom: 8px; }
.key-box strong { color: var(--text); display: block; }
.key-row { display: flex; gap: 8px; flex-wrap: wrap; }
.key-row input { flex: 1 1 190px; min-width: 0; padding: 11px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg); color: var(--text); font-size: 15px; text-transform: uppercase;
  font-family: var(--font-mono); letter-spacing: 1px; }
.key-row button { flex: 0 0 auto; background: var(--accent); color: #fff; border: none; padding: 12px 22px;
  border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
<?php
\SignalMasterAi\View::head('Account', 'Sign in or create a free account to follow coins, set alerts and see the full trade plan on every signal.', [
    'style' => ob_get_clean(),
    'noindex' => true,
]);
?>
<?php \SignalMasterAi\View::topbar(''); ?>
<div class="acct-wrap">
  <div class="acct-box">
    <?php // TWO LOGOS, ONE PAGE.
          // The shared header above already carries the mark and the site
          // name, left-aligned, linking home - View::topbar() renders it on
          // every page including this one. This card repeated both, in a
          // second h1, a few pixels below the first. Removed rather than
          // restyled: the header already does this job, and a page does not
          // need to introduce itself twice before getting to the form. ?>

    <?php if ($member): ?>
      <?php if (isset($_GET['paid'])): ?>
        <div style="background:var(--bg-up);border:1px solid var(--line-up);color:var(--up);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">
          Thank you! As soon as the payment confirms on-chain, premium activates automatically.
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['saved'])): ?>
        <div style="background:var(--bg-up);border:1px solid var(--line-up);color:var(--up);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">
          Settings saved.
        </div>
      <?php endif; ?>
      <?php // The confirmation names the new expiry rather than saying "done",
            // because the one thing somebody wants after redeeming a key is
            // the date it runs to - and on an extension that date is the only
            // proof the days were added to what they already had. ?>
      <?php if (isset($_GET['redeemed'])): ?>
        <div style="background:var(--bg-up);border:1px solid var(--line-up);color:var(--up);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">
          Key activated &mdash; premium is on
          <?= (int)$member['paid_until'] > 0
              ? 'until <strong>' . gmdate('M j, Y', (int)$member['paid_until']) . '</strong> UTC'
              : 'with no end date' ?>.
        </div>
      <?php endif; ?>
      <?php // The signed-in half of this page had no way to say "that did not
            // work". Every form on it either redirected or saved silently, so
            // nothing had ever needed one - until a key can be mistyped. ?>
      <?php if ($error): ?><div class="err"><?= sma_e($error) ?></div><?php endif; ?>
      <p class="sub">You are signed in.</p>
      <p style="font-size:15px;margin:14px 0 6px"><strong><?= sma_e($member['email']) ?></strong></p>
      <p><span class="tier-badge <?= sma_e($member['tier']) ?>"><?= $member['tier'] === 'paid' ? '★ PREMIUM' : 'FREE MEMBER' ?></span>
      <?php if ($member['tier'] === 'paid'): ?>
        <span class="sub" style="font-size:12px;color:var(--muted)">
          <?= (int)$member['paid_until'] > 0
              ? 'active until ' . gmdate('M j, Y H:i', (int)$member['paid_until']) . ' UTC'
              : 'unlimited access' ?>
        </span>
      <?php endif; ?>
      </p>
      <?php // A TRIAL SAYS SO, AND SAYS WHEN IT ENDS.
            //
            // Premium from a trial and premium from a payment look identical
            // on this page otherwise - same badge, same date - and the day it
            // lapses the member has no idea why access stopped. Counted in
            // whole days remaining, because "2 days left" is what somebody
            // decides on; the exact date is already on the line above.
            $trial = \SignalMasterAi\Trial::status((int)$member['id']); ?>
      <?php if ($trial !== null): ?>
        <?php if (!$trial['expired']): ?>
          <p class="tp-note" style="margin:2px 0 10px">
            <strong>Free trial<?= $trial['left'] > 0
              ? ' &mdash; ' . (int)$trial['left'] . ' day' . ($trial['left'] === 1 ? '' : 's') . ' left'
              : ' &mdash; ends today' ?>.</strong>
            Full premium access until <?= sma_e(gmdate('M j, Y H:i', (int)$trial['ends_at'])) ?> UTC.
            <a href="upgrade.php">Choose a plan</a> any time &mdash; paying does not cut the trial
            short, the longer of the two always wins.</p>
        <?php elseif ($member['tier'] !== 'paid'): ?>
          <p class="tp-note" style="margin:2px 0 10px">Your <?= (int)$trial['days'] ?>-day free trial
            ended on <?= sma_e(gmdate('M j, Y', (int)$trial['ends_at'])) ?>.
            <a href="upgrade.php">Upgrade</a> to get premium coins and alerts back.</p>
        <?php endif; ?>
      <?php endif; ?>
      <?php
      // WHAT THE PLAN ACTUALLY GIVES, AGAINST WHAT IS USED.
      //
      // The three lines here said "premium coins - paid plan" and nothing
      // else: a member could not see a single number their plan decides, so
      // the only way to discover an allowance was to hit it. That is the worst
      // moment to learn about a limit and the worst moment to be asked to pay.
      //
      // Every row is derived from Limits, so a tier the operator has not
      // capped simply reads Unlimited, and a limit they add appears here
      // without anyone editing this page. Rows where the plan is already
      // unlimited carry no bar - there is nothing to run out of.
      $mid = (int)$member['id'];
      $myTier = \SignalMasterAi\MemberAuth::tier();
      $pdoU = \SignalMasterAi\Database::pdo();
      $usedOf = static function (string $key) use ($mid, $pdoU): ?int {
          switch ($key) {
              case 'watch':
                  $q = $pdoU->prepare('SELECT pairs FROM member_alerts WHERE member_id = ?');
                  $q->execute([$mid]);
                  $n = 0;
                  foreach ($q->fetchAll() as $r) {
                      $n += count(array_filter(explode(',', (string)$r['pairs'])));
                  }
                  return $n;
              case 'positions':
                  $q = $pdoU->prepare("SELECT COUNT(*) FROM paper_trades WHERE member_id = ? AND status = 'open'");
                  $q->execute([$mid]);
                  return (int)$q->fetchColumn();
              case 'backtests':
                  return (int)(\SignalMasterAi\Cache::get('btday:m' . $mid . ':' . gmdate('Y-m-d')) ?? 0);
              case 'ai_asks':
                  return (int)(\SignalMasterAi\Cache::get('askday:' . $mid . ':' . gmdate('Y-m-d')) ?? 0);
              default:
                  return null;   // nothing meaningful to count against it
          }
      };
      ?>
      <div class="plan-limits">
        <h3>Your plan</h3>
        <?php foreach (\SignalMasterAi\Limits::LIMITS as $lk => $l): ?>
          <?php
          if (!\SignalMasterAi\Limits::live($lk)) {
              continue;                       // the feature is switched off site-wide
          }
          $max  = \SignalMasterAi\Limits::of($lk, $myTier);
          $used = $usedOf($lk);
          $pct  = ($max > 0 && $used !== null) ? min(100, (int)round($used / $max * 100)) : 0;
          $full = $max > 0 && $used !== null && $used >= $max;
          ?>
          <div class="plan-row<?= $full ? ' full' : '' ?>">
            <span class="pl-name"><?= sma_e((string)$l['label']) ?></span>
            <span class="pl-val"><?php
              if ($max === 0) {
                  echo '<b>Unlimited</b>';
              } elseif ($used === null) {
                  echo '<b>' . number_format($max) . '</b>';
              } else {
                  echo '<b>' . number_format($used) . '</b> / ' . number_format($max);
              }
            ?></span>
            <?php if ($max > 0 && $used !== null): ?>
              <span class="pl-bar" aria-hidden="true"><i style="width:<?= $pct ?>%"></i></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="plan-row">
          <span class="pl-name">Premium coins</span>
          <span class="pl-val"><b><?= $member['tier'] === 'paid' ? 'Included' : 'Locked' ?></b></span>
        </div>
      </div>
      <?php // Nothing to sell somebody who already owns it outright.
            //
            // A lifetime account was shown "Extend premium", which is an offer
            // to sell them days they can never run out of. The button goes and
            // a plain sentence takes its place - the one thing they might
            // actually wonder is whether it really has no end date. ?>
      <?php $isLifetime = $member['tier'] === 'paid' && (int)$member['paid_until'] === 0; ?>
      <?php if ($isLifetime): ?>
        <p class="sub" style="margin-top:18px;text-align:center">
          You have <strong>lifetime premium</strong> &mdash; there is nothing to renew.</p>
      <?php else: ?>
        <a class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:18px" href="upgrade.php">
          <?= $member['tier'] === 'paid' ? 'Extend premium' : '★ Upgrade to premium' ?>
        </a>
      <?php endif; ?>
      <?php // A key box here as well as on the upgrade page, because this is
            // where somebody lands from a renewal reminder, and asking them to
            // go to a page of prices to type a code they were given free is
            // the wrong direction of travel. Redeem::offered() keeps it off
            // every install that has no keys out. ?>
      <?php // Not for a lifetime account: the key would be consumed and its
            // seat used up to grant access that is already unlimited. ?>
      <?php if (\SignalMasterAi\Redeem::offered() && !$isLifetime): ?>
        <form method="post" action="account.php" class="key-box">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="redeem">
          <label for="keyfield"><strong>Activation key</strong>
            <?= $member['tier'] === 'paid' ? 'Adds its days on top of what you have left.'
                                           : 'Turns premium on straight away, no payment needed.' ?></label>
          <div class="key-row">
            <input id="keyfield" type="text" name="key" required autocomplete="off" spellcheck="false"
                   placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="24">
            <button type="submit">Activate</button>
          </div>
        </form>
      <?php endif; ?>
      <?php // btn-secondary, not a .btn with its background overridden inline.
            // .btn is the filled primary and now carries white ink to clear
            // contrast on its fill; painting a pale surface behind that inline
            // left white text on a light background - 1.12:1, and my own doing.
            // The class carries both halves so they cannot part company. ?>
      <a class="btn btn-secondary" style="display:block;text-align:center;margin-top:10px" href="index.php">Open charts</a>

      <?php
      // Strategy, risk and alert controls. These used to be site-wide admin
      // settings (one risk appetite for everyone) or absent entirely.
      $prefs = \SignalMasterAi\MemberPrefs::get((int)$member['id']);
      $profiles = \SignalMasterAi\MemberPrefs::PROFILES;
      ?>
      <form method="post" action="account.php#risk" id="risk" class="prefs-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="prefs">

        <p class="sub prefs-head"><strong>Strategy profile</strong></p>
        <?php // Offered only when the operator allows personal thresholds. A
              // picker that stores a choice the engine then ignores is the
              // worst of both: the member believes they changed something and
              // every reading they take afterwards is judged against a setting
              // that was never applied. ?>
        <?php if (Database::setting('member_engine_profile', '0') === '1'): ?>
          <div class="profile-picker">
            <?php foreach ($profiles as $key => $p): ?>
              <label class="profile-opt <?= $prefs['profile'] === $key ? 'on' : '' ?>">
                <input type="radio" name="profile" value="<?= sma_e($key) ?>" <?= $prefs['profile'] === $key ? 'checked' : '' ?>>
                <strong><?= sma_e($p['label']) ?></strong>
                <span><?= sma_e($p['blurb']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="prefs-hint">Changes what the chart shows you, and only for you. It does not
            change the alerts you are sent, and nothing personal ever enters the public track
            record.</p>
        <?php else: ?>
          <p class="prefs-hint">Every member sees the same analysis, tuned by the site rather than
            per account &mdash; so the calls you read are the calls the track record is measured on.</p>
        <?php endif; ?>

        <p class="sub prefs-head"><strong>Funds &amp; position sizing</strong></p>
        <!-- Funds live in one place, and size is chosen per trade in the
             order ticket. Keeping an account-size and risk-percent form here
             as well meant two screens claiming to decide the same thing. -->
        <p class="prefs-hint">Your balance is in your
          <a href="portfolio.php">portfolio wallet</a> — add or withdraw funds there. How much of it
          goes into any one trade is chosen on the chart when you open the position, the way an
          exchange order works.</p>
        <div class="prefs-grid">
          <label>Timezone
            <select name="timezone">
              <option value="">UTC (default)</option>
              <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                <option value="<?= sma_e($tz) ?>" <?= $prefs['timezone'] === $tz ? 'selected' : '' ?>><?= sma_e($tz) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <p class="sub prefs-head"><strong>Alert filters</strong></p>
        <p class="prefs-hint">Previously the only way to receive fewer alerts was to stop watching coins.</p>
        <div class="prefs-grid">
          <?php
          // The grade a member may ask for is bounded by the site's own floor.
          //
          // Both filters are applied, admin first, so a member has never been
          // able to receive a signal below the site minimum - the number they
          // pick can only ever narrow it. What the control DID do was offer
          // choices that changed nothing: with the site set to A, "Any grade",
          // "B and up" and "A and up" all delivered exactly A and A+. Four
          // options, one outcome, and no way for the member to tell. A control
          // whose settings do not change anything is worse than no control,
          // because it teaches the reader that none of the settings matter.
          // The real floor, never looser than what the site publishes -
          // see Publish::alertFloor(). Reading the raw setting here would
          // let this list offer "Any grade" on an install where nothing
          // below B ever actually gets sent.
          $siteFloor = \SignalMasterAi\Publish::alertFloor();
          if ($siteFloor === '') {
              $siteFloor = 'any';
          }
          $gradeOrder = ['any' => -1, 'C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];
          $floorRank  = $gradeOrder[$siteFloor] ?? -1;
          $gradeOpts  = [];
          foreach (['any' => 'Any grade', 'B' => 'B and up', 'A' => 'A and up', 'A+' => 'A+ only'] as $g => $lbl) {
              if (($gradeOrder[$g] ?? -1) >= $floorRank) {
                  $gradeOpts[$g] = $lbl;
              }
          }
          $memberMayFilter = Database::setting('member_grade_filter', '0') === '1' && count($gradeOpts) > 1;
          ?>
          <?php if ($memberMayFilter): ?>
            <label>Minimum grade
              <select name="min_grade">
                <?php foreach ($gradeOpts as $g => $lbl): ?>
                  <option value="<?= sma_e($g) ?>" <?= $prefs['min_grade'] === $g ? 'selected' : '' ?>><?= sma_e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($floorRank > -1): ?>
                <small class="prefs-hint">This site only sends <?= sma_e($siteFloor) ?> and above, so
                  that is the lowest setting here.</small>
              <?php endif; ?>
            </label>
          <?php else: ?>
            <?php // Told, not hidden. A member who cannot change it should still
                  // know what quality bar their alerts are being held to. ?>
            <label>Minimum grade
              <input type="text" aria-label="Minimum grade set by the site"
                     value="<?= $siteFloor === 'any' ? 'Every signal' : sma_e($siteFloor) . ' and above' ?>" disabled>
              <small class="prefs-hint">Set for the whole site.</small>
            </label>
          <?php endif; ?>
          <label>Minimum confidence (%)
            <input type="number" step="1" min="0" max="100" name="min_confidence" value="<?= sma_e((string)$prefs['min_confidence']) ?>">
          </label>
          <label>Direction
            <select name="directions">
              <?php foreach (['both' => 'Both directions', 'buy' => 'BUY only', 'sell' => 'SELL only'] as $d => $lbl): ?>
                <option value="<?= sma_e($d) ?>" <?= $prefs['directions'] === $d ? 'selected' : '' ?>><?= sma_e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php // WHICH OF THE THREE TYPES TO BE TOLD ABOUT.
                //
                // Checkboxes rather than a "1:2 and up" dropdown, because
                // these are not ranks: a 1:1 is a shorter trade with a nearer
                // target, not a worse signal, and somebody who only wants to
                // sit in 1:3s and somebody who only wants quick 1:1s are both
                // making a sensible choice. Ticking all three and ticking none
                // are the same instruction and both mean everything. ?>
          <?php // Hidden once the operator has narrowed the site to some of
                // the three types: there is nothing left to choose between,
                // and a control that empties somebody's alerts with no reason
                // on the page is worse than no control. MemberPrefs clears any
                // stored choice at the same time, so a hidden box is never
                // still filtering. ?>
          <?php if (\SignalMasterAi\Publish::memberMayChooseType()): ?>
          <fieldset class="pref-types">
            <legend>Signal types to alert me about</legend>
            <?php $atSel = $prefs['alert_types'] ?: [1, 2, 3]; ?>
            <?php foreach ([1 => '1:1', 2 => '1:2', 3 => '1:3'] as $tv => $tl): ?>
              <label class="pref-type"><input type="checkbox" name="alert_types[]" value="<?= $tv ?>"
                <?= in_array($tv, $atSel, true) ? 'checked' : '' ?>>
                <span class="rr-type rr<?= $tv ?>"><?= sma_e($tl) ?></span></label>
            <?php endforeach; ?>
            <p class="hint">Every plan carries targets at one, two and three times its risk; the
              type is which of them the setup is expected to reach. Leave all three ticked to hear
              about everything. Calls made before types existed are never withheld by this.</p>
          </fieldset>
          <?php endif; ?>
          <label>Max alerts per day
            <input type="number" step="1" min="0" max="500" name="max_alerts_day" value="<?= sma_e((string)$prefs['max_alerts_day']) ?>" placeholder="0 = unlimited">
          </label>
          <label>Quiet hours from
            <select name="quiet_from">
              <option value="-1">Off</option>
              <?php for ($h = 0; $h < 24; $h++): ?>
                <option value="<?= $h ?>" <?= $prefs['quiet_from'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
              <?php endfor; ?>
            </select>
          </label>
          <label>Quiet hours to
            <select name="quiet_to">
              <option value="-1">Off</option>
              <?php for ($h = 0; $h < 24; $h++): ?>
                <option value="<?= $h ?>" <?= $prefs['quiet_to'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
              <?php endfor; ?>
            </select>
          </label>
        </div>

        <?php
        // Personal rule weights. The engine and API already honoured these;
        // without a form they were reachable only by hand-crafting a request.
        $allRules = Database::pdo()
            ->query('SELECT rule_key, name, category, weight FROM ta_knowledge WHERE enabled = 1 ORDER BY category, name')
            ->fetchAll();
        $myWeights = $prefs['rule_weights'];
        $allCats = Database::pdo()->query('SELECT category, label FROM ta_categories ORDER BY label')->fetchAll();
        ?>
        <details class="adv-rules"<?= ($myWeights || $prefs['disabled_categories']) ? ' open' : '' ?>>
          <summary>Tune the engine yourself (advanced)</summary>
          <p class="prefs-hint">Turn off whole kinds of evidence, or override the weight of individual
            rules for your own analysis. Your changes affect only what <em>you</em> see — the site's
            published track record always reflects the default engine.</p>

          <p class="sub prefs-head" style="margin-top:12px"><strong>Ignore these categories</strong></p>
          <div class="cat-toggles">
            <?php foreach ($allCats as $c): ?>
              <label class="cat-toggle">
                <input type="checkbox" name="disabled_categories[]" value="<?= sma_e($c['category']) ?>"
                  <?= in_array($c['category'], $prefs['disabled_categories'], true) ? 'checked' : '' ?>>
                <?= sma_e($c['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>

          <p class="sub prefs-head" style="margin-top:14px"><strong>Rule weights</strong></p>
          <p class="prefs-hint">Blank uses the site weight (shown as the placeholder). 0 switches the
            rule off for you.</p>
          <div class="rule-weights">
            <?php $lastCat = null; foreach ($allRules as $r): ?>
              <?php if ($r['category'] !== $lastCat): $lastCat = $r['category']; ?>
                <div class="rw-cat"><?= sma_e($r['category']) ?></div>
              <?php endif; ?>
              <label class="rw-row">
                <span><?= sma_e($r['name']) ?></span>
                <input type="number" step="0.1" min="0" max="5"
                       name="rule_weights[<?= sma_e($r['rule_key']) ?>]"
                       value="<?= isset($myWeights[$r['rule_key']]) ? sma_e((string)$myWeights[$r['rule_key']]) : '' ?>"
                       placeholder="<?= sma_e((string)round((float)$r['weight'], 2)) ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </details>

        <?php // RECEIPTS FOR YOUR OWN TRADES.
              //
              // Separate from the signal alerts above, and on by default,
              // because they answer a different question: those tell you the
              // market moved, these tell you what happened to money you had
              // committed. Somebody who wants no signal alerts at all still
              // wants to know their position closed.
              //
              // Shown only where the site actually has paper trading and can
              // actually send mail - a switch for something that cannot happen
              // is a promise the install will not keep. ?>
        <?php if (sma_setting('paper_enabled', '1') === '1'
                  && sma_setting('trade_email_enabled', '1') === '1'): ?>
        <p class="sub prefs-head"><strong>Trade receipts</strong></p>
        <label class="chk trade-mail">
          <input type="checkbox" name="trade_email" value="1"
                 <?= ($prefs['trade_email'] ?? '1') !== '0' ? 'checked' : '' ?>>
          <span>Email me when one of my paper trades opens or closes
            <span class="prefs-hint" style="display:block">The close carries the exit price, how it
              ended, the profit or loss in money and in R, how long you held it, and your wallet
              afterwards.</span></span>
        </label>
        <?php endif; ?>

        <?php if (sma_setting('webhooks_enabled', '1') === '1'): ?>
        <p class="sub prefs-head"><strong>Webhook (advanced)</strong></p>
        <p class="prefs-hint">Every flip on your watched pairs is POSTed here as signed JSON — connect a
          bot, n8n, Zapier or your own service. HTTPS public endpoints only.</p>
        <label class="wh-label">Endpoint URL
          <input type="url" name="webhook_url" value="<?= sma_e($webhookBadValue ?? $prefs['webhook_url']) ?>" placeholder="https://example.com/hooks/signals">
        </label>
        <?php if ($prefs['webhook_url'] !== ''): ?>
          <p class="prefs-hint">Verify requests with the <code>X-SMA-Signature</code> header (HMAC-SHA256).
            Signing secret: <code class="secret"><?= sma_e(\SignalMasterAi\Webhooks::secret((int)$member['id'])) ?></code>
            <?php $fails = \SignalMasterAi\Webhooks::recentFailures((int)$member['id']); ?>
            <?php if ($fails > 0): ?>
              <br><span style="color:var(--warn-text)">⚠ <?= (int)$fails ?> recent delivery failure<?= $fails === 1 ? '' : 's' ?> — check the endpoint is reachable.</span>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php endif; ?>

        <button class="btn" type="submit">Save my settings</button>
      </form>

      <?php if (\SignalMasterAi\Referrals::enabled()):
        $refCode = \SignalMasterAi\Referrals::codeFor((int)$member['id']);
        $refStats = \SignalMasterAi\Referrals::stats((int)$member['id']);
        $refDays = (int)sma_setting('referral_days', '7');
        $refBase = rtrim(sma_setting('site_url'), '/');
      ?>
      <div class="reveal">
      <p class="sub" style="margin-top:20px;margin-bottom:6px"><strong style="color:var(--text)">Invite a friend</strong></p>
      <p class="sub" style="font-size:11.5px">When someone who signs up through your link makes their
        first payment, you both get <strong><?= $refDays ?> days</strong> of premium.</p>
      <input type="text" readonly data-select-on-click style="font-size:12px"
             aria-label="Your referral link"
             value="<?= sma_e(($refBase !== '' ? $refBase : '') . '/?ref=' . $refCode) ?>">
      <p class="sub" style="font-size:11.5px;margin-top:6px">
        Signed up through your link: <strong><?= (int)$refStats['total'] ?></strong><?php
        // Both halves, because they answer different questions - who arrived,
        // and who paid. Only the first was shown, and it was counted in a way
        // that DROPPED anybody who converted, so the number fell every time
        // the feature worked. See Referrals::stats().
        if ((int)$refStats['total'] > 0): ?>
          &mdash; <strong><?= (int)$refStats['converted'] ?></strong>
          <?= (int)$refStats['converted'] === 1 ? 'has' : 'have' ?> paid, so
          you have earned <strong><?= (int)$refStats['converted'] * $refDays ?> days</strong>.
        <?php endif; ?>
      </p>
      </div>
      <?php endif; ?>

      <?php // Telegram delivery is gated like the rest - see Admin >
            // Settings > What free and premium accounts get. Shown-but-locked
            // rather than hidden, so a free member knows the channel exists. ?>
      <?php if (\SignalMasterAi\Telegram::enabled()): ?>
      <div class="reveal">
      <?php endif; ?>
      <?php if (\SignalMasterAi\Telegram::enabled()
                && !\SignalMasterAi\Gate::allows('telegram', $member['tier'])): ?>
        <p class="sub" style="margin-top:20px;margin-bottom:6px"><strong style="color:var(--text)">Telegram alerts <span class="nav-lock"></span></strong></p>
        <p class="sub">Signals pushed to Telegram the moment they fire. Part of
          <a href="upgrade.php">Premium</a>.</p>
      <?php endif; ?>
      <?php if (\SignalMasterAi\Telegram::enabled()
                && \SignalMasterAi\Gate::allows('telegram', $member['tier'])): ?>
        <?php $tgLinked = (int)(Database::pdo()->query('SELECT tg_chat_id FROM members WHERE id = ' . (int)$member['id'])->fetchColumn()) !== 0; ?>
        <p class="sub" style="margin-top:20px;margin-bottom:6px"><strong style="color:var(--text)">Telegram alerts</strong></p>
        <?php if ($tgLinked): ?>
          <div class="perk" style="justify-content:space-between">
            <span style="color:var(--up)">&#10004; Connected - signal alerts arrive in your Telegram</span>
            <form method="post" action="account.php" class="inline" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="act" value="tg_unlink">
              <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;text-decoration:underline">Unlink</button>
            </form>
          </div>
        <?php else: ?>
          <?php // Stored value only - see Telegram::botUsername. When it is not
                // resolved yet the connect button is simply not offered, which
                // is what already happened; what changed is that finding out
                // no longer costs this page an outbound call on every visit. ?>
          <?php $tgUser = \SignalMasterAi\Telegram::botUsername(); ?>
          <?php if ($tgUser !== ''): ?>
            <a class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:6px;background:var(--accent)"
               href="https://t.me/<?= sma_e($tgUser) ?>?start=<?= sma_e(\SignalMasterAi\Telegram::linkCode((int)$member['id'])) ?>"
               target="_blank" rel="noopener">Connect Telegram - get signals in your DMs</a>
            <p class="sub" style="font-size:11.5px;margin-top:6px">Opens the bot - tap START once and your watched
              coins deliver to Telegram automatically (linking takes up to a minute).</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (\SignalMasterAi\Telegram::enabled()): ?>
      </div>
      <?php endif; ?>

      <?php if ($member['tier'] === 'paid' && sma_setting('api_feed_enabled', '1') === '1'): ?>
      <div class="reveal">
        <p class="sub" style="margin-top:20px;margin-bottom:6px"><strong style="color:var(--text)">Signals API (premium)</strong></p>
        <?php if (!empty($_SESSION['api_token_show'])): ?>
          <?php // A token plainly exists here - it's shown two lines down -
                // but $hasTok was only ever assigned in the OTHER branch, so
                // the "Regenerate token" vs "Generate API token" button below
                // read the empty variable as false and kept saying "Generate"
                // immediately under a freshly generated token. ?>
          <?php $hasTok = true; ?>
          <p class="sub" style="font-size:12px">Your new token - <strong>copy it now</strong>, it is shown only once:</p>
          <code style="display:block;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px;font-size:11.5px;word-break:break-all"><?= sma_e($_SESSION['api_token_show']) ?></code>
          <p class="sub" style="font-size:11.5px;margin-top:6px">Send it as a header - a token in the URL
            ends up in server logs and browser history:</p>
          <code style="display:block;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px;font-size:11px;word-break:break-all">curl -H "Authorization: Bearer &lt;your token&gt;" <?= sma_e(\SignalMasterAi\Request::baseUrl()) ?>/api.php?action=feed</code>
          <p class="sub" style="font-size:11.5px;margin-top:6px">Returns the latest signal per coin as JSON.
            <code>?token=</code> in the URL still works for anything already built against it.</p>
          <?php unset($_SESSION['api_token_show']); ?>
        <?php else: ?>
          <?php $hasTok = (string)Database::pdo()->query('SELECT api_token FROM members WHERE id = ' . (int)$member['id'])->fetchColumn() !== ''; ?>
          <p class="sub" style="font-size:12px"><?= $hasTok
              ? 'A token is active (stored hashed - it cannot be shown again).'
              : 'Plug the signals into your own bot or dashboard with a personal API token.' ?></p>
        <?php endif; ?>
        <form method="post" action="account.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="api_regen">
          <button class="btn" type="submit" style="background:var(--surface2);border:1px solid var(--border);margin-top:6px">
            <?= !empty($hasTok) ? 'Regenerate token (old one stops working)' : 'Generate API token' ?></button>
        </form>

        <?php // AUTOMATING IT: THE ANSWER, WHERE THE QUESTION GETS ASKED.
              //
              // The token and the webhook have been here for a while and
              // nothing on this page said what a person is supposed to do with
              // them, so "can I auto-trade these?" was a support question with
              // no page to point at. It is answered here rather than in a
              // manual because this is the screen somebody is on when they
              // wonder - and it says plainly where the exchange keys go, which
              // is the one part of this that has to be unambiguous. ?>
        <details class="auto-doc">
          <summary>Trading these automatically</summary>
          <p class="sub">Two ways in. <strong>Pull:</strong> ask the feed above every minute -
            works from a laptop or a home PC, nothing has to reach you.
            <strong>Push:</strong> set the webhook below and each call arrives as it happens,
            which needs a public HTTPS address.</p>
          <p class="sub"><strong>Your exchange keys stay with you.</strong> This site never asks
            for them and has nowhere to put them: it publishes signals, and whatever you run
            places the orders. Nobody should ever ask you to paste exchange API keys into a web
            form &mdash; including us.</p>
          <p class="sub">A ready-to-run connector ships with the site, in the
            <code>connector/</code> folder: Python, works with Binance, Bybit, OKX, KuCoin and
            the rest through ccxt, sizes each trade by the risk you allow, and places the entry,
            the stop and the take-profit ladder the signal already carries. It starts on a paper
            backend and needs three separate settings before it will touch real money.</p>
          <p class="sub">Every call carries an <strong>id</strong> like <code>SM-MCSK6B</code>.
            It is the same string in the feed, the webhook, your alert email and on the chart, and
            it is what stops one signal being traded twice &mdash; a webhook is delivered at least
            once by design, so key on the id, never on the coin and the time.</p>
          <p class="sub"><code>levels.expires_at</code> is the plan's own deadline. Past it the
            setup is not the setup any more, and the connector skips it.</p>
          <p class="sub"><strong>Telegram.</strong> No exchange takes orders from Telegram &mdash;
            anything that says otherwise is a program somewhere else holding the keys. What it is
            good for is the other half: the connector can report every decision to your own
            Telegram bot and take <code>/pause</code>, <code>/status</code> and
            <code>/risk</code> from your phone, and in confirm mode it places nothing until you
            tap <strong>Take</strong> on the message. The bot is yours, made with @BotFather in a
            minute &mdash; it is not this site's bot, which only delivers alerts.</p>
        </details>
      </div>
      <?php endif; ?>

      <?php
      // A HISTORY NEEDS A DATE AND A METHOD.
      //
      // This was "#46 Monthly - $10.00 PENDING": no date, no method, and
      // capped at eight with no way past it. A member asking "did my bank
      // transfer from Tuesday go through" could not answer it from their own
      // account page, and after eight orders their earlier ones were simply
      // gone. ?paid=all lists everything.
      $payAll = ($_GET['pays'] ?? '') === 'all';
      $payTotal = (int)(function () use ($member) {
          $s = Database::pdo()->prepare('SELECT COUNT(*) FROM payments WHERE member_id = ?');
          $s->execute([$member['id']]);
          $n = (int)$s->fetchColumn();
          $s->closeCursor();
          return $n;
      })();
      $myPays = Database::pdo()->prepare(
          'SELECT * FROM payments WHERE member_id = ? ORDER BY created_at DESC'
          . ($payAll ? '' : ' LIMIT 8'));
      $myPays->execute([$member['id']]);
      $myPays = $myPays->fetchAll();
      $payColour = fn(string $s) => match ($s) {
          'paid'            => 'var(--up)',
          'awaiting_review' => 'var(--warn-text)',
          'pending'         => 'var(--text)',
          default           => 'var(--muted)',
      };
      $payWord = fn(array $p) => match ($p['status']) {
          'paid'            => 'PAID',
          'awaiting_review' => 'CHECKING',
          'pending'         => $p['kind'] === 'manual' ? 'AWAITING YOUR PAYMENT' : 'NOT FINISHED',
          'failed'          => 'FAILED',
          'expired'         => 'EXPIRED',
          'rejected'        => 'REJECTED',
          default           => strtoupper(str_replace('_', ' ', (string)$p['status'])),
      };
      ?>
      <?php if ($myPays): ?>
      <div class="reveal">
      <p class="sub" style="margin-top:20px;margin-bottom:6px"><strong style="color:var(--text)">Your payments</strong></p>
      <?php foreach ($myPays as $p): ?>
        <div class="perk" style="justify-content:space-between;gap:10px;align-items:flex-start">
          <span style="min-width:0">
            <a href="upgrade.php?pay=<?= (int)$p['id'] ?>" style="color:var(--text);text-decoration:none">#<?= (int)$p['id'] ?> <?= sma_e($p['plan_name']) ?></a>
            &middot; $<?= number_format((float)$p['amount_usd'], 2) ?>
            <span class="sub" style="display:block;font-size:11.5px">
              <?= sma_e(gmdate('j M Y', (int)$p['created_at'])) ?>
              &middot; <?= sma_e($p['method_name']) ?></span>
          </span>
          <span style="color:<?= $payColour((string)$p['status']) ?>;white-space:nowrap;font-size:12px">
            <?= sma_e($payWord($p)) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$payAll && $payTotal > count($myPays)): ?>
        <p class="sub" style="font-size:12px;margin-top:4px">
          <a href="account.php?pays=all" style="color:var(--accent)">Show all <?= $payTotal ?> payments</a></p>
      <?php elseif ($payAll && $payTotal > 8): ?>
        <p class="sub" style="font-size:12px;margin-top:4px">
          <a href="account.php" style="color:var(--accent)">Show fewer</a></p>
      <?php endif; ?>
      </div>
      <?php endif; ?>
      <a class="back" href="account.php?logout=1">Log out</a>

    <?php else: ?>
      <?php if ($mustVerify): ?>
        <?php // Locked. Offering "Log in / Register" to someone who is already
              // logged in and simply owes a code is two dead ends and no way
              // out; the way out is the code, or the door. ?>
        <p class="sub">Your account is waiting on one more step.</p>
      <?php else: ?>
      <p class="sub">Free registration unlocks member-tier coins.</p>
      <div class="tabs">
        <a href="account.php?tab=login" class="<?= $tab === 'login' ? 'on' : '' ?>">Log in</a>
        <?php if ($regEnabled): ?>
        <a href="account.php?tab=register" class="<?= $tab === 'register' ? 'on' : '' ?>">Register</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($error): ?><div class="err"><?= sma_e($error) ?></div><?php endif; ?>
      <?php foreach (array_filter([$flashMsg, $notice]) as $good): ?>
        <div style="background:var(--bg-up);border:1px solid var(--line-up);color:var(--up);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px"><?= sma_e($good) ?></div>
      <?php endforeach; ?>

      <?php if ($tab === 'verify'): ?>
        <p class="sub"><strong style="color:var(--text)">Check your email</strong> &mdash; we sent a
          <?= (int)\SignalMasterAi\MemberVerify::LEN ?>-digit code<?= $pendingEmail !== '' ? ' to <strong style="color:var(--text)">'
              . sma_e($pendingEmail) . '</strong>' : '' ?>. It expires in
          <?= (int)\SignalMasterAi\MemberVerify::ttlMinutes() ?> minutes.</p>
        <form method="post" action="account.php?tab=verify">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="verify_otp">
          <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= sma_e($next) ?>"><?php endif; ?>
          <label>Verification code</label>
          <?php /* inputmode + one-time-code give a phone the numeric pad and the
                   autofill suggestion straight from the notification. */ ?>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                 maxlength="<?= (int)\SignalMasterAi\MemberVerify::LEN ?>"
                 autocomplete="one-time-code" autofocus required
                 style="letter-spacing:8px;font-size:22px;text-align:center;font-weight: 500">
          <button class="btn" type="submit">Verify and continue</button>
        </form>
        <form method="post" action="account.php?tab=verify" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="resend_otp">
          <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= sma_e($next) ?>"><?php endif; ?>
          <button type="submit" class="back"
                  style="background:none;border:0;padding:0;cursor:pointer;font:inherit">
            Didn't get it? Send another code</button>
        </form>
        <p class="sub" style="margin-top:12px;font-size:12px">Look in the spam folder too &mdash; a
          verification code is exactly the kind of mail filters like to hold on to.</p>
        <?php if ($mustVerify): ?>
          <a class="back" href="account.php?logout=1">Log out</a>
        <?php else: ?>
          <a class="back" href="account.php?tab=login">&larr; Back to log in</a>
        <?php endif; ?>

      <?php elseif ($resetToken !== ''): ?>
        <p class="sub"><strong style="color:var(--text)">Choose a new password</strong></p>
        <form method="post" action="account.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="reset_do">
          <input type="hidden" name="token" value="<?= sma_e($resetToken) ?>">
          <label>New password (min 8 characters)</label>
          <input type="password" name="password" autocomplete="new-password" required>
          <button class="btn" type="submit">Set new password</button>
        </form>
      <?php elseif ($tab === 'forgot'): ?>
        <p class="sub"><strong style="color:var(--text)">Reset your password</strong> - we email you a link.</p>
        <form method="post" action="account.php?tab=forgot">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="forgot">
          <label>Email</label>
          <input type="email" name="email" autocomplete="email" required>
          <button class="btn" type="submit">Send reset link</button>
        </form>
        <a class="back" href="account.php?tab=login">&larr; Back to log in</a>
      <?php else: ?>
      <form method="post" action="account.php?tab=<?= $tab ?>">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="<?= $tab ?>">
        <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= sma_e($next) ?>"><?php endif; ?>
        <label>Email</label>
        <input type="email" name="email" autocomplete="email" required>
        <label>Password<?= $tab === 'register' ? ' (min 8 characters)' : '' ?></label>
        <input type="password" name="password" autocomplete="<?= $tab === 'register' ? 'new-password' : 'current-password' ?>" required>
        <button class="btn" type="submit"><?= $tab === 'register' ? 'Create free account' : 'Log in' ?></button>
      </form>
      <?php if ($tab === 'login'): ?>
        <a class="back" href="account.php?tab=forgot" style="margin-top:10px">Forgot password?</a>
      <?php endif; ?>
      <?php endif; ?>
      <a class="back" href="index.php">&larr; Back to charts</a>
    <?php endif; ?>
  </div>
</div>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
