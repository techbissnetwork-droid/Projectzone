<?php
declare(strict_types=1);

/**
 * Every email this site tried to send.
 *
 * "My alerts stopped", "my alerts never started" and "nothing on my watchlist
 * has moved" produced the same evidence from this admin panel: none. A failed
 * send reached error_log() and nowhere else, which on shared hosting is
 * nowhere at all. This is the record - who it went to, what it was about,
 * whether it left, and if it did not, the reason in the words the mail server
 * used.
 *
 * Not deduplicated, unlike the error log. That one answers "what is broken";
 * this one answers "did THAT message go", and folding two sends to the same
 * member into one row would remove the only thing anyone comes here for.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\EmailLog;
use SignalMasterAi\Mailer;

Auth::requireLogin();

$kind = (string)($_GET['kind'] ?? '');
$failed = ($_GET['failed'] ?? '') === '1';
$rows = EmailLog::recent(300, $kind, $failed);
$day = EmailLog::summary(24);
$week = EmailLog::summary(24 * 7);
$kinds = EmailLog::kinds();

// Waiting to be sent, if a bundling window is in use - otherwise "nothing sent
// in the last hour" reads as a fault when it is the setting working.
$holdMin = (int)Database::setting('alert_bundle_min', '0');
$waiting = $holdMin > 0 ? \SignalMasterAi\AlertQueue::waiting() : 0;

admin_header('Email log', 'emails',
    'One row per message this site tried to send, with the reason when it failed. If mail is not '
    . 'arriving, start here and then check the DNS records further down.');
show_flash();
?>
<div class="cards">
  <div class="card"><div class="num" style="color:var(--up)"><?= $day['sent'] ?></div>
    <div class="lbl">Delivered in 24h</div></div>
  <div class="card"><div class="num" style="color:<?= $day['failed'] > 0 ? 'var(--down)' : 'var(--text)' ?>">
    <?= $day['failed'] ?></div><div class="lbl">Failed in 24h</div></div>
  <div class="card"><div class="num"><?= $week['sent'] ?></div><div class="lbl">Delivered this week</div></div>
  <?php if ($holdMin > 0): ?>
    <div class="card"><div class="num"><?= $waiting ?></div>
      <div class="lbl">Held for the <?= $holdMin ?>m window</div></div>
  <?php endif; ?>
</div>

<?php if (!Mailer::enabled()): ?>
  <div class="error"><strong>Email sending is switched off.</strong> Nothing below can
    leave until a method is chosen under <a href="settings.php#alerts" style="color:inherit">Alerts
    &rsaquo; Email / SMTP</a>. Members who ticked "email me" are getting nothing and are told nothing.</div>
<?php endif; ?>

<?php admin_panel('What was sent, and what was not',
    'One row per attempt, not per problem. A failure carries the reason the mail server gave, and '
    . 'alert rows name the pairs they were about - so "I ticked watch-all and got nothing" can be '
    . 'answered by reading a row. Rows are dropped after '
    . (int)Database::setting('email_log_days', '30') . ' days.'); ?>

  <form method="get" action="emails.php" class="inline-form" style="margin:0 0 12px">
    <select name="kind" aria-label="Filter by kind of email" onchange="this.form.submit()">
      <option value="">Every kind</option>
      <?php foreach ($kinds as $k): ?>
        <option value="<?= sma_e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= sma_e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="chk"><input type="checkbox" name="failed" value="1" <?= $failed ? 'checked' : '' ?>
      onchange="this.form.submit()"> Failures only</label>
    <?php if ($kind !== '' || $failed): ?>
      <a class="btn small gray" href="emails.php">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <?php admin_empty($kind !== '' || $failed ? 'Nothing matches this filter' : 'No mail recorded yet',
        $kind !== '' || $failed
          ? 'Clear the filter above to see every attempt.'
          : 'Mail is recorded from the moment it is attempted - this page cannot show what was sent before it existed.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>When</th><th>To</th><th>Kind</th><th>Subject</th><th>Result</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr<?= (int)$r['ok'] === 0 ? ' class="dim"' : '' ?>>
      <td class="muted" style="white-space:nowrap"><?= sma_e(gmdate('M j, H:i', (int)$r['at'])) ?></td>
      <td><?= sma_e((string)$r['recipient']) ?></td>
      <td><span class="badge"><?= sma_e((string)$r['kind']) ?></span></td>
      <td>
        <?= sma_e((string)$r['subject']) ?>
        <?php if ((string)$r['context'] !== ''): ?>
          <div class="muted small"><?= sma_e((string)$r['context']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <?php if ((int)$r['ok'] === 1): ?>
          <span style="color:var(--up);font-weight:700">sent</span>
        <?php else: ?>
          <span style="color:var(--down);font-weight:700">failed</span>
          <div class="small" style="margin-top:2px"><?= sma_e((string)$r['reason']) ?></div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>

  <p class="hint" style="margin-top:14px"><strong>"Sent" means accepted, not read.</strong> It says
    the mail server took the message &mdash; it cannot say the inbox kept it. A run of accepted
    alerts that a member insists never arrived is a spam-folder or reputation problem rather than a
    sending one, and the panel below is where that gets fixed.</p>
<?php admin_panel_end(); ?>

<?php
// Deliverability. The one thing that decides whether accepted mail arrives.
//
// Every check here reads live DNS for the domain this site actually sends
// from, because a guide that says "add an SPF record" without saying whether
// you already have one, and what is in it, is a page an operator reads once
// and cannot act on.
$fromEmail = Database::setting('smtp_from_email', '');
$sendDomain = strtolower(trim((string)substr(strrchr($fromEmail, '@') ?: '', 1)));
$dns = static function (string $name, string $type) {
    try {
        $r = @dns_get_record($name, $type === 'TXT' ? DNS_TXT : DNS_A);
        return is_array($r) ? $r : [];
    } catch (\Throwable $e) {
        return [];
    }
};
$spf = $dmarc = [];
$dkimHint = '';
if ($sendDomain !== '' && !filter_var($sendDomain, FILTER_VALIDATE_IP)) {
    foreach ($dns($sendDomain, 'TXT') as $rec) {
        $txt = (string)($rec['txt'] ?? '');
        if (stripos($txt, 'v=spf1') === 0) {
            $spf[] = $txt;
        }
    }
    foreach ($dns('_dmarc.' . $sendDomain, 'TXT') as $rec) {
        $txt = (string)($rec['txt'] ?? '');
        if (stripos($txt, 'v=DMARC1') === 0) {
            $dmarc[] = $txt;
        }
    }
    // DKIM lives at a selector the mail provider chooses, so it cannot be
    // looked up blind. The common ones are worth trying rather than telling
    // an operator "check your provider" and leaving it there.
    foreach (['default', 'mail', 'google', 'k1', 's1', 'selector1', 'dkim', 'smtp'] as $sel) {
        if ($dns($sel . '._domainkey.' . $sendDomain, 'TXT')) {
            $dkimHint = $sel;
            break;
        }
    }
}
$smtpHost = strtolower(Database::setting('smtp_host', ''));
$known = ['sendgrid' => 'include:sendgrid.net', 'mailgun' => 'include:mailgun.org',
          'amazonaws' => 'include:amazonses.com', 'postmark' => 'include:spf.mtasv.net',
          'sparkpost' => 'include:sparkpostmail.com', 'zoho' => 'include:zoho.com',
          'google' => 'include:_spf.google.com', 'gmail' => 'include:_spf.google.com',
          'brevo' => 'include:spf.brevo.com', 'sendinblue' => 'include:spf.sendinblue.com',
          'office365' => 'include:spf.protection.outlook.com',
          'outlook' => 'include:spf.protection.outlook.com'];
$suggest = 'include:' . ($smtpHost !== '' ? $smtpHost : 'your-mail-host');
foreach ($known as $needle => $inc) {
    if ($smtpHost !== '' && str_contains($smtpHost, $needle)) {
        $suggest = $inc;
        break;
    }
}
?>
<?php admin_panel('Make your mail arrive: SPF, DKIM, DMARC',
    'Checked live against the domain you actually send from, every time this page loads. Without '
    . 'these three records a mailbox provider has no way to tell your alerts from someone forging '
    . 'your domain - which is the single biggest reason accepted mail still lands in spam.'); ?>
  <?php if ($sendDomain === ''): ?>
    <p class="hint">Set a From address under <a href="settings.php#alerts">Alerts &rsaquo; Email /
      SMTP</a> first &mdash; these records belong to whatever domain you send from, and there is
      nothing to check until there is one.</p>
  <?php else: ?>
  <p class="hint">You send as <strong><?= sma_e($fromEmail) ?></strong>, so these records belong to
    <strong><?= sma_e($sendDomain) ?></strong> and are added wherever that domain's DNS lives &mdash;
    usually the registrar, not this server.</p>

  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Record</th><th>Status</th><th>What to add</th></tr>
    <tr>
      <td><strong>SPF</strong><div class="muted small">Who may send as you</div></td>
      <td><?php if ($spf): ?><span style="color:var(--up);font-weight:700">found</span>
            <div class="muted small" style="word-break:break-all"><?= sma_e(implode(' / ', $spf)) ?></div>
          <?php else: ?><span style="color:var(--down);font-weight:700">missing</span><?php endif; ?></td>
      <td>
        <?php if ($spf): ?>
          <div class="small">One SPF record only &mdash; a second one invalidates both. If your
            sender is not listed above, add <code><?= sma_e($suggest) ?></code> inside the existing
            record rather than creating another.</div>
        <?php else: ?>
          <div class="small">A <code>TXT</code> record on <code><?= sma_e($sendDomain) ?></code>:</div>
          <pre class="hook" style="white-space:pre-wrap;margin-top:6px"><?= sma_e(
            'v=spf1 ' . $suggest . ' ~all') ?></pre>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td><strong>DKIM</strong><div class="muted small">Signs each message</div></td>
      <td><?php if ($dkimHint !== ''): ?><span style="color:var(--up);font-weight:700">found</span>
            <div class="muted small">selector <code><?= sma_e($dkimHint) ?></code></div>
          <?php else: ?><span style="color:var(--warn);font-weight:700">not detected</span><?php endif; ?></td>
      <td><div class="small">DKIM cannot be written by hand: the key pair is generated by whoever
        actually sends the mail, and only they hold the private half. Find "DKIM" or "domain
        authentication" in <?= $smtpHost !== '' ? '<strong>' . sma_e($smtpHost) . '</strong>' : 'your mail provider' ?>,
        and it will give you the exact record to paste. This check tries the common selector names,
        so "not detected" can also mean yours uses an unusual one.</div></td>
    </tr>
    <tr>
      <td><strong>DMARC</strong><div class="muted small">What to do with fakes</div></td>
      <td><?php if ($dmarc): ?><span style="color:var(--up);font-weight:700">found</span>
            <div class="muted small" style="word-break:break-all"><?= sma_e(implode(' / ', $dmarc)) ?></div>
          <?php else: ?><span style="color:var(--warn);font-weight:700">missing</span><?php endif; ?></td>
      <td>
        <?php if (!$dmarc): ?>
          <div class="small">A <code>TXT</code> record on
            <code>_dmarc.<?= sma_e($sendDomain) ?></code>. Start at <code>p=none</code>, which
            asks for reports and rejects nothing &mdash; going straight to <code>p=reject</code>
            before SPF and DKIM both pass can silently bin your own mail:</div>
          <pre class="hook" style="white-space:pre-wrap;margin-top:6px"><?= sma_e(
            'v=DMARC1; p=none; rua=mailto:' . ($fromEmail !== '' ? $fromEmail : 'you@' . $sendDomain)) ?></pre>
        <?php else: ?>
          <div class="small">Once SPF and DKIM have passed for a few weeks, tighten
            <code>p=none</code> to <code>p=quarantine</code> and then <code>p=reject</code>.</div>
        <?php endif; ?>
      </td>
    </tr>
  </table>
  </div>

  <p class="hint" style="margin-top:12px"><strong>Order matters.</strong> SPF first, then DKIM, then
    DMARC at <code>p=none</code> &mdash; DMARC judges the other two, so publishing it first tells
    mailbox providers to enforce rules you have not set up yet. DNS changes take minutes to hours to
    spread; this panel reads live records each time you open it, so come back and check rather than
    trusting that it saved.</p>
  <p class="hint"><strong>Send from your own domain.</strong> A From address at gmail.com or
    outlook.com cannot pass DKIM for mail this server sends, because you cannot publish records on
    someone else's domain &mdash; and those providers publish DMARC policies that tell everyone to
    reject exactly that. Use an address on a domain you control.</p>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
