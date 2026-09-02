<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me       = current_user();
$projects = projects_for_user((int) $me['id']);
$project  = $projects[0] ?? null;
$tickets  = db_all('SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$me['id']]);
$logs     = $project
    ? db_all('SELECT * FROM maintenance_logs WHERE project_id = ? AND visible_to_client = 1
              ORDER BY performed_on DESC, id DESC LIMIT 5', [$project['id']])
    : [];

client_head('Overview', 'index.php');
?>

<div class="hero-line">
  <h1>Hello, <?= esc(explode(' ', $me['name'])[0]) ?>.</h1>
<?php if ($project): ?>
  <p>Here is where <strong><?= esc($project['name']) ?></strong> stands today &mdash; what renews when,
     what we have done recently, and anything still open between us.</p>
<?php else: ?>
  <p>Your account is set up. Once we add your project you will see its renewal dates and history here.</p>
<?php endif; ?>
</div>

<?php if ($project): ?>
<div class="renewals">
<?php foreach (project_renewals($project) as $r): ?>
  <div class="ren <?= esc($r['state']) ?>">
    <h6><?= esc($r['label']) ?></h6>
    <b><?= esc(date_human($r['date'])) ?></b>
    <span class="pill <?= esc($r['state']) ?>"><?= esc($r['human']) ?></span>
<?php if ($r['provider']): ?>    <small><?= esc($r['provider']) ?></small><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>Recent maintenance</h2>
        <div class="acts"><a class="btn ghost sm" href="maintenance.php">See all</a></div></header>
<?php if (!$logs): ?>
      <div class="empty"><strong>Nothing logged yet</strong>
        <p>Work we do on your site shows up here as we do it.</p></div>
<?php else: ?>
      <div class="pad"><div class="thread">
<?php foreach ($logs as $l): ?>
        <div class="msg">
          <div class="who"><b><?= esc($l['title']) ?></b>
            <span class="pill"><?= esc(ucfirst($l['kind'])) ?></span>
            <?= esc(date_human($l['performed_on'])) ?></div>
<?php if ($l['body']): ?>          <p><?= esc($l['body']) ?></p><?php endif; ?>
        </div>
<?php endforeach; ?>
      </div></div>
<?php endif; ?>
    </div>

    <div class="panel">
      <header><h2>Open with us</h2>
        <div class="acts"><a class="btn sm" href="support.php?action=new">Raise a request</a></div></header>
<?php if (!$tickets): ?>
      <div class="empty"><strong>Nothing open</strong>
        <p>Need a change, a fix or an upgrade? Raise a request and it comes straight to the people
           who built your site.</p>
        <p style="margin-top:16px"><a class="btn" href="support.php?action=new">Raise a request</a></p></div>
<?php else: ?>
      <div class="pad"><div class="ticketlist">
<?php foreach ($tickets as $t): ?>
        <a class="ticketrow" href="ticket.php?id=<?= (int) $t['id'] ?>">
          <div><b><?= esc($t['subject']) ?></b>
            <div class="meta"><?= esc($t['reference']) ?> &middot; <?= esc(ucfirst($t['category'])) ?>
              &middot; <?= esc(date_human($t['created_at'])) ?></div></div>
          <div class="right"><?= status_pill($t['status']) ?></div>
        </a>
<?php endforeach; ?>
      </div></div>
<?php endif; ?>
    </div>
  </div>

  <div>
    <div class="panel">
      <header><h2>Your project</h2></header>
      <div class="pad">
        <div class="kv"><span>Name</span><strong><?= esc($project['name']) ?></strong></div>
<?php if ($project['domain']): ?>
        <div class="kv"><span>Domain</span>
          <strong><a href="https://<?= esc($project['domain']) ?>" rel="noopener" target="_blank">
            <?= esc($project['domain']) ?></a></strong></div>
<?php endif; ?>
        <div class="kv"><span>Status</span><strong><?= status_pill($project['status']) ?></strong></div>
<?php if ($project['launched_on']): ?>
        <div class="kv"><span>Live since</span><strong><?= esc(date_human($project['launched_on'])) ?></strong></div>
<?php endif; ?>
        <div class="kv"><span>Care plan</span><strong><?= esc($project['care_plan'] ?: 'None') ?></strong></div>
        <p style="margin-top:14px"><a class="btn ghost sm" href="project.php">All the details</a></p>
      </div>
    </div>

    <div class="panel">
      <header><h2>Need something?</h2></header>
      <div class="pad" style="display:grid;gap:8px">
        <a class="btn" href="support.php?action=new&amp;category=support">Report a problem</a>
        <a class="btn ghost" href="support.php?action=new&amp;category=maintenance">Ask for a change</a>
        <a class="btn ghost" href="support.php?action=new&amp;category=upgrade">Discuss an upgrade</a>
      </div>
    </div>

    <div class="panel">
      <header><h2>Reach us</h2></header>
      <div class="pad">
        <div class="kv"><span>Support</span><strong><?= esc(setting('site.support_email')) ?></strong></div>
<?php if (setting('site.phone')): ?>
        <div class="kv"><span>Phone</span><strong><?= esc(setting('site.phone')) ?></strong></div>
<?php endif; ?>
        <div class="kv"><span>Hours</span><strong><?= esc(setting('site.hours')) ?></strong></div>
      </div>
    </div>
  </div>
</div>

<?php if (count($projects) > 1): ?>
<div class="panel" style="margin-top:18px">
  <header><h2>Your other projects</h2></header>
  <div class="tablewrap"><table>
    <thead><tr><th>Project</th><th>Domain</th><th class="right">Status</th></tr></thead>
    <tbody>
<?php foreach (array_slice($projects, 1) as $p): ?>
      <tr>
        <td><a class="rowlink" href="project.php?project=<?= (int) $p['id'] ?>"><?= esc($p['name']) ?></a></td>
        <td><?= esc($p['domain'] ?: '—') ?></td>
        <td class="right"><?= status_pill($p['status']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="panel">
  <div class="empty">
    <strong>No project linked yet</strong>
    <p>Your login works, but nothing has been linked to it. If that looks wrong, email
      <a href="mailto:<?= esc(setting('site.support_email')) ?>" style="color:var(--acc)">
        <?= esc(setting('site.support_email')) ?></a> and we will sort it out.</p>
  </div>
</div>
<?php endif; ?>

<?php client_foot(); ?>
