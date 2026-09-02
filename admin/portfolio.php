<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

/* ── delete ─────────────────────────────────────────────── */
if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT * FROM portfolio WHERE id = :id', ['id' => $id]);
    if ($row) {
        Upload::remove($row['cover_image']);
        Database::run('UPDATE projects SET portfolio_id = NULL WHERE portfolio_id = :id', ['id' => $id]);
        Database::delete('portfolio', $id);
        log_activity('portfolio.delete', 'portfolio', $id, $row['title']);
        Flash::ok('“' . $row['title'] . '” was deleted from the portfolio.');
    }
    redirect('admin/portfolio.php');
}

/* ── flip visibility straight from the list ─────────────── */
if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, title, visibility FROM portfolio WHERE id = :id', ['id' => $id]);
    if ($row) {
        $next = $row['visibility'] === 'public' ? 'private' : 'public';
        Database::update('portfolio', ['visibility' => $next], $id);
        log_activity('portfolio.visibility', 'portfolio', $id, $next);
        Flash::ok('“' . $row['title'] . '” is now ' . ($next === 'public' ? 'public on the site.' : 'admin-only.'));
    }
    redirect('admin/portfolio.php');
}

/* ── create / edit ──────────────────────────────────────── */
if ($action === 'new' || $action === 'edit') {
    $item = $action === 'edit'
        ? Database::one('SELECT * FROM portfolio WHERE id = :id', ['id' => $id])
        : ['visibility' => 'private', 'is_featured' => 0, 'sort_order' => 0];
    if ($action === 'edit' && !$item) {
        http_response_code(404);
        exit('Portfolio item not found.');
    }
    $errors = [];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Enter a title.';
        }

        $cover = $item['cover_image'] ?? null;
        [$path, $upErr] = Upload::image('cover_image', 'portfolio');
        if ($upErr) {
            $errors[] = $upErr;
        } elseif ($path) {
            Upload::remove($cover);
            $cover = $path;
        }
        if (!empty($_POST['remove_cover'])) {
            Upload::remove($cover);
            $cover = null;
        }

        if (!$errors) {
            $data = [
                'title'        => $title,
                'slug'         => unique_slug('portfolio', $d('slug') ?? $title, $action === 'edit' ? $id : null),
                'client_name'  => $d('client_name'),
                'category'     => $d('category'),
                'summary'      => $d('summary'),
                'body'         => $d('body'),
                'tech'         => $d('tech'),
                'results'      => $d('results'),
                'live_url'     => $d('live_url'),
                'cover_image'  => $cover,
                'visibility'   => ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
                'is_featured'  => !empty($_POST['is_featured']) ? 1 : 0,
                'completed_on' => $d('completed_on'),
                'sort_order'   => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($action === 'edit') {
                Database::update('portfolio', $data, $id);
                log_activity('portfolio.update', 'portfolio', $id, $title);
                Flash::ok('Portfolio entry saved.');
            } else {
                $data['created_at'] = now();
                $id = Database::insert('portfolio', $data);
                log_activity('portfolio.create', 'portfolio', $id, $title);
                Flash::ok('Portfolio entry created.');
            }
            redirect('admin/portfolio.php');
        }
        $item = array_merge($item, $_POST, ['cover_image' => $cover]);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit portfolio entry' : 'New portfolio entry';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="split">
        <div class="stack">
          <div class="fieldset">
            <p class="legend">The work</p>
            <label class="field"><span>Title</span>
              <input name="title" data-slug-source value="<?= e($item['title'] ?? '') ?>" required maxlength="180"></label>
            <div class="row two">
              <label class="field"><span>URL slug <small>auto</small></span>
                <input name="slug" data-slug-target value="<?= e($item['slug'] ?? '') ?>" maxlength="180"></label>
              <label class="field"><span>Category</span>
                <input name="category" placeholder="Hospitality · Online ordering" value="<?= e($item['category'] ?? '') ?>"></label>
            </div>
            <label class="field"><span>Summary <small>one or two lines, shown on cards</small></span>
              <textarea name="summary" rows="2" maxlength="500"><?= e($item['summary'] ?? '') ?></textarea></label>
            <label class="field"><span>Full description</span>
              <textarea name="body" rows="7"><?= e($item['body'] ?? '') ?></textarea></label>
          </div>
          <div class="fieldset">
            <p class="legend">Detail</p>
            <label class="field"><span>Technologies <small>comma separated</small></span>
              <input name="tech" placeholder="PHP, MySQL, Stripe" value="<?= e($item['tech'] ?? '') ?>"></label>
            <label class="field"><span>Results <small>one per line — “3.4× · orders per shift”</small></span>
              <textarea name="results" rows="4" placeholder="3.4× · orders handled per shift
&lt;1s · menu load on 4G"><?= e($item['results'] ?? '') ?></textarea></label>
          </div>
        </div>

        <div class="stack">
          <div class="fieldset">
            <p class="legend">Who sees this</p>
            <label class="field"><span>Visibility</span>
              <select name="visibility">
                <option value="private"<?= ($item['visibility'] ?? '') === 'private' ? ' selected' : '' ?>>Private — admin and the client only</option>
                <option value="public"<?= ($item['visibility'] ?? '') === 'public' ? ' selected' : '' ?>>Public — shown on the site portfolio</option>
              </select></label>
            <label class="field check"><input type="checkbox" name="is_featured" value="1"<?= !empty($item['is_featured']) ? ' checked' : '' ?>>
              <span>Feature on the home page</span></label>
            <p class="hint">Private entries never appear on the public site. Use them for work under NDA, or to record a delivery before the client approves publishing it.</p>
          </div>

          <div class="fieldset">
            <p class="legend">Cover image</p>
            <?php if (!empty($item['cover_image'])): ?>
              <img class="thumb" src="<?= e(url($item['cover_image'])) ?>" alt="" id="coverNow">
              <label class="field check"><input type="checkbox" name="remove_cover" value="1"><span>Remove this image</span></label>
            <?php endif; ?>
            <img class="thumb" id="coverPreview" alt="" hidden>
            <label class="field"><span>Upload <small>JPG, PNG, WEBP or SVG · max 4 MB</small></span>
              <input type="file" name="cover_image" accept="image/*" data-preview="#coverPreview"></label>
          </div>

          <div class="fieldset">
            <p class="legend">Meta</p>
            <label class="field"><span>Live URL</span>
              <input name="live_url" type="url" placeholder="https://" value="<?= e($item['live_url'] ?? '') ?>"></label>
            <label class="field"><span>Client name</span>
              <input name="client_name" value="<?= e($item['client_name'] ?? '') ?>"></label>
            <div class="row two">
              <label class="field"><span>Completed</span>
                <input name="completed_on" type="date" value="<?= e($item['completed_on'] ?? '') ?>"></label>
              <label class="field"><span>Sort order</span>
                <input name="sort_order" type="number" value="<?= e((string)($item['sort_order'] ?? 0)) ?>"></label>
            </div>
          </div>
        </div>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save entry' : 'Create entry' ?></button>
        <a class="btn ghost" href="portfolio.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

/* ── list ───────────────────────────────────────────────── */
$vis   = (string)($_GET['vis'] ?? '');
$where = in_array($vis, ['public', 'private'], true) ? ' WHERE visibility = :v' : '';
$items = Database::all('SELECT * FROM portfolio' . $where . ' ORDER BY sort_order, completed_on DESC, id DESC',
    $where ? ['v' => $vis] : []);

$PAGE_TITLE = 'Portfolio';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="portfolio.php?action=new">Add completed project</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach (['' => 'All', 'public' => 'Public', 'private' => 'Admin only'] as $k => $v): ?>
    <a href="?vis=<?= e($k) ?>" class="<?= $vis === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>

<section class="card">
  <?php if (!$items): ?>
    <div class="empty"><b>Nothing in the portfolio</b>
      <p>Add a completed project, then choose whether the public site shows it.</p>
      <a class="btn sm" href="portfolio.php?action=new">Add completed project</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Project</th><th>Category</th><th>Completed</th><th>Visibility</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <span class="t-main"><?= e($it['title']) ?><?= $it['is_featured'] ? ' <span class="badge info">Featured</span>' : '' ?></span>
            <span class="t-sub"><?= e($it['client_name'] ?: excerpt($it['summary'], 70)) ?></span>
          </td>
          <td><?= e($it['category'] ?: '—') ?></td>
          <td class="nowrap"><?= e(fdate($it['completed_on'])) ?></td>
          <td>
            <form method="post" action="portfolio.php?action=toggle&id=<?= (int)$it['id'] ?>">
              <?= Csrf::field() ?>
              <button class="badge <?= $it['visibility'] === 'public' ? 'ok' : 'warn' ?>" type="submit"
                      style="cursor:pointer" title="Click to switch">
                <?= $it['visibility'] === 'public' ? 'Public' : 'Admin only' ?>
              </button>
            </form>
          </td>
          <td><div class="acts">
            <?php if ($it['visibility'] === 'public'): ?>
              <a class="btn ghost sm" href="<?= e(url('portfolio-item.php?slug=' . urlencode($it['slug']))) ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
            <a class="btn ghost sm" href="portfolio.php?action=edit&id=<?= (int)$it['id'] ?>">Edit</a>
            <form method="post" action="portfolio.php?action=delete&id=<?= (int)$it['id'] ?>"
                  data-confirm="Delete “<?= e($it['title']) ?>” from the portfolio? This cannot be undone.">
              <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
