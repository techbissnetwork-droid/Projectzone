<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$projects = Database::all('SELECT * FROM projects WHERE user_id = :u ORDER BY status, name', ['u' => (int)$me['id']]);

$PAGE_TITLE = 'My sites';
$AREA = 'client';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if (!$projects): ?>
  <section class="card"><div class="empty"><b>No sites yet</b>
    <p>When we start building for you, the site appears here with its domain, hosting and SSL dates.</p>
    <a class="btn sm" href="tickets.php?action=new">Talk to us</a></div></section>
<?php else: ?>
  <div class="stack">
    <?php foreach ($projects as $p): ?>
      <section class="card">
        <div class="card__head">
          <h2><a class="linkish" href="project.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h2>
          <span class="badge <?= e(status_tone($p['status'])) ?>"><?= e(label($p['status'])) ?></span>
          <?php if ($p['site_url']): ?>
            <a class="btn ghost sm" style="margin-left:auto" href="<?= e($p['site_url']) ?>" target="_blank" rel="noopener noreferrer">Visit ↗</a>
          <?php endif; ?>
        </div>
        <div class="card__body">
          <div class="grid g3">
            <?php foreach ([['Domain', 'domain_expires_on', $p['domain_name']],
                            ['Hosting', 'hosting_expires_on', $p['hosting_provider']],
                            ['SSL', 'ssl_expires_on', $p['ssl_issuer']]] as [$lbl, $col, $detail]):
              $st = expiry_state($p[$col]); $d = days_until($p[$col]);
              $pct = $d === null ? 0 : ($d < 0 ? 100 : max(0, min(100, (int)round((1 - $d / 365) * 100)))); ?>
              <div class="expiry <?= e($st) ?>">
                <div class="expiry__top"><b><?= e($lbl) ?></b><span class="expiry__date"><?= e(fdate($p[$col])) ?></span></div>
                <div class="expiry__bar" aria-hidden="true"><i style="--w:<?= $pct ?>%"></i></div>
                <p class="expiry__note"><?= e(expiry_label($p[$col])) ?><?= $detail ? ' · ' . e($detail) : '' ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="rowline" style="margin-top:16px">
            <a class="btn ghost sm" href="project.php?id=<?= (int)$p['id'] ?>">History &amp; details</a>
            <a class="btn ghost sm" href="tickets.php?action=new&project=<?= (int)$p['id'] ?>&category=maintenance">Request maintenance</a>
            <a class="btn ghost sm" href="tickets.php?action=new&project=<?= (int)$p['id'] ?>&category=upgrade">Request an upgrade</a>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
