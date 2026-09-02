<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\MarketData;

Auth::requireLogin();
$pdo = Database::pdo();
$error = null;
$importReport = null;
$pickPreview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';

    $validTier = fn($t) => in_array($t, ['public', 'free', 'paid'], true) ? $t : 'public';

    if ($act === 'add') {
        $sym = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['symbol'] ?? '') ?? '');
        $label = trim($_POST['label'] ?? '');
        $tier = $validTier($_POST['tier'] ?? 'public');
        if ($sym === '' || strlen($sym) > 20) {
            $error = 'Symbol must be 1-20 alphanumeric characters (e.g. BTCUSDT).';
        } else {
            try {
                $pdo->prepare('INSERT INTO symbols (symbol, label, tier) VALUES (?, ?, ?)')
                    ->execute([$sym, $label !== '' ? $label : $sym, $tier]);
                flash("Symbol $sym added ($tier tier).");
                header('Location: symbols.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Could not add symbol (duplicate?).';
            }
        }
    }

    if ($act === 'set_tier') {
        $pdo->prepare('UPDATE symbols SET tier = ? WHERE id = ?')
            ->execute([$validTier($_POST['tier'] ?? 'public'), (int)($_POST['id'] ?? 0)]);
        flash('Coin tier updated.');
        header('Location: symbols.php');
        exit;
    }

    if ($act === 'engine_override') {
        // Per-coin engine settings. Blank means "use the site default", so an
        // override is only stored for values the admin actually changed.
        $id = (int)($_POST['id'] ?? 0);
        $ov = [];
        $numeric = [
            'cat_buy_threshold'      => [0.1, 50],
            'cat_sell_threshold'     => [-50, -0.1],
            'min_aligned_categories' => [1, 9],
            'chop_limit'             => [0, 100],
            'sl_atr_mult'            => [0.1, 10],
            // tp1_atr_mult only sets the chase-guard staleness threshold now -
            // published targets are always exact multiples of sl_atr_mult, so
            // there is nothing left for a per-coin tp2/tp3 to override.
            'tp1_atr_mult'           => [0.1, 10],
        ];
        foreach ($numeric as $key => [$min, $max]) {
            $raw = trim((string)($_POST[$key] ?? ''));
            if ($raw !== '' && is_numeric($raw)) {
                $ov[$key] = (string)max($min, min($max, (float)$raw));
            }
        }
        if (isset($_POST['levels_enabled_set'])) {
            $ov['levels_enabled'] = isset($_POST['levels_enabled']) ? '1' : '0';
        }
        $pdo->prepare('UPDATE symbols SET engine_json = ? WHERE id = ?')
            ->execute([$ov ? json_encode($ov) : '', $id]);
        flash($ov ? 'Per-coin engine overrides saved.' : 'Per-coin overrides cleared - this coin uses the site defaults.');
        header('Location: symbols.php?coin=' . $id . '#tune');
        exit;
    }

    if ($act === 'pick_preview') {
        // Step one of two: work out which pairs the choice covers and SHOW
        // them. It does not write anything.
        //
        // This used to import directly, and that was wrong in a way only
        // visible afterwards: "top 50 by volume" is a promise about a list
        // nobody has seen, and the exchange's book moves, so the honest
        // version of the feature is to name every coin first and let the
        // operator untick the ones they do not want. Unticking twelve rows in
        // a preview is a moment; deleting twelve published pairs afterwards
        // means they were briefly live on the site.
        $quote = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['quote'] ?? 'USDT') ?? 'USDT');
        $tier = $validTier($_POST['pick_tier'] ?? 'public');
        $mode = (string)($_POST['pick_mode'] ?? 'top');
        $topN = max(1, min(1000, (int)($_POST['pick_top'] ?? 50)));
        $group = (string)($_POST['pick_group'] ?? '');
        $enable = isset($_POST['pick_enabled']) ? 1 : 0;
        try {
            $md = new MarketData($config);
            $ranked = $md->rankedSymbols($quote);
            $chosen = [];
            if ($mode === 'group') {
                $want = array_flip(\SignalMasterAi\CoinGroups::assets($group));
                foreach ($ranked as $r) {
                    if (isset($want[$r['base']])) {
                        $chosen[] = $r;
                    }
                }
            } else {
                // Ranked by the exchange's own 24h volume, biggest first.
                foreach ($ranked as $r) {
                    if (\SignalMasterAi\CoinGroups::isStable($r['base'])) {
                        continue;      // a stablecoin against a stablecoin is a flat line
                    }
                    $chosen[] = $r;
                    if (count($chosen) >= $topN) {
                        break;
                    }
                }
            }
            // Which of these are already on the list, so the preview can say
            // so instead of quietly doing nothing for them.
            $existing = [];
            foreach ($pdo->query('SELECT symbol FROM symbols') as $row) {
                $existing[strtoupper((string)$row['symbol'])] = true;
            }
            foreach ($chosen as &$c) {
                $c['have'] = isset($existing[$c['symbol']]);
            }
            unset($c);
            $pickPreview = [
                'what'  => $mode === 'group'
                    ? (\SignalMasterAi\CoinGroups::GROUPS[$group][0] ?? $group)
                    : 'top ' . $topN . ' by 24h volume',
                'mode'  => $mode === 'group' ? 'group' : 'top',
                'group' => $group,
                'top'   => $topN,
                'quote' => $quote,
                'tier'  => $tier,
                'enable' => $enable,
                'rows'  => $chosen,
                'new'   => count(array_filter($chosen, fn($c) => !$c['have'])),
            ];
        } catch (Throwable $e) {
            $error = 'Could not read the exchange list: ' . $e->getMessage();
        }
    }

    if ($act === 'pick_import') {
        // Step two: import exactly what was ticked, and nothing else. The
        // symbols arrive from the preview form rather than being recomputed,
        // so what gets written is the list the operator actually looked at -
        // re-running the ranking here could quietly import a different set if
        // the book moved between the two clicks.
        $quote = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['quote'] ?? 'USDT') ?? 'USDT');
        $tier = $validTier($_POST['pick_tier'] ?? 'public');
        $enable = isset($_POST['pick_enabled']) ? 1 : 0;
        $what = trim((string)($_POST['pick_what'] ?? 'a chosen set'));
        $wanted = (array)($_POST['pairs'] ?? []);
        try {
            $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $ins = $pdo->prepare(
                ($isSqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE')
                . ' INTO symbols (symbol, label, enabled, tier) VALUES (?, ?, ?, ?)'
            );
            $added = 0;
            $seen = [];
            $pdo->beginTransaction();
            foreach ($wanted as $sym) {
                $sym = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$sym) ?? '');
                if (!preg_match('/^[A-Z0-9]{2,20}$/', $sym) || isset($seen[$sym])) {
                    continue;
                }
                $seen[$sym] = true;
                $base = ($quote !== '' && str_ends_with($sym, $quote))
                    ? substr($sym, 0, -strlen($quote)) : $sym;
                $ins->execute([$sym, $base . ' / ' . $quote, $enable, $tier]);
                $added += $ins->rowCount();
            }
            $pdo->commit();
            \SignalMasterAi\Audit::log('symbol.import', $what, '',
                $added . ' added, ' . count($seen) . ' selected');
            if (count($seen) === 0) {
                $importReport = 'Nothing was ticked, so nothing was imported.';
            } else {
                $importReport = ucfirst($what) . ': ' . count($seen) . ' coin(s) selected against '
                    . $quote . ', ' . $added . ' new ('
                    . ($enable ? 'enabled' : 'disabled - enable the ones you want') . ').'
                    . ($added < count($seen)
                       ? ' The other ' . (count($seen) - $added) . ' were already on the list.' : '');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $importReport = 'Import failed: ' . $e->getMessage();
        }
    }

    if ($act === 'import') {
        $quote = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['quote'] ?? 'USDT') ?? 'USDT');
        $tier = $validTier($_POST['import_tier'] ?? 'public');
        try {
            $md = new MarketData($config);
            $pairs = $md->exchangeSymbols($quote);
            $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $ins = $pdo->prepare(
                ($isSqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE') .
                ' INTO symbols (symbol, label, enabled, tier) VALUES (?, ?, ?, ?)'
            );
            $enable = isset($_POST['import_enabled']) ? 1 : 0;
            $added = 0;
            $skipped = [];
            $pdo->beginTransaction();
            foreach ($pairs as $p) {
                // Only tickers the whole app can carry. The exchange list was
                // taken at face value, so a ticker with characters the column
                // or the URL cannot hold went in mangled - one install ended
                // up with a coin called "????USDT" that answered HTTP 400 on
                // every request for the rest of its life. Rejected here, at
                // the one place it enters, and reported rather than dropped
                // silently.
                if (!preg_match('/^[A-Z0-9]{2,20}$/', $p['symbol'])) {
                    $skipped[] = $p['symbol'];
                    continue;
                }
                $ins->execute([$p['symbol'], $p['base'] . ' / ' . $p['quote'], $enable, $tier]);
                $added += $ins->rowCount();
            }
            $pdo->commit();
            $importReport = count($pairs) . " trading pairs found on the exchange for quote $quote; "
                          . "$added new symbols imported into the $tier tier ("
                          . ($enable ? 'enabled' : 'disabled - enable the ones you want') . ").";
            if ($skipped) {
                $importReport .= ' ' . count($skipped) . ' skipped - the exchange lists them under a'
                               . ' ticker that is not plain A-Z/0-9 and the app cannot request them: '
                               . implode(', ', array_slice($skipped, 0, 8))
                               . (count($skipped) > 8 ? ', …' : '') . '.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Import failed: ' . $e->getMessage();
        }
    }

    if ($act === 'enable_all' || $act === 'disable_all') {
        $pdo->exec('UPDATE symbols SET enabled = ' . ($act === 'enable_all' ? 1 : 0));
        flash($act === 'enable_all' ? 'All symbols enabled.' : 'All symbols disabled.');
        header('Location: symbols.php');
        exit;
    }

    // Remove every coin at once - the counterpart to importing hundreds of
    // them in one click, which was possible while undoing it was not.
    //
    // Cached candles go with them: they are a download, not a record, and they
    // are the bulk of the database. Stored SIGNALS do not, unless asked for
    // explicitly. A track record an admin can prune by deleting the coins that
    // went badly is not a track record, so removing published history is its
    // own decision, made deliberately, and never a side effect of tidying the
    // coin list.
    // 'delete_all' lived here and is now System > Delete data, which asks
    // which kinds as well as which coins. Nothing links to this action any
    // more, so the handler goes with the form rather than sitting behind a URL
    // an old bookmark could still post to.

    // In or out of the background scan rotation. Separate from "enabled":
    // a coin can stay listed, charted and searchable while the cron budget
    // goes to the pairs the operator actually publishes.
    if ($act === 'toggle_scan') {
        // Putting a coin back also wipes its failure record: the admin has
        // decided it is worth another try, and leaving the old strikes in
        // place would have the next single failure drop it again instantly.
        $sid = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare('SELECT symbol, scan FROM symbols WHERE id = ?');
        $q->execute([$sid]);
        $was = $q->fetch();
        $q->closeCursor();
        $pdo->prepare("UPDATE symbols SET scan = 1 - scan, scan_fails = 0, scan_note = ''
                       WHERE id = ?")->execute([$sid]);
        if ($was) {
            \SignalMasterAi\Audit::log('symbol.scan', (string)$was['symbol'],
                (int)$was['scan'] ? 'in rotation' : 'out', (int)$was['scan'] ? 'out' : 'in rotation');
        }
        flash('Scan rotation updated.');
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'toggle_priority') {
        $sid = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare('SELECT symbol, priority FROM symbols WHERE id = ?');
        $q->execute([$sid]);
        $was = $q->fetch();
        $q->closeCursor();
        $pdo->prepare('UPDATE symbols SET priority = 1 - priority WHERE id = ?')->execute([$sid]);
        if ($was) {
            \SignalMasterAi\Audit::log('symbol.priority', (string)$was['symbol'],
                (int)$was['priority'] ? 'every run' : 'rotation',
                (int)$was['priority'] ? 'rotation' : 'every run');
        }
        flash('Scan priority updated.');
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'scan_all' || $act === 'scan_none') {
        $pdo->exec('UPDATE symbols SET scan = ' . ($act === 'scan_all' ? 1 : 0)
                 . ($act === 'scan_all' ? ", scan_fails = 0, scan_note = ''" : ''));
        \SignalMasterAi\Audit::log('symbol.scan_bulk', 'all coins', '',
            $act === 'scan_all' ? 'every coin in rotation' : 'every coin out of rotation');
        flash($act === 'scan_all' ? 'Every coin is in the scan rotation.' : 'Every coin removed from the scan rotation.');
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'set_tfs') {
        // Which timeframes this coin is read on. Empty (or every frame ticked)
        // stores nothing, which means "whatever the site allows" and keeps the
        // coin following the site list if that list later changes.
        $sid = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare('SELECT symbol, tfs FROM symbols WHERE id = ?');
        $q->execute([$sid]);
        $was = $q->fetch();
        $q->closeCursor();
        if ($was) {
            $siteTfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
            $want = array_map('strval', (array)($_POST['tfs'] ?? []));
            $before = (string)$was['tfs'];
            \SignalMasterAi\Visibility::setTfs((string)$was['symbol'], $want, $siteTfs);
            $q->execute([$sid]);
            $after = (string)($q->fetch()['tfs'] ?? '');
        $q->closeCursor();
            \SignalMasterAi\Audit::log('symbol.timeframes', (string)$was['symbol'],
                $before === '' ? 'site default' : $before,
                $after === '' ? 'site default' : $after);
            flash($after === ''
                ? $was['symbol'] . ' follows the site timeframe list.'
                : $was['symbol'] . ' is read on: ' . str_replace(',', ', ', $after) . '.');
        }
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'tf_bulk') {
        // One frame, every coin. Removing the last frame from a coin would
        // leave it unreadable, so the site list is restored instead of an
        // empty one - and the flash says so rather than letting it look like
        // the change did nothing.
        $tf = (string)($_POST['tf'] ?? '');
        $on = ($_POST['mode'] ?? '') === 'on';
        $siteTfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
        $n = 0;
        if (in_array($tf, $siteTfs, true)) {
            foreach ($pdo->query('SELECT symbol FROM symbols')->fetchAll() as $row) {
                $sym = (string)$row['symbol'];
                $cur = \SignalMasterAi\Visibility::tfsFor($sym, $siteTfs);
                $has = in_array($tf, $cur, true);
                if ($on === $has) {
                    continue;
                }
                $next = $on ? array_merge($cur, [$tf]) : array_diff($cur, [$tf]);
                \SignalMasterAi\Visibility::setTfs($sym, array_values($next), $siteTfs);
                $n++;
            }
            if ($n > 0) {
                \SignalMasterAi\Audit::log('symbol.timeframes_bulk', $tf, '',
                    ($on ? 'added to' : 'removed from') . ' ' . $n . ' coin(s)');
            }
        }
        flash($n === 0 ? 'Nothing to change - every coin was already set that way.'
            : $n . ' coin(s) ' . ($on ? 'now read on ' : 'no longer read on ') . $tf . '.');
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'set_visibility') {
        // Where one coin is allowed to appear. The form posts the surfaces to
        // SHOW, because a list of ticked boxes reading "yes, here" is what the
        // operator is actually deciding; the stored value is the inverse so an
        // untouched coin stores nothing and behaves as it always did.
        $sid = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare('SELECT symbol, hide_on FROM symbols WHERE id = ?');
        $q->execute([$sid]);
        $was = $q->fetch();
        $q->closeCursor();
        if ($was) {
            $show = array_map('strval', (array)($_POST['surfaces'] ?? []));
            $hidden = array_values(array_diff(array_keys(\SignalMasterAi\Visibility::SURFACES), $show));
            $before = trim((string)$was['hide_on'], ',');
            \SignalMasterAi\Visibility::set((string)$was['symbol'], $hidden);
            \SignalMasterAi\Audit::log('symbol.visibility', (string)$was['symbol'],
                $before === '' ? 'everywhere' : 'hidden on ' . $before,
                $hidden ? 'hidden on ' . implode(',', $hidden) : 'everywhere');
            flash($hidden
                ? $was['symbol'] . ' is now hidden on: ' . implode(', ', $hidden) . '.'
                : $was['symbol'] . ' shows everywhere again.');
        }
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'vis_bulk') {
        $surface = (string)($_POST['surface'] ?? '');
        $show = ($_POST['mode'] ?? '') === 'show';
        $all = array_column($pdo->query('SELECT symbol FROM symbols')->fetchAll(), 'symbol');
        $n = \SignalMasterAi\Visibility::bulk($all, $surface, $show);
        if ($n > 0) {
            \SignalMasterAi\Audit::log('symbol.visibility_bulk', $surface, '',
                ($show ? 'shown on' : 'hidden from') . ' ' . $surface . ' for ' . $n . ' coin(s)');
        }
        $label = \SignalMasterAi\Visibility::SURFACES[$surface][0] ?? $surface;
        flash($n === 0 ? 'Nothing to change - every coin was already set that way.'
            : $n . ' coin(s) ' . ($show ? 'now show on' : 'are now hidden from') . ' ' . $label . '.');
        header('Location: symbols.php');
        exit;
    }
    if ($act === 'toggle' || $act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'toggle') {
            $q = $pdo->prepare('SELECT symbol, enabled FROM symbols WHERE id = ?');
            $q->execute([$id]);
            $was = $q->fetch();
        $q->closeCursor();
        if ($was) {
                \SignalMasterAi\Audit::log('symbol.enabled', (string)$was['symbol'],
                    (int)$was['enabled'] ? 'enabled' : 'disabled',
                    (int)$was['enabled'] ? 'disabled' : 'enabled');
            }
            $pdo->prepare('UPDATE symbols SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
            flash('Symbol updated.');
        } else {
            $stmt = $pdo->prepare('SELECT symbol FROM symbols WHERE id = ?');
            $stmt->execute([$id]);
            $sym = $stmt->fetchColumn();
            if ($sym !== false) {
                // DELETE MEANS DELETE THE COIN. NOTHING ELSE, UNLESS ASKED.
                //
                // The button used to take candles, shadow signals, members'
                // queued alerts and the board row with it every
                // time, and offered two checkboxes for the rest - so "Delete"
                // quietly removed six kinds of thing and the operator could
                // only see two of those decisions. Removing a coin from the
                // site and erasing what the site knows about it are different
                // acts, and only the first one is what the button says.
                //
                // Now the plain button removes the row and stops. Everything
                // else is ticked deliberately under Advanced, which renders
                // itself from Purge::KINDS - the same list, in the same order,
                // with the same warnings as System > Delete data. That is the
                // duplication this had: not the engine, which was already
                // shared, but a hand-written kind list here that could drift
                // from the one on the other screen, and did.
                $kinds = ['coin'];
                foreach (array_keys(\SignalMasterAi\Purge::KINDS) as $k) {
                    if ($k !== 'coin' && isset($_POST['kind'][$k])) {
                        $kinds[] = $k;
                    }
                }
                $purge = in_array('signals', $kinds, true);
                $gone = \SignalMasterAi\Purge::run($kinds, [(string)$sym]);
                $what = \SignalMasterAi\Purge::describe($gone);
                \SignalMasterAi\Audit::log('symbol.delete', (string)$sym, 'on the site',
                    'deleted with ' . $what . ($purge ? '' : ' - signals kept'));
                flash("$sym deleted - gone from the scanner, charts, backtest and track record."
                    . (count($kinds) > 1 ? ' Removed with it: ' . $what . '.' : '')
                    . ($purge
                        ? ''
                        : ' Its stored signals were kept - already hidden from the public site,'
                          . ' but still in Signal history and still training the engine.'));
            }
        }
        header('Location: symbols.php');
        exit;
    }
}

$symbols = $pdo->query('SELECT * FROM symbols ORDER BY symbol')->fetchAll();
$enabledCount = count(array_filter($symbols, fn($s) => $s['enabled']));
$scanCount = count(array_filter($symbols, fn($s) => $s['enabled'] && (int)($s['scan'] ?? 1) === 1));
$droppedCount = count(array_filter($symbols,
    fn($s) => $s['enabled'] && (int)($s['scan'] ?? 1) === 0 && ($s['scan_note'] ?? '') !== ''));
$priorityCount = count(array_filter($symbols,
    fn($s) => $s['enabled'] && (int)($s['scan'] ?? 1) === 1 && (int)($s['priority'] ?? 0) === 1));
$csrf = Auth::csrfToken();

admin_header('Coins', 'symbols',
    'Every pair the site knows about. Open one to set where it appears, which timeframes it runs '
    . 'on and how the engine treats it - all of a coin\'s switches are on its own panel.');
show_flash();
if ($error) {
    echo '<div class="error">' . sma_e($error) . '</div>';
}
if ($importReport !== null) {
    echo '<div class="flash">' . sma_e($importReport) . '</div>';
}
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($symbols) ?></div><div class="lbl">Symbols total</div></div>
  <div class="card"><div class="num"><?= $enabledCount ?></div><div class="lbl">Enabled (visible on site)</div></div>
  <div class="card"><div class="num"><?= $scanCount ?></div><div class="lbl">In the cron scan rotation</div></div>
  <?php if ($priorityCount > 0): ?>
  <div class="card"><div class="num"><?= $priorityCount ?></div><div class="lbl">Read on every run (priority)</div></div>
  <?php endif; ?>
  <?php if ($droppedCount > 0): ?>
  <div class="card"><div class="num" style="color:var(--warn)"><?= $droppedCount ?></div>
    <div class="lbl">Dropped by the scan (failing)</div></div>
  <?php endif; ?>
</div>
<div class="form-card">
  <h2 style="margin-top:0">Add symbol</h2>
  <form method="post" action="symbols.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="add">
    <div class="row2">
      <div>
        <label>Symbol (as used by the market API, e.g. BTCUSDT)</label>
        <input aria-label="Symbol (as used by the market API, e.g. BTCUSDT)" type="text" name="symbol" placeholder="BTCUSDT">
      </div>
      <div>
        <label>Display label</label>
        <input aria-label="Display label" type="text" name="label" placeholder="Bitcoin / USDT">
      </div>
      <div>
        <label>Access tier</label>
        <select aria-label="Access tier" name="tier">
          <option value="public">Public - everyone</option>
          <option value="free">Free members</option>
          <option value="paid">Paid members</option>
        </select>
      </div>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Add symbol</button></p>
    <p class="hint">The symbol must exist on the configured data source (default: Binance spot pairs).</p>
  </form>
</div>

<div class="form-card">
  <h2 style="margin-top:0">Import a set of coins</h2>
  <p class="hint">Importing the whole book is four hundred rows and an evening spent unticking. Pick
    the ones worth publishing instead &mdash; the biggest by turnover, or a sector.</p>
  <?php
  // The picker remembers what was asked for, so "same thing but 80 instead of
  // 50" is one edit rather than setting four fields again.
  $pvMode  = $pickPreview['mode'] ?? 'top';
  $pvTop   = (int)($pickPreview['top'] ?? 50);
  $pvGroup = (string)($pickPreview['group'] ?? '');
  $pvQuote = (string)($pickPreview['quote'] ?? 'USDT');
  $pvTier  = (string)($pickPreview['tier'] ?? 'paid');
  $pvEnable = $pickPreview === null ? 1 : (int)$pickPreview['enable'];
  ?>
  <form method="post" action="symbols.php#pickPreview">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="pick_preview">
    <div class="row2">
      <div>
        <label>What to import</label>
        <select name="pick_mode" id="pickMode" aria-label="What to import">
          <option value="top" <?= $pvMode === 'top' ? 'selected' : '' ?>>Biggest coins by 24h volume</option>
          <option value="group" <?= $pvMode === 'group' ? 'selected' : '' ?>>A category</option>
        </select>
      </div>
      <div id="pickTopWrap"<?= $pvMode === 'top' ? '' : ' style="display:none"' ?>>
        <label>How many (1 - 1000)</label>
        <input aria-label="How many (1 - 1000)" type="number" name="pick_top" value="<?= $pvTop ?>" min="1" max="1000">
      </div>
      <div id="pickGroupWrap"<?= $pvMode === 'group' ? '' : ' style="display:none"' ?>>
        <label>Category</label>
        <select aria-label="Category" name="pick_group">
          <?php foreach (\SignalMasterAi\CoinGroups::selectable() as $gk => [$gLabel, $gDesc, $gAssets]): ?>
            <option value="<?= sma_e($gk) ?>" <?= $pvGroup === $gk ? 'selected' : '' ?>>
              <?= sma_e($gLabel) ?> (<?= count($gAssets) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row2">
      <div>
        <label>Quote asset</label>
        <select aria-label="Quote asset" name="quote">
          <?php foreach (['USDT', 'USDC', 'FDUSD', 'BTC', 'ETH'] as $q): ?>
            <option value="<?= $q ?>" <?= $pvQuote === $q ? 'selected' : '' ?>><?= $q ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Access tier</label>
        <select aria-label="Access tier" name="pick_tier">
          <option value="public" <?= $pvTier === 'public' ? 'selected' : '' ?>>Public - everyone</option>
          <option value="free" <?= $pvTier === 'free' ? 'selected' : '' ?>>Free members</option>
          <option value="paid" <?= $pvTier === 'paid' ? 'selected' : '' ?>>Paid members</option>
        </select>
      </div>
      <div>
        <label>Enable immediately</label>
        <input aria-label="Enable immediately" type="checkbox" name="pick_enabled" <?= $pvEnable ? 'checked' : '' ?> style="width:auto">
      </div>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Show me the coins &rarr;</button></p>
    <p class="hint">Nothing is imported yet &mdash; this lists every coin the choice covers so you can
      untick the ones you do not want first.</p>
    <p class="hint"><strong>Volume, not market cap.</strong> They rank differently, and what decides
      whether this engine can read a coin is whether anyone is trading it &mdash; a large-cap with no
      turnover here produces gappy candles and unreliable levels, while a mid-cap with heavy volume
      produces clean ones. It is also the exchange's own number, so there is no third-party API to
      rate-limit you or go behind a key. Stablecoins are skipped: a stablecoin against a stablecoin
      is a flat line.</p>
    <p class="hint">Categories are a curated list, not a feed. Nothing on a spot exchange publishes a
      sector, and the engine never reads these &mdash; they only decide which rows an import creates,
      and every one is a coin you can untick afterwards. A pair the exchange does not list against
      your chosen quote asset is simply not matched.</p>
  </form>
  <script>
  (function () {
    var m = document.getElementById('pickMode');
    if (!m) return;
    function sync() {
      document.getElementById('pickTopWrap').style.display = m.value === 'top' ? '' : 'none';
      document.getElementById('pickGroupWrap').style.display = m.value === 'group' ? '' : 'none';
    }
    m.addEventListener('change', sync);
    sync();
  })();
  </script>
</div>

<?php if ($pickPreview !== null): ?>
<?php
// Volume reads as "$1.2B", not as fourteen digits. An operator scanning
// forty rows is comparing magnitudes, not counting zeros.
$vol = static function (float $v): string {
    foreach ([[1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$div, $suf]) {
        if ($v >= $div) {
            return '$' . rtrim(rtrim(number_format($v / $div, 1, '.', ''), '0'), '.') . $suf;
        }
    }
    return '$' . number_format($v, 0);
};
$px = static function (float $p): string {
    if ($p <= 0) {
        return '-';
    }
    return $p >= 1 ? number_format($p, 2) : rtrim(rtrim(number_format($p, 8), '0'), '.');
};
?>
<div class="form-card" id="pickPreview">
  <h2 style="margin-top:0">Choose which of these to import</h2>
  <p class="hint">
    <strong><?= sma_e(ucfirst($pickPreview['what'])) ?></strong> against
    <?= sma_e($pickPreview['quote']) ?> &mdash; <?= count($pickPreview['rows']) ?> coin(s) matched,
    <?= (int)$pickPreview['new'] ?> not on your list yet. Ticked rows get imported as
    <strong><?= sma_e($pickPreview['tier']) ?></strong> and
    <strong><?= $pickPreview['enable'] ? 'enabled' : 'left disabled' ?></strong>;
    change either of those above and show the list again.
  </p>
  <?php if (!$pickPreview['rows']): ?>
    <p class="hint">Nothing matched. This exchange may not list those coins against
      <?= sma_e($pickPreview['quote']) ?> &mdash; try a different quote asset.</p>
  <?php else: ?>
  <form method="post" action="symbols.php" id="pickImportForm">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="pick_import">
    <input type="hidden" name="quote" value="<?= sma_e($pickPreview['quote']) ?>">
    <input type="hidden" name="pick_tier" value="<?= sma_e($pickPreview['tier']) ?>">
    <input type="hidden" name="pick_what" value="<?= sma_e($pickPreview['what']) ?>">
    <?php if ($pickPreview['enable']): ?>
      <input type="hidden" name="pick_enabled" value="1">
    <?php endif; ?>
    <p style="margin:0 0 10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button type="button" class="btn small gray" data-pick="all">Select all</button>
      <button type="button" class="btn small gray" data-pick="none">Select none</button>
      <button type="button" class="btn small gray" data-pick="new">Only the new ones</button>
      <span class="hint" id="pickCount" style="margin:0"></span>
    </p>
    <div class="tbl-scroll">
      <table class="grid">
        <tr class="head">
          <th style="width:34px"></th><th>Coin</th><th>Pair</th>
          <th style="text-align:right">24h volume</th>
          <th style="text-align:right">Price</th>
          <th style="text-align:right">24h</th>
          <th></th>
        </tr>
        <?php foreach ($pickPreview['rows'] as $r): ?>
        <tr<?= $r['have'] ? ' style="opacity:.55"' : '' ?>>
          <td><input type="checkbox" name="pairs[]" value="<?= sma_e($r['symbol']) ?>"
                     class="pickBox" data-new="<?= $r['have'] ? '0' : '1' ?>"
                     <?= $r['have'] ? '' : 'checked' ?> style="width:auto"></td>
          <td><strong><?= sma_e($r['base']) ?></strong></td>
          <td><code><?= sma_e($r['symbol']) ?></code></td>
          <td style="text-align:right"><?= sma_e($vol((float)$r['volume'])) ?></td>
          <td style="text-align:right"><?= sma_e($px((float)($r['price'] ?? 0))) ?></td>
          <td style="text-align:right;color:<?= ((float)($r['change'] ?? 0)) >= 0 ? 'var(--up)' : 'var(--down)' ?>">
            <?= sprintf('%+.1f%%', (float)($r['change'] ?? 0)) ?></td>
          <td><?php if ($r['have']): ?><span class="badge neutral">already added</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Import the ticked coins</button></p>
    <p class="hint">Rows marked <em>already added</em> are unticked because there is nothing to do for
      them &mdash; ticking one changes nothing, it will not overwrite the tier or label you already
      set. To change those, use the list further down the page.</p>
  </form>
  <script>
  (function () {
    var form = document.getElementById('pickImportForm');
    if (!form) return;
    var boxes = form.querySelectorAll('.pickBox');
    var out = document.getElementById('pickCount');
    function count() {
      var n = 0;
      boxes.forEach(function (b) { if (b.checked) n++; });
      out.textContent = n + ' of ' + boxes.length + ' ticked';
    }
    form.querySelectorAll('[data-pick]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var how = btn.getAttribute('data-pick');
        boxes.forEach(function (b) {
          b.checked = how === 'all' ? true : how === 'none' ? false : b.dataset.new === '1';
        });
        count();
      });
    });
    boxes.forEach(function (b) { b.addEventListener('change', count); });
    count();
  })();
  </script>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="form-card">
  <h2 style="margin-top:0">Import all pairs from the exchange</h2>
  <form method="post" action="symbols.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="import">
    <div class="row2">
      <div>
        <label>Quote asset</label>
        <select aria-label="Quote asset" name="quote">
          <option value="USDT">USDT (~470 pairs)</option>
          <option value="USDC">USDC</option>
          <option value="FDUSD">FDUSD</option>
          <option value="BTC">BTC</option>
          <option value="ETH">ETH</option>
        </select>
      </div>
      <div>
        <label>Access tier for imported pairs</label>
        <select aria-label="Access tier for imported pairs" name="import_tier">
          <option value="public">Public - everyone</option>
          <option value="free">Free members</option>
          <option value="paid" selected>Paid members</option>
        </select>
      </div>
      <div>
        <label>Enable imported pairs immediately</label>
        <input aria-label="Enable imported pairs immediately" type="checkbox" name="import_enabled" checked style="width:auto">
      </div>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Import all trading pairs</button></p>
    <p class="hint">Fetches the exchange's live pair list and adds every actively trading pair with the
      chosen quote asset. Existing symbols are never duplicated or overwritten. Candles are only fetched
      for a pair when someone actually views its chart, so importing hundreds of pairs costs nothing.</p>
  </form>
  <p style="margin-top:4px">
    <form class="inline-form" method="post" action="symbols.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="enable_all">
      <button class="btn small gray" type="submit">Enable all</button>
    </form>
    <form class="inline-form" method="post" action="symbols.php"
          onsubmit="return confirm('Disable ALL symbols? The site needs at least one enabled.')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="disable_all">
      <button class="btn small gray" type="submit">Disable all</button>
    </form>
    <form class="inline-form" method="post" action="symbols.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="scan_all">
      <button class="btn small gray" type="submit">Scan all</button>
    </form>
    <form class="inline-form" method="post" action="symbols.php"
          onsubmit="return confirm('Take every coin out of the cron scan rotation? No new background signals will be generated until you put some back.')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="scan_none">
      <button class="btn small gray" type="submit">Scan none</button>
    </form>
  </p>
