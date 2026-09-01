<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\NewsFetcher;

Auth::requireLogin();
$pdo = Database::pdo();
$error = null;
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $kind = ($_POST['kind'] ?? 'rss') === 'ff_calendar' ? 'ff_calendar' : 'rss';
        if ($name === '' || mb_strlen($name) > 64) {
            $error = 'Source name is required (max 64 chars).';
        } elseif (!preg_match('#^https://[^\s]+$#i', $url)) {
            $error = 'Feed URL must start with https://';
        } else {
            try {
                $pdo->prepare('INSERT INTO news_sources (name, kind, url) VALUES (?, ?, ?)')
                    ->execute([$name, $kind, mb_substr($url, 0, 255)]);
                flash("Source \"$name\" added - it will be fetched automatically.");
                header('Location: news.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Could not add source (duplicate URL?).';
            }
        }
    }

    if ($act === 'toggle' || $act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'toggle') {
            $pdo->prepare('UPDATE news_sources SET enabled = 1 - enabled, fetched_at = 0 WHERE id = ?')->execute([$id]);
            flash('Source updated.');
        } else {
            $stmt = $pdo->prepare('SELECT name FROM news_sources WHERE id = ?');
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();
            if ($name !== false) {
                $pdo->prepare('DELETE FROM news_sources WHERE id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM news_items WHERE source_name = ?')->execute([$name]);
                flash("Source \"$name\" and its stored headlines deleted.");
            }
        }
        header('Location: news.php');
        exit;
    }

    if ($act === 'refresh') {
        // Force an immediate refresh of every enabled source.
        $pdo->exec('UPDATE news_sources SET fetched_at = 0 WHERE enabled = 1');
        $report = (new NewsFetcher())->refreshIfStale();
    }

    if ($act === 'clear') {
        $pdo->exec('DELETE FROM news_items');
        $pdo->exec('DELETE FROM calendar_events');
        flash('Stored headlines and events cleared - they will be refetched automatically.');
        header('Location: news.php');
        exit;
    }
}

