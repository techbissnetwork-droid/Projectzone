<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$project = my_project();
client_head('My project', 'project.php');

if (!$project) {
    ?>
    <div class="panel"><div class="empty"><strong>No project linked yet</strong>
      <p>Nothing has been linked to your account. Email
        <a href="mailto:<?= esc(setting('site.support_email')) ?>" style="color:var(--acc)">
          <?= esc(setting('site.support_email')) ?></a> and we will sort it out.</p></div></div>
    <?php
    client_foot();
    exit;
}
?>

<div class="hero-line">
  <h1><?= esc($project['name']) ?></h1>
  <p>Everything we run for you, and when each part next needs renewing. We handle the renewals &mdash;
     this is here so you can see them.</p>
</div>

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
      <header><h2>The site</h2></header>
      <div class="pad">
        <div class="kv"><span>Project</span><strong><?= esc($project['name']) ?></strong></div>
        <div class="kv"><span>Reference</span><strong><?= esc($project['reference'] ?: '—') ?></strong></div>
        <div class="kv"><span>Status</span><strong><?= status_pill($project['status']) ?></strong></div>
        <div class="kv"><span>Live since</span><strong><?= esc(date_human($project['launched_on'])) ?></strong></div>
        <div class="kv"><span>Care plan</span><strong><?= esc($project['care_plan'] ?: 'None') ?></strong></div>
<?php if ($project['care_renews_on']): ?>
        <div class="kv"><span>Care renews</span><strong><?= esc(date_human($project['care_renews_on'])) ?></strong></div>
<?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <header><h2>Domain</h2></header>
      <div class="pad">
        <div class="kv"><span>Domain</span><strong>
<?php if ($project['domain']): ?>
          <a href="https://<?= esc($project['domain']) ?>" rel="noopener" target="_blank"><?= esc($project['domain']) ?></a>
<?php else: ?>—<?php endif; ?></strong></div>
        <div class="kv"><span>Registrar</span><strong><?= esc($project['domain_registrar'] ?: '—') ?></strong></div>
        <div class="kv"><span>Renews</span><strong><?= esc(date_human($project['domain_expires_on'])) ?></strong></div>
        <div class="kv"><span>Registered to</span><strong>You</strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>Hosting</h2></header>
      <div class="pad">
        <div class="kv"><span>Provider</span><strong><?= esc($project['hosting_provider'] ?: '—') ?></strong></div>
        <div class="kv"><span>Plan</span><strong><?= esc($project['hosting_plan'] ?: '—') ?></strong></div>
        <div class="kv"><span>Renews</span><strong><?= esc(date_human($project['hosting_expires_on'])) ?></strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>SSL and email</h2></header>
      <div class="pad">
        <div class="kv"><span>Certificate from</span><strong><?= esc($project['ssl_provider'] ?: '—') ?></strong></div>
        <div class="kv"><span>Certificate expires</span><strong><?= esc(date_human($project['ssl_expires_on'])) ?></strong></div>
        <div class="kv"><span>Email provider</span><strong><?= esc($project['email_provider'] ?: '—') ?></strong></div>
        <div class="kv"><span>Mailboxes</span><strong><?= (int) $project['email_accounts'] ?: '—' ?></strong></div>
        <div class="kv"><span>Email renews</span><strong><?= esc(date_human($project['email_expires_on'])) ?></strong></div>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <header><h2>Something to change?</h2></header>
      <div class="pad" style="display:grid;gap:8px">
        <a class="btn" href="support.php?action=new&amp;category=maintenance">Ask for a change</a>
        <a class="btn ghost" href="support.php?action=new&amp;category=upgrade">Discuss an upgrade</a>
        <a class="btn ghost" href="support.php?action=new&amp;category=support">Report a problem</a>
      </div>
    </div>

    <div class="panel">
      <header><h2>Who owns what</h2></header>
      <div class="pad" style="color:var(--mute);font-size:14px">
        <p style="margin-bottom:12px">Your domain, hosting and email accounts are registered in
          your business name, not ours. We do the admin, you own the asset.</p>
        <p>If you ever want to move away, nothing has to be transferred &mdash; ask us for the
          logins and they are yours.</p>
      </div>
    </div>

    <div class="panel">
      <header><h2>History</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:14px;margin-bottom:12px">Every backup, update and fix
          we have made is listed under Maintenance.</p>
        <a class="btn ghost sm" href="maintenance.php">See maintenance history</a>
      </div>
    </div>
  </div>
</div>

<?php client_foot(); ?>
