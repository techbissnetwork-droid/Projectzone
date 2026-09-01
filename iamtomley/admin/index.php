<?php
$__page = 'dashboard';
$title = 'Dashboard';
require_once __DIR__ . '/includes/head.php';

$pTotal  = (int) db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$pActive = (int) db()->query("SELECT COUNT(*) FROM projects WHERE is_active=1")->fetchColumn();
$gTotal  = (int) db()->query("SELECT COUNT(*) FROM games")->fetchColumn();
$gActive = (int) db()->query("SELECT COUNT(*) FROM games WHERE is_active=1")->fetchColumn();
$sTotal  = (int) db()->query("SELECT COUNT(*) FROM stats")->fetchColumn();
?>
<div class="topbar">
  <div>
    <h1 class="page-title">Welcome back, <?= e($__user['username']) ?> 👋</h1>
    <div class="page-sub">Manage every part of your site — nothing here touches the design.</div>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/index.php')) ?>" target="_blank">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
    View live site
  </a>
</div>

<div class="tiles">
  <div class="tile"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="var(--mint)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
    <div class="n"><?= $pActive ?></div><div class="l"><?= $pTotal ?> projects · <?= $pActive ?> live</div></div>
  <div class="tile"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink)" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4m-2-2v4"/></svg></div>
    <div class="n"><?= $gActive ?></div><div class="l"><?= $gTotal ?> games · <?= $gActive ?> live</div></div>
  <div class="tile"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
    <div class="n"><?= $sTotal ?></div><div class="l">Stat widgets</div></div>
</div>

<div class="panel">
  <h2>Quick actions</h2>
  <div class="actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/settings.php')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4m0 14v4M4.2 4.2l2.8 2.8m10 10 2.8 2.8M1 12h4m14 0h4M4.2 19.8l2.8-2.8m10-10 2.8-2.8"/></svg>
      Edit site content
    </a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/projects.php')) ?>">Manage projects</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/games.php')) ?>">Manage games</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/stats.php')) ?>">Edit stats</a>
  </div>
</div>

<div class="panel">
  <h2>How it works</h2>
  <p class="small" style="line-height:1.75">
    Everything visible on your public site is stored in a database and editable here — hero text, photo,
    call-to-action buttons, the project cards &amp; their tags, the four stat widgets, your games list, the
    contact block, footer and SEO meta. The premium design (colours, animated background, glassmorphism,
    motion) lives in <span class="pill">assets/css/styles.css</span> and is never overwritten by the admin.
    The 20 playable games are self-contained and mapped by ID; you can rename, recategorise, reorder,
    hide or show them here.
  </p>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
