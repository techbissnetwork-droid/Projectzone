<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$id = (int)($_GET['id'] ?? 0);
$p  = Database::one('SELECT * FROM projects WHERE id = :id', ['id' => $id]);
require_owner($p);

$logs    = Database::all('SELECT * FROM maintenance_logs WHERE project_id = :id ORDER BY performed_on DESC, id DESC', ['id' => $id]);
$tickets = Database::all('SELECT * FROM tickets WHERE project_id = :id AND user_id = :u ORDER BY updated_at DESC',
    ['id' => $id, 'u' => (int)$me['id']]);

$PAGE_TITLE = $p['name'];
$AREA = 'client';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="projects.php">All sites</a>'
              . '<a class="btn sm" href="tickets.php?action=new&project=' . $id . '">Ask about this site</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="grid g3">
  <?php foreach ([['Domain', 'domain_expires_on', $p['domain_name'] ?: $p['domain_registrar']],
                  ['Hosting', 'hosting_expires_on', $p['hosting_provider'] ?: $p['hosting_plan']],
                  ['SSL certificate', 'ssl_expires_on', $p['ssl_issuer']]] as [$lbl, $col, $detail]):
    $st = expiry_state($p[$col]); $d = days_until($p[$col]);
    $pct = $d === null ? 0 : ($d < 0 ? 100 : max(0, min(100, (int)round((1 - $d / 365) * 100)))); ?>
    <div class="expiry <?= e($st) ?>">
      <div class="expiry__top"><b><?= e($lbl) ?></b><span class="expiry__date"><?= e(fdate($p[$col])) ?></span></div>
      <div class="expiry__bar" aria-hidden="true"><i style="--w:<?= $pct ?>%"></i></div>
      <p class="expiry__note"><?= e(expiry_label($p[$col])) ?><?= $detail ? ' · ' . e($detail) : '' ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="split">
  <section class="card">
    <div class="card__head"><h2>What we have done</h2>
      <span class="badge muted"><?= count($logs) ?> <?= count($logs) === 1 ? 'entry' : 'entries' ?></span></div>
    <div class="card__body">
      <?php if (!$logs): ?>
        <p class="hint">No maintenance recorded yet. Everything we do on this site will be listed here with the date.</p>
      <?php else: ?>
        <ul class="tl">
          <?php foreach ($logs as $l): ?>
            <li><span class="tl__dot"></span><div class="tl__body">
              <b><?= e($l['title']) ?> <span class="badge muted"><?= e(label($l['kind'])) ?></span></b>
              <?php if ($l['body']): ?><p><?= e($l['body']) ?></p><?php endif; ?>
              <time><?= e(fdate($l['performed_on'])) ?></time>
            </div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Site details</h2>
        <span class="badge <?= e(status_tone($p['status'])) ?>"><?= e(label($p['status'])) ?></span></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0"><tbody>
          <tr><th>Address</th><td class="right"><?php if ($p['site_url']): ?>
            <a class="linkish" href="<?= e($p['site_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('~^https?://~', '', $p['site_url'])) ?> ↗</a>
            <?php else: ?>—<?php endif; ?></td></tr>
          <tr><th>Package</th><td class="right"><?= e($p['package'] ?: '—') ?></td></tr>
          <tr><th>Domain</th><td class="right"><?= e($p['domain_name'] ?: '—') ?></td></tr>
          <tr><th>Hosting</th><td class="right"><?= e($p['hosting_provider'] ?: '—') ?><?= $p['hosting_plan'] ? ' · ' . e($p['hosting_plan']) : '' ?></td></tr>
          <tr><th>Business email</th><td class="right"><?= e($p['email_provider'] ?: '—') ?><?= (int)$p['email_accounts'] ? ' · ' . (int)$p['email_accounts'] . ' mailboxes' : '' ?></td></tr>
          <tr><th>Launched</th><td class="right"><?= e(fdate($p['launched_on'])) ?></td></tr>
        </tbody></table>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Need something?</h2></div>
      <div class="card__body stack">
        <a class="btn block" href="tickets.php?action=new&project=<?= $id ?>&category=maintenance">Request maintenance</a>
        <a class="btn ghost block" href="tickets.php?action=new&project=<?= $id ?>&category=upgrade">Request an upgrade</a>
        <a class="btn ghost block" href="tickets.php?action=new&project=<?= $id ?>&category=support">Report a problem</a>
        <p class="hint">We reply within one business day. Urgent issues on a live site are picked up sooner.</p>
      </div>
    </section>

    <?php if ($tickets): ?>
      <section class="card">
        <div class="card__head"><h2>Your requests for this site</h2></div>
        <div class="tablewrap"><table class="data"><tbody>
          <?php foreach ($tickets as $t): ?>
            <tr><td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
                    <span class="t-sub"><?= e(ftime($t['updated_at'])) ?></span></td>
                <td class="right"><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </section>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
