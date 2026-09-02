<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$projects = Database::all('SELECT * FROM projects WHERE user_id = :u ORDER BY status, name', ['u' => (int)$me['id']]);
$tickets  = Database::all("SELECT * FROM tickets WHERE user_id = :u AND status <> 'closed' ORDER BY updated_at DESC LIMIT 5",
    ['u' => (int)$me['id']]);
$orders   = Database::all('SELECT o.*, p.title AS product_title FROM orders o
                           LEFT JOIN products p ON p.id = o.product_id
                           WHERE o.user_id = :u ORDER BY o.created_at DESC LIMIT 4', ['u' => (int)$me['id']]);

$ids = array_column($projects, 'id');
$recent = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $recent = Database::all("SELECT m.*, p.name AS project_name FROM maintenance_logs m
                             JOIN projects p ON p.id = m.project_id
                             WHERE m.project_id IN ($in) ORDER BY m.performed_on DESC, m.id DESC LIMIT 6");
}

/* Anything that needs the client's attention, soonest first. */
$alerts = [];
foreach ($projects as $p) {
    foreach ([['Domain', 'domain_expires_on'], ['Hosting', 'hosting_expires_on'], ['SSL certificate', 'ssl_expires_on']] as [$lbl, $col]) {
        $st = expiry_state($p[$col]);
        if (in_array($st, ['warn', 'danger', 'expired'], true)) {
            $alerts[] = ['project' => $p['name'], 'what' => $lbl, 'date' => $p[$col], 'state' => $st];
        }
    }
}
usort($alerts, static fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));

$PAGE_TITLE = 'Dashboard';
$AREA = 'client';
$PAGE_ACTIONS = '<a class="btn sm" href="tickets.php?action=new">Get help</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="grid g3">
  <div class="stat"><span>Your sites</span><b><?= count($projects) ?></b><small><a class="linkish" href="projects.php">Open</a></small></div>
  <div class="stat <?= $tickets ? 'hot' : '' ?>"><span>Open requests</span><b><?= count($tickets) ?></b><small><a class="linkish" href="tickets.php">Support</a></small></div>
  <div class="stat <?= $alerts ? 'bad' : '' ?>"><span>Needs renewal</span><b><?= count($alerts) ?></b><small><?= $alerts ? 'Action needed soon' : 'Nothing expiring' ?></small></div>
</div>

<?php if ($alerts): ?>
  <div class="alert warn">
    <p><b>Renewals coming up.</b> We handle these for you — reply to any reminder if something should not renew.</p>
    <?php foreach (array_slice($alerts, 0, 4) as $a): ?>
      <p><?= e($a['what']) ?> for <b><?= e($a['project']) ?></b> — <?= e(expiry_label($a['date'])) ?> (<?= e(fdate($a['date'])) ?>)</p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="split">
  <section class="card">
    <div class="card__head"><h2>Your sites</h2><a class="btn ghost sm" href="projects.php">All sites</a></div>
    <?php if (!$projects): ?>
      <div class="empty"><b>No sites yet</b><p>Once we start a project for you it appears here with its renewal dates and history.</p></div>
    <?php else: ?>
      <div class="tablewrap"><table class="data">
        <thead><tr><th>Site</th><th>Domain</th><th>Hosting</th><th>SSL</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td><a class="linkish t-main" href="project.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a>
                <?php if ($p['site_url']): ?><span class="t-sub"><?= e(preg_replace('~^https?://~', '', $p['site_url'])) ?></span><?php endif; ?></td>
            <?php foreach (['domain_expires_on','hosting_expires_on','ssl_expires_on'] as $col):
              $st = expiry_state($p[$col]); ?>
              <td class="nowrap"><?php if (!$p[$col]): ?><span class="muted">—</span>
                <?php else: ?><span class="badge <?= $st === 'ok' ? 'ok' : ($st === 'warn' ? 'warn' : 'danger') ?>"><?= e(fdate($p[$col], 'j M y')) ?></span><?php endif; ?></td>
            <?php endforeach; ?>
            <td><span class="badge <?= e(status_tone($p['status'])) ?>"><?= e(label($p['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Open requests</h2></div>
      <?php if (!$tickets): ?>
        <div class="empty"><p>Nothing open.</p><a class="btn sm" href="tickets.php?action=new">Ask for help</a></div>
      <?php else: ?>
        <div class="tablewrap"><table class="data"><tbody>
          <?php foreach ($tickets as $t): ?>
            <tr><td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
                    <span class="t-sub"><?= e(label($t['category'])) ?> · <?= e(ftime($t['updated_at'])) ?></span></td>
                <td class="right"><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>

    <?php if ($orders): ?>
      <section class="card">
        <div class="card__head"><h2>Purchases</h2><a class="btn ghost sm" href="orders.php">All</a></div>
        <div class="tablewrap"><table class="data"><tbody>
          <?php foreach ($orders as $o): ?>
            <tr><td><span class="t-main"><?= e($o['product_title'] ?? 'Product') ?></span>
                    <span class="t-sub mono"><?= e($o['reference']) ?></span></td>
                <td class="right"><span class="badge <?= e(status_tone($o['status'])) ?>"><?= e(label($o['status'])) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php if ($recent): ?>
  <section class="card">
    <div class="card__head"><h2>Recent work on your sites</h2></div>
    <div class="card__body">
      <ul class="tl">
        <?php foreach ($recent as $r): ?>
          <li><span class="tl__dot"></span><div class="tl__body">
            <b><?= e($r['title']) ?> <span class="badge muted"><?= e(label($r['kind'])) ?></span></b>
            <?php if ($r['body']): ?><p><?= e($r['body']) ?></p><?php endif; ?>
            <time><?= e(fdate($r['performed_on'])) ?> · <?= e($r['project_name']) ?></time>
          </div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
