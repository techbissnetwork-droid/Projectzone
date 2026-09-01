<?php
$__page = 'games';
$title = 'Games';
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/imagefetch.php';
require_login();

$pdo = db();

/** True if the games table has a usable column (adding it first if it can). */
function games_has_column(PDO $pdo, string $col, string $definition): bool
{
    static $known = [];
    if (isset($known[$col])) { return $known[$col]; }
    if (function_exists('ensure_column')) {
        ensure_column($pdo, 'games', $col, $definition);
    }
    try {
        $pdo->query("SELECT $col FROM games LIMIT 1");
        $known[$col] = true;
    } catch (Throwable $e) {
        $known[$col] = false;
    }
    return $known[$col];
}

/** The columns this page adds; used for the check, the repair and the SQL hint. */
function games_new_columns(): array
{
    return [
        'source'    => "VARCHAR(10) NOT NULL DEFAULT 'builtin'",
        'embed_url' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'html_code' => "TEXT",
        'cover'     => "VARCHAR(255) NOT NULL DEFAULT ''",
    ];
}

/** Which of those columns are still missing. */
function games_missing_columns(PDO $pdo): array
{
    $missing = [];
    foreach (games_new_columns() as $col => $definition) {
        if (!games_has_column($pdo, $col, $definition)) { $missing[$col] = $definition; }
    }
    return $missing;
}