</div>

<?php if ($symbols): ?>
<?php // The three COUNT(*) queries that used to run here went with the form
      // that printed them. Delete data counts every kind in one pass, and it
      // counts them for the coin in scope rather than for the install. ?>
<div class="form-card">
  <h2 style="margin-top:0">Delete the whole coin list</h2>
  <?php // MOVED, NOT REMOVED.
        //
        // This was a form with two checkboxes deciding what went along with
        // the coins, on a page that could not show how much of any of it there
        // was - and the signal history had its own version of the same three
        // questions on a different page, with different defaults. One screen
        // asks both questions once, with a count against every line. What is
        // left here is the pointer, because this is where somebody looking at
        // a coin list will look for it. ?>
  <p class="hint">Removing every coin, its history, its cached candles or what the engine learned
    from it are four separate decisions, and they are asked together - with a count against each -
    under <a href="data.php"><strong>System &rsaquo; Delete data</strong></a>. The per-coin
    <strong>Delete</strong> button on each row below is still here for retiring one coin while you
    are looking at it.</p>
  <p><a class="btn gray" href="data.php">Open Delete data</a></p>
</div>
<?php endif; ?>

<?php
// Everything about one coin, in one place.
//
// Every switch that governs a coin existed, and each one lived somewhere
// else: enable, scan, priority and tier were buttons in the table row, where
// it appears was behind ?show=, its timeframes were under that, and its
// engine overrides were behind ?tune= on a different anchor. Turning a coin
// off "everywhere except the chart" meant knowing which of four places held
// which half of the answer.
//
// One panel now, opened from one link, with the groups nested inside it so
// the panel opens short and any part of it is one click deep. ?show= and
// ?tune= still work - they are what existing links and bookmarks point at.
$coinId = (int)($_GET['coin'] ?? ($_GET['show'] ?? ($_GET['tune'] ?? 0)));
$coinRow = null;
if ($coinId > 0) {
    foreach ($symbols as $s) {
        if ((int)$s['id'] === $coinId) { $coinRow = $s; break; }
    }
}
if ($coinRow):
    $hiddenNow = array_values(array_filter(explode(',', (string)($coinRow['hide_on'] ?? ''))));
    $ov  = ($coinRow['engine_json'] ?? '') !== '' ? (json_decode($coinRow['engine_json'], true) ?: []) : [];
    $val = fn(string $k): string => isset($ov[$k]) ? sma_e((string)$ov[$k]) : '';
    $def = fn(string $k, string $d): string => sma_e(\SignalMasterAi\Database::setting($k, $d));
    $nSurf = count(\SignalMasterAi\Visibility::SURFACES);
    $siteTfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
    $coinTfs = \SignalMasterAi\Visibility::tfsFor((string)$coinRow['symbol'], $siteTfs);
    $ownSet  = \SignalMasterAi\Visibility::ownTfs((string)$coinRow['symbol']);