$sources = $pdo->query('SELECT * FROM news_sources ORDER BY name')->fetchAll();
$itemCount = (int)$pdo->query('SELECT COUNT(*) FROM news_items')->fetchColumn();
$eventCount = (int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn();
$latest = $pdo->query('SELECT * FROM news_items ORDER BY published_at DESC LIMIT 12')->fetchAll();
$csrf = Auth::csrfToken();

admin_header('News sources', 'news',
    'Where headlines and calendar events come from. This runs on its own: each enabled source is '
    . 'refetched when its cache expires, old headlines are pruned, and the public page refreshes '
    . 'itself. Only the headline, link and a short excerpt are stored, always attributed to the '
    . 'original publisher.');
show_flash();
if ($error) {
    echo '<div class="error">' . sma_e($error) . '</div>';
}
?>
<?php if ($report !== null): ?>
<?php admin_panel('Refresh report', 'What each source said when it was last asked, just now.'); ?>
  <?php if (!$report): ?>
    <p class="hint">Every cache was already fresh, so nothing needed refetching.</p>
  <?php else: ?>
  <div class="tbl-scroll">
    <table class="grid">
      <tr class="head"><th style="width:280px">Source</th><th>Result</th></tr>
      <?php foreach ($report as $src => $res): ?>
        <tr><td><strong><?= sma_e($src) ?></strong></td><td class="muted"><?= sma_e($res) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php endif; ?>

<div class="cards">
  <div class="card"><div class="num"><?= count(array_filter($sources, fn($s) => $s['enabled'])) ?></div><div class="lbl">Enabled sources</div></div>
  <div class="card"><div class="num"><?= number_format($itemCount) ?></div><div class="lbl">Headlines stored</div></div>
  <div class="card"><div class="num"><?= number_format($eventCount) ?></div><div class="lbl">Calendar events stored</div></div>
</div>

<?php // The sources are the object this page is about, so they come first and
      // everything that acts on them - adding one, refetching, clearing the
      // store - lives in the same block rather than scattered above and below
      // the table the way it was.
      admin_panel('Feeds', 'Every source the site pulls from. A disabled source is left alone entirely - not fetched, not shown, not scored.'); ?>
  <?php if (!$sources): ?>
    <?php admin_empty('No sources yet', 'Add an RSS or Atom feed below and the site starts pulling headlines on its own schedule.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Source</th><th>Type</th><th>URL</th><th>Status</th><th>Last fetch</th><th style="width:170px">Actions</th></tr>
    <?php foreach ($sources as $s): ?>
    <tr<?= $s['enabled'] ? '' : ' class="dim"' ?>>
      <td><strong><?= sma_e($s['name']) ?></strong></td>
      <td><span class="badge off"><?= $s['kind'] === 'ff_calendar' ? 'calendar' : 'rss' ?></span></td>
      <td class="muted" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sma_e($s['url']) ?></td>
      <td><span class="badge <?= $s['enabled'] ? 'on' : 'off' ?>"><?= $s['enabled'] ? 'ENABLED' : 'DISABLED' ?></span></td>
      <td class="muted" style="white-space:nowrap"><?= $s['fetched_at'] ? gmdate('H:i:s', (int)$s['fetched_at']) . ' UTC' : 'never' ?></td>
      <td>
        <form class="inline-form" method="post" action="news.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn small gray" type="submit"><?= $s['enabled'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form class="inline-form" method="post" action="news.php"
              onsubmit="return confirm('Delete source <?= sma_e($s['name']) ?> and its stored headlines?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn small danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>

  <details class="acc">
    <summary>Add a source</summary>
    <div class="acc-body">
      <form method="post" action="news.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="add">
        <div class="row2">
          <div><label>Name</label><input aria-label="Name" type="text" name="name" placeholder="Reuters Markets"></div>
          <div><label>Type</label>
            <select aria-label="Type" name="kind">
              <option value="rss">RSS / Atom feed</option>
              <option value="ff_calendar">ForexFactory calendar XML</option>
            </select>
          </div>
        </div>
        <label>Feed URL (https)</label>
        <input aria-label="Feed URL (https)" type="url" name="url" placeholder="https://example.com/feed">
        <p style="margin-top:14px"><button class="btn" type="submit">Add source</button></p>
        <p class="hint">Stick to reputable publishers whose feeds are intended for syndication.</p>
      </form>
    </div>
  </details>

  <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px">
    <form class="inline-form" method="post" action="news.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="refresh">
      <button class="btn gray" type="submit">Refresh all now</button>
    </form>
    <form class="inline-form" method="post" action="news.php"
          onsubmit="return confirm('Clear all stored headlines and events?')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="clear">
      <button class="btn danger" type="submit">Clear stored items</button>
    </form>
  </div>
  <p class="hint">Refreshing by hand is only ever a shortcut - cron refetches each source when its
    cache expires, and an ordinary visit to the public site will do it too.</p>
<?php admin_panel_end(); ?>

<?php admin_panel('Latest stored headlines', 'The most recent items in the store, newest first. This is what the public news panel is reading from.'); ?>
<?php if (!$latest): ?>
  <?php admin_empty('Nothing stored yet', 'Press "Refresh all now" above, or just open the public site - the first visitor triggers a fetch.'); ?>
<?php else: ?>
<div class="tbl-scroll">
<table class="grid">
  <tr class="head"><th>Published (UTC)</th><th>Source</th><th>Headline</th></tr>
  <?php foreach ($latest as $n): ?>
  <tr>
    <td class="muted" style="white-space:nowrap"><?= gmdate('m-d H:i', (int)$n['published_at']) ?></td>
    <td class="muted"><?= sma_e($n['source_name']) ?></td>
    <td><?php $nu = sma_href($n['url']); ?>
      <?php if ($nu !== ''): ?>
        <a href="<?= $nu ?>" target="_blank" rel="noopener" style="color:var(--text)"><?= sma_e($n['title']) ?></a>
      <?php else: ?>
        <?= sma_e($n['title']) ?> <span class="hint">(no usable link)</span>
      <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