$MISSING = games_missing_columns($pdo);
$CAN_ADD = $MISSING === [];        // adding your own games needs the new columns

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // ── Save the whole list (title / category / emoji / order / visibility) ──
    if ($action === 'save_all') {
        $ids = $_POST['id'] ?? [];
        $st = $pdo->prepare("UPDATE games SET title=?,cat=?,emoji=?,sort_order=?,is_active=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $cat = slugify((string) ($_POST['cat'][$i] ?? 'arcade')) ?: 'arcade';
            $st->execute([
                clip((string) ($_POST['title'][$i] ?? ''), 80),
                $cat,
                clip((string) ($_POST['emoji'][$i] ?? '🎮'), 16),
                (int) ($_POST['sort_order'][$i] ?? 0),
                isset($_POST['is_active'][$id]) ? 1 : 0,
                (int) $id,
            ]);
        }
        flash('Games saved.');

    // ── Add or edit one game ────────────────────────────────────────────────
    } elseif ($action === 'save_one' && $CAN_ADD) {
        $id     = (int) ($_POST['id'] ?? 0);
        $source = game_source_key((string) ($_POST['source'] ?? 'url'));
        $gTitle = clip((string) ($_POST['title'] ?? ''), 80);
        $cat    = slugify((string) ($_POST['cat_custom'] ?? '') ?: (string) ($_POST['cat'] ?? 'arcade')) ?: 'arcade';
        $emoji  = clip((string) ($_POST['emoji'] ?? ''), 16) ?: '🎮';
        $embed  = trim((string) ($_POST['embed_url'] ?? ''));
        $html   = (string) ($_POST['html_code'] ?? '');
        $codeRef = (int) ($_POST['code_ref'] ?? 0);
        $order  = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $cover  = trim((string) ($_POST['cover'] ?? ''));
        $notes  = [];
        $error  = '';

        if ($gTitle === '') {
            $error = 'Give the game a title.';
        } elseif (mb_strlen($embed) > 255) {
            // MySQL would refuse the row outright; say so instead of failing.
            $error = 'That game address is too long (255 characters maximum).';
        } elseif (mb_strlen($cover) > 255) {
            $error = 'That cover image path is too long (255 characters maximum).';
        } elseif ($source === 'url') {
            if ($embed === '') {
                $error = 'Paste the address the game is played at.';
            } else {
                if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $embed)) { $embed = 'https://' . ltrim($embed, '/'); }
                if (url_origin($embed) === '') { $error = 'That does not look like a full web address.'; }
            }
        } elseif ($source === 'html') {
            if (trim($html) === '') {
                $error = 'Paste the game\'s HTML.';
            } elseif (stripos($html, '<') === false) {
                $error = 'That does not look like HTML — paste the whole game page.';
            }
        } elseif ($source === 'builtin' && ($codeRef < 1 || $codeRef > 20)) {
            $error = 'Built-in games are numbered 1 to 20.';
        }

        if ($error !== '') {
            flash($error);
            header('Location: ' . url('/admin/games.php' . ($id > 0 ? '?edit=' . $id : '?add=1')));
            exit;
        }

        // Cover image: uploaded file, typed path, Detect, or — for a linked
        // game with no cover — the site's own logo, fetched on save.
        $prefix = slugify($gTitle, 24) ?: 'game';
        if (!empty($_FILES['cover_file']['name'])) {
            $up = store_uploaded_image($_FILES['cover_file'], $prefix);
            if ($up['ok']) { $cover = $up['path']; }
            elseif ($up['error'] !== '') { $notes[] = $up['error']; }
        }
        if ($cover === '' && $source === 'url' && $embed !== '') {
            $found = detect_site_image($embed, $prefix);
            if ($found['ok']) {
                $cover = $found['path'];
                $notes[] = 'Picked up the ' . strtolower($found['kind']) . ' as its cover.';
            }
        }

        if ($id > 0) {
            $prev = $pdo->prepare("SELECT cover FROM games WHERE id = ?");
            $prev->execute([$id]);
            $old = trim((string) ($prev->fetchColumn() ?: ''));
            if ($old !== '' && $old !== $cover) { delete_stored_image($old); }

            $pdo->prepare(
                "UPDATE games SET code_ref=?,title=?,cat=?,emoji=?,sort_order=?,is_active=?,
                                  source=?,embed_url=?,html_code=?,cover=? WHERE id=?"
            )->execute([
                $source === 'builtin' ? $codeRef : 0, $gTitle, $cat, $emoji, $order, $active,
                $source, $source === 'url' ? $embed : '', $source === 'html' ? $html : null, $cover, $id,
            ]);
            array_unshift($notes, 'Game updated.');
        } else {
            if ($order === 0) {
                $order = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM games")->fetchColumn();
            }
            $pdo->prepare(
                "INSERT INTO games (code_ref,title,cat,emoji,sort_order,is_active,source,embed_url,html_code,cover)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $source === 'builtin' ? $codeRef : 0, $gTitle, $cat, $emoji, $order, $active,
                $source, $source === 'url' ? $embed : '', $source === 'html' ? $html : null, $cover,
            ]);
            array_unshift($notes, 'Game added.');
        }
        flash(implode(' ', $notes));

    // ── Delete one game ─────────────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($CAN_ADD) {
            $prev = $pdo->prepare("SELECT cover FROM games WHERE id = ?");
            $prev->execute([$id]);
            delete_stored_image((string) ($prev->fetchColumn() ?: ''));
        }
        $pdo->prepare("DELETE FROM games WHERE id=?")->execute([$id]);
        flash('Game deleted.');

    // ── Add the columns this page needs ─────────────────────────────────────
    } elseif ($action === 'repair') {
        $failed = [];
        foreach ($MISSING as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE games ADD COLUMN $col $definition");
            } catch (Throwable $e) {
                $m = $e->getMessage();
                if (stripos($m, 'duplicate') === false && stripos($m, 'exists') === false) {
                    $failed[] = $col . ' (' . $m . ')';
                }
            }
        }
        try { $pdo->exec("UPDATE games SET source='builtin' WHERE source IS NULL OR source=''"); } catch (Throwable $e) {}
        flash($failed
            ? 'Some columns could not be added automatically: ' . implode('; ', $failed) . ' — please run the SQL shown below in phpMyAdmin.'
            : 'Database ready — you can add your own games now. 🎮');
    }

    touch_content();
    header('Location: ' . url('/admin/games.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';

$rows = $pdo->query("SELECT * FROM games ORDER BY sort_order, id")->fetchAll();
$cats = game_categories();

// Which game is in the editor: an existing one, a blank new one, or none.
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM games WHERE id=?");
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}
$showForm = $CAN_ADD && ($edit !== null || isset($_GET['add']));
$form = $edit ?: [
    'id' => 0, 'code_ref' => 0, 'title' => '', 'cat' => 'arcade', 'emoji' => '🎮',
    'sort_order' => count($rows) + 1, 'is_active' => 1,
    'source' => 'url', 'embed_url' => '', 'html_code' => '', 'cover' => '',
];
$formSource = game_source_key((string) ($form['source'] ?? 'url'));
$coverNow = media((string) ($form['cover'] ?? ''));
?>
<div class="topbar">
  <div><h1 class="page-title">Games</h1><div class="page-sub">The games on your site — the twenty built in, plus any you add yourself.</div></div>
  <?php if ($CAN_ADD && !$showForm): ?>
    <a class="btn btn-primary" href="<?= e(url('/admin/games.php?add=1')) ?>">+ Add a game</a>
  <?php endif; ?>
</div>

<?php if ($MISSING): ?>
<div class="panel" style="border-color:rgba(255,200,60,.3)">
  <div class="alert alert-warn" style="margin-bottom:1rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Your database predates the "add your own games" feature, so it is missing the
    <?php $n = count($MISSING); $i = 0; foreach (array_keys($MISSING) as $col): $i++; ?>
      <span class="pill"><?= e($col) ?></span><?= $i < $n - 1 ? ', ' : ($i === $n - 1 ? ' and ' : '') ?>
    <?php endforeach; ?>
    column<?= $n > 1 ? 's' : '' ?>. Add <?= $n > 1 ? 'them' : 'it' ?> and you can add games without touching any files:</div>
  <form method="post" action="<?= e(url('/admin/games.php')) ?>" style="margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="action" value="repair"><button class="btn btn-primary" type="submit">Upgrade the games table now</button></form>
  <div class="small">If that fails (some hosts block <code>ALTER</code>), run <?= $n > 1 ? 'these' : 'this' ?> once in <strong>phpMyAdmin → your database → SQL</strong>:<br>
  <?php foreach ($MISSING as $col => $definition): ?>
    <code style="display:inline-block;margin-top:.4rem">ALTER TABLE games ADD COLUMN <?= e($col) ?> <?= e($definition) ?>;</code><br>
  <?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="panel">
  <h2><?= $edit ? 'Edit game' : 'Add a game' ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/games.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_one" />
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>" />

    <div class="field">
      <label>Where does this game come from?</label>
      <select name="source" id="gameSource" data-source-select>
        <?php foreach (game_sources() as $sk => $sl): ?>
          <option value="<?= e($sk) ?>" <?= $formSource === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">
        <strong>Link to a game</strong> — the easiest: paste the address it is played at and it opens inside your site.
        <strong>Pasted HTML game</strong> — for a single-file game: paste the whole page and it is served from here.
        <strong>Built-in game</strong> — one of the twenty that ship with the site, chosen by its number.
      </div>
    </div>

    <div data-source-only="url" <?= $formSource === 'url' ? '' : 'hidden' ?>>
      <div class="field">
        <label>Game address</label>
        <input type="text" name="embed_url" id="gameUrl" value="<?= e($form['embed_url'] ?? '') ?>" placeholder="https://example.com/games/my-game/" />
        <div class="hint">Some sites refuse to be shown inside another page. When that happens visitors get an “open in a new tab” button instead, so the game always works.</div>
      </div>
    </div>

    <div data-source-only="html" <?= $formSource === 'html' ? '' : 'hidden' ?>>
      <div class="field">
        <label>Game HTML</label>
        <textarea name="html_code" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:.82rem"><?= e((string) ($form['html_code'] ?? '')) ?></textarea>
        <div class="hint">The complete page, from <code>&lt;!DOCTYPE html&gt;</code> down. It runs in a sandbox — it can play, draw and take keyboard input, but it cannot reach the rest of your site.</div>
      </div>
    </div>

    <div data-source-only="builtin" <?= $formSource === 'builtin' ? '' : 'hidden' ?>>
      <div class="field" style="max-width:220px">
        <label>Built-in game number</label>
        <input type="number" name="code_ref" min="1" max="20" value="<?= (int) ($form['code_ref'] ?: 1) ?>" />
        <div class="hint">1–20, matching <span class="pill">assets/js/games-data.js</span>.</div>
      </div>
    </div>

    <div class="grid-3">
      <div class="field"><label>Title</label><input type="text" name="title" id="gameTitle" value="<?= e($form['title']) ?>" required /></div>
      <div class="field"><label>Emoji</label><input type="text" name="emoji" value="<?= e($form['emoji']) ?>" style="text-align:center" /></div>
      <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>" /></div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Category</label>
        <select name="cat">
          <?php foreach ($cats as $ck => $cl): ?>
            <option value="<?= e($ck) ?>" <?= ($form['cat'] ?? '') === $ck ? 'selected' : '' ?>><?= e($cl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>…or a new category</label>
        <input type="text" name="cat_custom" placeholder="e.g. Multiplayer" />
        <div class="hint">Type one here and it becomes a filter button on the site.</div>
      </div>
    </div>

    <div class="field">
      <label>Cover image</label>
      <div class="image-picker">
        <img class="image-preview" id="gameCoverPreview" data-preview-for="#gameCover"
             src="<?= e($coverNow) ?>" alt="" <?= $coverNow === '' ? 'hidden' : '' ?> />
        <div class="image-picker-body">
          <input type="text" name="cover" id="gameCover" value="<?= e($form['cover'] ?? '') ?>" placeholder="Leave blank to use the emoji" />
          <div class="actions" style="margin:.5rem 0 0">
            <button class="btn btn-ghost btn-sm" type="button"
                    data-detect="#gameUrl" data-detect-target="#gameCover"
                    data-detect-preview="#gameCoverPreview" data-detect-prefix="#gameTitle"
                    data-detect-note="#gameCoverNote">Detect from the game address</button>
            <button class="btn btn-ghost btn-sm" type="button"
                    data-clear-image="#gameCover" data-clear-preview="#gameCoverPreview"
                    data-clear-note="#gameCoverNote">Clear</button>
          </div>
          <div class="detect-note" id="gameCoverNote"></div>
          <div class="hint" style="margin-top:.55rem">Shown on the game card in place of the emoji. Leave it blank on a linked game and its site's own image is fetched when you save.</div>
          <input type="file" name="cover_file" accept="image/*" style="margin-top:.5rem" />
        </div>
      </div>
    </div>

    <div class="field row-inline">
      <label class="switch"><input type="checkbox" name="is_active" <?= ((int) $form['is_active']) === 1 ? 'checked' : '' ?> /><span class="track"></span></label>
      <span class="small">Show this game on the site</span>
    </div>

    <div class="actions">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update game' : 'Add game' ?></button>
      <a class="btn btn-ghost" href="<?= e(url('/admin/games.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2>All games (<?= count($rows) ?>)</h2>
  <div class="hint" style="margin-bottom:1rem">Edit titles, categories, emoji, order and visibility straight in the table and press Save. Use <strong>Edit</strong> on a row to change where a game comes from or its cover image.</div>
  <form method="post" action="<?= e(url('/admin/games.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_all" />
    <div class="table-wrap">
      <table>
        <thead><tr><th>Cover</th><th>Source</th><th>Emoji</th><th>Title</th><th>Category</th><th>Order</th><th>Live</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
          $rSource = game_source_key((string) ($r['source'] ?? 'builtin'));
          $rCover  = media((string) ($r['cover'] ?? ''));
        ?>
          <tr>
            <td><?php if ($rCover !== ''): ?><img class="row-thumb" src="<?= e($rCover) ?>" alt="" loading="lazy" /><?php else: ?><span class="muted" style="font-size:1.3rem"><?= e($r['emoji']) ?></span><?php endif; ?></td>
            <td>
              <?php if ($rSource === 'builtin'): ?>
                <span class="pill">Built-in #<?= (int) $r['code_ref'] ?></span>
              <?php elseif ($rSource === 'url'): ?>
                <span class="pill" style="color:var(--cyan);border-color:rgba(0,212,255,.3)">Linked</span>
                <div class="muted" style="font-size:.72rem;max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= e(url_origin((string) $r['embed_url'])) ?></div>
              <?php else: ?>
                <span class="pill" style="color:var(--violet);border-color:rgba(167,139,250,.3)">Pasted HTML</span>
              <?php endif; ?>
              <input type="hidden" name="id[]" value="<?= (int) $r['id'] ?>">
            </td>
            <td><input type="text" name="emoji[]" value="<?= e($r['emoji']) ?>" style="width:64px;text-align:center"></td>
            <td><input type="text" name="title[]" value="<?= e($r['title']) ?>"></td>
            <td>
              <select name="cat[]">
                <?php foreach ($cats as $ck => $cl): ?><option value="<?= e($ck) ?>" <?= $r['cat']===$ck?'selected':'' ?>><?= e($cl) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="sort_order[]" value="<?= (int) $r['sort_order'] ?>" style="width:70px"></td>
            <td><label class="switch"><input type="checkbox" name="is_active[<?= (int) $r['id'] ?>]" <?= $r['is_active']?'checked':'' ?>><span class="track"></span></label></td>
            <td style="text-align:right">
              <?php if ($CAN_ADD): ?>
              <div class="actions" style="justify-content:flex-end">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/games.php?edit=' . (int) $r['id'])) ?>">Edit</a>
              </div>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:2rem">No games yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="actions" style="margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save all games</button></div>
  </form>
</div>

<?php if ($CAN_ADD && $rows): ?>
<div class="panel">
  <h2>Remove a game</h2>
  <div class="hint" style="margin-bottom:1rem">Hiding a game with the <strong>Live</strong> switch keeps it for later. Deleting is permanent — a built-in game can always be added back by its number.</div>
  <div class="actions" style="flex-wrap:wrap">
    <?php foreach ($rows as $r): ?>
      <form method="post" action="<?= e(url('/admin/games.php')) ?>" onsubmit="return confirm('Delete “<?= e($r['title']) ?>” from the site?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">✕ <?= e($r['title']) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/foot.php'; ?>