?>
<div class="form-card coin-hub" id="coin">
  <h2 style="margin-top:0">
    <?= sma_e($coinRow['symbol']) ?>
    <span class="badge <?= (int)$coinRow['enabled'] === 1 ? 'on' : 'off' ?>"><?= (int)$coinRow['enabled'] === 1 ? 'ON' : 'OFF' ?></span>
    <a class="btn small gray" href="symbols.php" style="float:right">Close</a>
  </h2>
  <p class="hint">Every switch for this coin. The master switch is all-or-nothing &mdash; off, the
    coin is gone from every surface below, the public track record included. The groups under it
    are for the cases in between.</p>

  <details class="coin-grp" id="coin-availability" open>
    <summary>Availability <span class="grp-state"><?= (int)$coinRow['enabled'] === 1 ? 'on' : 'off' ?>
      &middot; <?= (int)$coinRow['scan'] === 1 ? 'scanned' : 'not scanned' ?>
      &middot; <?= sma_e((string)$coinRow['tier']) ?></span></summary>
    <p class="hint">The master switch, whether the background scan reads it, whether it jumps the
      queue, and who can see it at all.</p>
    <div class="coin-acts">
      <form method="post" action="symbols.php" class="inline-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle">
        <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
        <button class="btn small <?= (int)$coinRow['enabled'] === 1 ? 'danger' : '' ?>" type="submit">
          <?= (int)$coinRow['enabled'] === 1 ? 'Turn off everywhere' : 'Turn on' ?></button>
      </form>
      <form method="post" action="symbols.php" class="inline-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle_scan">
        <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
        <button class="btn small gray" type="submit">
          <?= (int)$coinRow['scan'] === 1 ? 'Stop scanning' : 'Start scanning' ?></button>
      </form>
      <form method="post" action="symbols.php" class="inline-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle_priority">
        <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
        <button class="btn small gray" type="submit">
          <?= (int)$coinRow['priority'] === 1 ? 'Drop from priority' : 'Scan every run' ?></button>
      </form>
      <form method="post" action="symbols.php" class="inline-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="set_tier">
        <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
        <select name="tier" style="max-width:150px">
          <?php foreach (['public' => 'Everyone', 'free' => 'Signed-in members', 'paid' => 'Premium only'] as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= (string)$coinRow['tier'] === $tk ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn small gray" type="submit">Set access</button>
      </form>
    </div>
  </details>

  <details class="coin-grp" id="show">
    <summary>Where it appears <span class="grp-state"><?= $nSurf - count($hiddenNow) ?> of <?= $nSurf ?> surfaces</span></summary>
  <p class="hint">The master switch above is all-or-nothing: disabled, a coin is gone from every
    surface listed here, the public track record included. These are for the cases in between &mdash;
    a pair worth charting but not worth scanning, or one you publish a record for without letting
    anyone follow it with position sizing. Unticking a box does not delete anything; tick it again
    and the coin comes straight back.</p>
  <form method="post" action="symbols.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="set_visibility">
    <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
    <?php foreach (\SignalMasterAi\Visibility::SURFACES as $key => [$label, $what]): ?>
      <label class="chk" style="align-items:flex-start">
        <input type="checkbox" name="surfaces[]" value="<?= sma_e($key) ?>"
               <?= in_array($key, $hiddenNow, true) ? '' : 'checked' ?> style="width:auto;margin-top:3px">
        <span><strong><?= sma_e($label) ?></strong>
          <span class="hint" style="display:block;margin-top:1px"><?= sma_e($what) ?></span></span>
      </label>
    <?php endforeach; ?>
    <p style="margin-top:14px">
      <button class="btn" type="submit">Save</button>
      
    </p>
  </form>
  </details>

  <details class="coin-grp" id="tfs">
    <summary>Timeframes <span class="grp-state"><?= $ownSet ? sma_e(implode(', ', $coinTfs)) : 'site list' ?></span></summary>
  <p class="hint">The site offers one timeframe list and every coin used to be read on all of it,
    whether or not the reading was worth having. A thin pair has no meaningful 5m structure, and
    scanning it there costs a slot in every rotation to produce calls nobody should act on. Unticking
    a frame removes it from this coin's chart picker, its scanner rows, its backtest options, its
    alerts and its share of the cron budget. Ticking every frame is the same as ticking none: the
    coin follows the site list, including if you change that list later.</p>
  <form method="post" action="symbols.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="set_tfs">
    <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
    <div class="tf-checks" style="display:flex;flex-wrap:wrap;gap:6px 14px;margin:10px 0">
      <?php foreach ($siteTfs as $iv): ?>
        <label class="chk" style="margin:0">
          <input type="checkbox" name="tfs[]" value="<?= sma_e($iv) ?>"
                 <?= in_array($iv, $coinTfs, true) ? 'checked' : '' ?> style="width:auto">
          <?= sma_e($iv) ?></label>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-top:0">Currently:
      <strong><?= $ownSet ? sma_e(implode(', ', $coinTfs)) : 'the site list (' . sma_e(implode(', ', $siteTfs)) . ')' ?></strong>.
      The site list is set under <a href="settings.php#market">Settings &rsaquo; Market data</a>.</p>
    <p style="margin-top:12px"><button class="btn" type="submit">Save timeframes</button></p>
  </form>
  </details>

  <details class="coin-grp" id="tune">
    <summary>Engine tuning <span class="grp-state"><?= $ov ? count($ov) . ' override(s)' : 'site defaults' ?></span></summary>
  <p class="hint">One global configuration cannot suit both BTC on the daily and a thin meme coin on
    15m. Leave a field blank to use the site default (shown as the placeholder). Only values you
    change are stored.</p>
  <form method="post" action="symbols.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="engine_override">
    <input type="hidden" name="id" value="<?= (int)$coinRow['id'] ?>">
    <div class="row2">
      <div><label>BUY threshold</label>
        <input aria-label="BUY threshold" type="number" step="0.1" name="cat_buy_threshold" value="<?= $val('cat_buy_threshold') ?>"
               placeholder="site default: <?= $def('cat_buy_threshold', '3.0') ?>"></div>
      <div><label>SELL threshold</label>
        <input aria-label="SELL threshold" type="number" step="0.1" name="cat_sell_threshold" value="<?= $val('cat_sell_threshold') ?>"
               placeholder="site default: <?= $def('cat_sell_threshold', '-3.0') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Minimum agreeing categories</label>
        <input aria-label="Minimum agreeing categories" type="number" step="1" min="1" max="9" name="min_aligned_categories" value="<?= $val('min_aligned_categories') ?>"
               placeholder="site default: <?= $def('min_aligned_categories', '2') ?>"></div>
      <div><label>Chop limit</label>
        <input aria-label="Chop limit" type="number" step="0.1" name="chop_limit" value="<?= $val('chop_limit') ?>"
               placeholder="site default: <?= $def('chop_limit', '61.8') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Stop loss (ATR multiple)</label>
        <input aria-label="Stop loss (ATR multiple)" type="number" step="0.1" name="sl_atr_mult" value="<?= $val('sl_atr_mult') ?>"
               placeholder="site default: <?= $def('sl_atr_mult', '1.5') ?>">
        <p class="hint">Sets this coin's whole ladder - targets always publish at exactly 1&times;,
          2&times; and 3&times; this distance.</p></div>
      <div><label>Chase-guard distance (ATR multiple)</label>
        <input aria-label="Chase-guard distance (ATR multiple)" type="number" step="0.1" name="tp1_atr_mult" value="<?= $val('tp1_atr_mult') ?>"
               placeholder="site default: <?= $def('tp1_atr_mult', '1.0') ?>">
        <p class="hint">Not a price target - only when this coin's "entry is stale" warning kicks in.</p></div>
    </div>
    <label class="chk"><input type="checkbox" name="levels_enabled_set" <?= isset($ov['levels_enabled']) ? 'checked' : '' ?>>
      Override trade-plan levels for this coin</label>
    <label class="chk" style="margin-left:22px"><input type="checkbox" name="levels_enabled"
      <?= (string)($ov['levels_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> Publish entry/stop/targets</label>
    <p style="margin-top:14px">
      <button class="btn" type="submit">Save overrides</button>
      <a class="btn gray" href="symbols.php">Close</a>
    </p>
    <p class="hint">Submitting with every field blank clears this coin's overrides.</p>
  </form>
  </details>
  <?php // A link to #show or #tune must arrive with that group already open,
        // or the anchor lands on a closed row and looks like it did nothing. ?>
  <script>
  (function () {
    var open = function () {
      var h = location.hash.replace('#', '');
      if (!h) return;
      var el = document.getElementById(h);
      if (el && el.tagName === 'DETAILS') { el.open = true; el.scrollIntoView({ block: 'start' }); }
    };
    open();
    window.addEventListener('hashchange', open);
  })();
  </script>
</div>
<?php endif; ?>

<div class="form-card">
  <h2 style="margin-top:0">One timeframe, every coin</h2>
  <p class="hint">Turn a frame on or off across the whole list &mdash; dropping 5m everywhere when
    it stops earning its scan slots, for instance. A coin left with no frames at all falls back to
    the site list rather than becoming unreadable.</p>
  <form method="post" action="symbols.php" class="inline-form">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="tf_bulk">
    <select name="tf" aria-label="Timeframe to switch on or off in bulk" style="max-width:140px">
      <?php foreach (\SignalMasterAi\Visibility::siteTfs($config['market']['intervals']) as $iv): ?>
        <option value="<?= sma_e($iv) ?>"><?= sma_e($iv) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn small gray" type="submit" name="mode" value="on">Read on it</button>
    <button class="btn small gray" type="submit" name="mode" value="off">Skip it</button>
  </form>
</div>

<div class="form-card">
  <h2 style="margin-top:0">Show or hide every coin on one surface</h2>
  <p class="hint">For the whole list at once &mdash; taking every coin out of the scanner while you
    retune it, for instance, without touching what is charted or alerted on.</p>
  <?php $visCounts = \SignalMasterAi\Visibility::counts(); ?>
  <form method="post" action="symbols.php" class="inline-form">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="vis_bulk">
    <select name="surface" aria-label="Which part of the site to change visibility on" style="max-width:220px">
      <?php foreach (\SignalMasterAi\Visibility::SURFACES as $key => [$label, $what]): ?>
        <option value="<?= sma_e($key) ?>"><?= sma_e($label) ?><?= $visCounts[$key]
          ? ' (' . $visCounts[$key] . ' hidden)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn small gray" type="submit" name="mode" value="show">Show all</button>
    <button class="btn small gray" type="submit" name="mode" value="hide">Hide all</button>
  </form>
</div>

<div class="coin-filter">
  <input type="text" id="coinSearch" placeholder="Search coins&hellip; name, ticker, tier or enabled/disabled"
         autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Search coins">
  <span class="hint" id="coinCount" style="margin-top:0"></span>
</div>
<div class="tbl-scroll">
<table class="grid" id="coinTable">
  <tr class="head"><th>Symbol</th><th>Label</th><th>Tier</th><th>Status</th><th>Shows on</th><th>Scan</th><th>Priority</th><th style="width:180px">Actions</th></tr>
  <?php foreach ($symbols as $s): ?>
  <tr data-search="<?= sma_e(strtolower($s['symbol'] . ' ' . $s['label'] . ' ' . $s['tier'] . ' '
        . ($s['enabled'] ? 'enabled' : 'disabled') . ' ' . ((int)($s['scan'] ?? 1) ? 'scanned' : 'unscanned'))) ?>">
    <td><strong><?= sma_e($s['symbol']) ?></strong></td>
    <td><?= sma_e($s['label']) ?></td>
    <td>
      <form class="inline-form" method="post" action="symbols.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="set_tier">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <?php // One of these per coin, all named "tier". A screen reader read
              // 37 identical combo boxes with no way to tell which coin each
              // belonged to - and this control publishes or hides that coin
              // the instant it changes. ?>
        <select name="tier" aria-label="Who can see <?= sma_e($s['symbol']) ?>"
                onchange="this.form.submit()" style="max-width:130px;padding:5px 8px;font-size:12px">
          <option value="public" <?= $s['tier'] === 'public' ? 'selected' : '' ?>>Public</option>
          <option value="free" <?= $s['tier'] === 'free' ? 'selected' : '' ?>>Free members</option>
          <option value="paid" <?= $s['tier'] === 'paid' ? 'selected' : '' ?>>Paid members</option>
        </select>
      </form>
    </td>
    <td><span class="badge <?= $s['enabled'] ? 'on' : 'off' ?>"><?= $s['enabled'] ? 'ENABLED' : 'DISABLED' ?></span>
      <?php if (($s['engine_json'] ?? '') !== ''): ?>
        <span class="badge" style="background:var(--warn-bg);color:var(--warn)" title="This coin overrides the site engine settings">TUNED</span>
      <?php endif; ?>
    </td>
    <td>
      <?php // Six switches per row would be unreadable, so the row carries the
            // count and the panel carries the switches. ?>
      <?php
        $hid = array_values(array_filter(explode(',', (string)($s['hide_on'] ?? ''))));
        $nAll = count(\SignalMasterAi\Visibility::SURFACES);
        $labels = array_map(fn($k) => \SignalMasterAi\Visibility::SURFACES[$k][0] ?? $k, $hid);
      ?>
      <a class="btn small <?= $hid ? '' : 'gray' ?>" href="symbols.php?coin=<?= (int)$s['id'] ?>#show"
         title="<?= $hid ? 'Hidden on: ' . sma_e(implode(', ', $labels)) : 'Shown on every surface' ?>">
        <?= $hid ? ($nAll - count($hid)) . ' of ' . $nAll : 'Everywhere' ?></a>
      <?php $ownTf = array_values(array_filter(explode(',', (string)($s['tfs'] ?? '')))); ?>
      <?php if ($ownTf): ?>
        <div class="hint" style="margin:4px 0 0" title="Only read on these timeframes">
          <?= sma_e(implode(', ', $ownTf)) ?></div>
      <?php endif; ?>
    </td>
    <td>
      <form class="inline-form" method="post" action="symbols.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle_scan">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn small <?= (int)($s['scan'] ?? 1) ? '' : 'gray' ?>" type="submit"
                title="<?= (int)($s['scan'] ?? 1) ? 'In the cron scan rotation - click to take it out' : 'Not scanned by cron - click to add it' ?>">
          <?= (int)($s['scan'] ?? 1) ? '&#10003; Scanned' : 'Off' ?></button>
      </form>
      <?php // Why the scan gave up, so "Off" is never a mystery. ?>
      <?php if (($s['scan_note'] ?? '') !== '' && !(int)($s['scan'] ?? 1)): ?>
        <div class="hint" style="margin:4px 0 0;color:var(--warn)"
             title="<?= sma_e($s['scan_note']) ?>">dropped after
          <?= (int)$s['scan_fails'] ?> failed run(s):
          <?= sma_e(mb_substr($s['scan_note'], 0, 60)) ?><?= mb_strlen($s['scan_note']) > 60 ? '…' : '' ?></div>
      <?php elseif ((int)($s['scan_fails'] ?? 0) > 0): ?>
        <div class="hint" style="margin:4px 0 0;color:var(--warn)"
             title="<?= sma_e((string)($s['scan_note'] ?? '')) ?>"><?= (int)$s['scan_fails'] ?> failed run(s)</div>
      <?php elseif (str_starts_with((string)($s['scan_note'] ?? ''), 'temporary:')): ?>
        <?php // A fetch that failed and did NOT count against the coin.
              //
              // These carry no strike by design - a rate limit is not a dead
              // coin - but "no strike" was being rendered as "nothing at all",
              // so twenty coins failing every single run looked like twenty
              // coins working. The operator has to be able to see that it is
              // their endpoint and not their watchlist. ?>
        <div class="hint" style="margin:4px 0 0"
             title="<?= sma_e((string)$s['scan_note']) ?>">last fetch failed, kept in the scan:
          <?= sma_e(mb_substr(substr((string)$s['scan_note'], 10), 0, 54)) ?>&hellip;</div>
      <?php endif; ?>
    </td>
    <td>
      <?php // Every run, or once per trip round the list. ?>
      <form class="inline-form" method="post" action="symbols.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle_priority">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn small <?= (int)($s['priority'] ?? 0) ? '' : 'gray' ?>" type="submit"
                title="<?= (int)($s['priority'] ?? 0)
                  ? 'Read on every cron run - click to put it back in the normal rotation'
                  : 'Read once per trip round the list - click to read it every run' ?>">
          <?= (int)($s['priority'] ?? 0) ? '&#9733; Every run' : 'Rotation' ?></button>
      </form>
    </td>
    <td>
      <a class="btn small gray" href="symbols.php?coin=<?= (int)$s['id'] ?>#coin">Settings</a>
      <form class="inline-form" method="post" action="symbols.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="toggle">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn small gray" type="submit"><?= $s['enabled'] ? 'Disable' : 'Enable' ?></button>
      </form>
      <?php // Two questions, not one. Removing the coin from the site is
            // reversible - add it back and it rescans. Deleting its settled
            // signals rewrites the published track record and cannot be
            // undone, so it is asked separately and defaults to no. ?>
      <?php // One button that does one thing, and a drawer for the rest.
            //
            // The checkboxes sat beside the button where they were read as
            // part of it, and one of them was ticked by default - so the
            // ordinary click deleted the coin's learning as well, every time,
            // unless somebody noticed and unticked it. Advanced starts closed
            // and starts empty: nothing is preselected, because a destructive
            // default is a decision made for the operator. ?>
      <form class="inline-form coin-del" method="post" action="symbols.php"
            onsubmit="return confirm(this.querySelector('input[name^=kind]:checked')
              ? 'Delete <?= sma_e($s['symbol']) ?> AND the data you ticked?\n\nAnything ticked here cannot be undone.'
              : 'Delete <?= sma_e($s['symbol']) ?>?\n\nThe coin goes, with the things that only point at it - its watchlist entries, its live board row and its fetch log. Its signals, candles, paper trades and learning are kept.')">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="delete">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn small danger" type="submit">Delete coin</button>
        <details class="coin-del-adv">
          <summary class="hint">Advanced&hellip;</summary>
          <div class="coin-del-kinds">
            <p class="hint"><strong>Always removed with the coin:</strong> its watchlist entries,
              its live board row and its fetch log. Those are pointers at the coin and nothing else
              &mdash; a watchlist entry for a coin that does not exist can never fire, and logs an
              error on every alert sweep until it is cleared.</p>
            <p class="hint"><strong>Kept unless you tick it here.</strong> Same list, same warnings,
              as <a href="data.php">System &rsaquo; Delete data</a>.</p>
            <?php foreach (\SignalMasterAi\Purge::KINDS as $kKey => [$kLabel, $kTable, $kPer, $kHint]):
                    // 'board' goes with the coin whatever happens - see Purge::run -
                    // so offering it as a choice would be offering a decision that
                    // has already been made.
                    if ($kKey === 'coin' || $kKey === 'board') { continue; }
                    $kPub = in_array($kKey, \SignalMasterAi\Purge::PUBLIC_FACING, true); ?>
              <label class="coin-del-kind">
                <input type="checkbox" name="kind[<?= sma_e($kKey) ?>]" value="1">
                <span><strong><?= sma_e($kLabel) ?></strong><?= $kPub
                    ? ' <span class="hint" style="color:var(--bad)">changes the public record</span>' : '' ?>
                  <br><span class="hint"><?= sma_e($kHint) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<p class="hint" id="coinNone" hidden>No coin matches your search.</p>
<script>
(function () {
  var input = document.getElementById('coinSearch');
  var count = document.getElementById('coinCount');
  var none = document.getElementById('coinNone');
  var rows = Array.prototype.slice.call(document.querySelectorAll('#coinTable tr[data-search]'));
  function apply() {
    var q = input.value.trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (r) {
      var hit = q === '' || r.getAttribute('data-search').indexOf(q) !== -1;
      r.style.display = hit ? '' : 'none';
      if (hit) shown++;
    });
    count.textContent = q === '' ? rows.length + ' coins' : shown + ' of ' + rows.length + ' coins match';
    none.hidden = shown !== 0;
  }
  input.addEventListener('input', apply);
  apply();
})();
</script>
<?php admin_footer(); ?>
