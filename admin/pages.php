<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT title FROM pages WHERE id = :id', ['id' => $id]);
    if ($row) {
        Database::run('DELETE FROM nav_items WHERE page_id = :id', ['id' => $id]);
        Database::delete('pages', $id);
        log_activity('page.delete', 'page', $id, $row['title']);
        Flash::ok('“' . $row['title'] . '” was deleted, along with any menu link to it.');
    }
    redirect('admin/pages.php');
}

if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, status FROM pages WHERE id = :id', ['id' => $id]);
    if ($row) {
        Database::update('pages', [
            'status'     => $row['status'] === 'published' ? 'draft' : 'published',
            'updated_at' => now(),
        ], $id);
    }
    redirect('admin/pages.php');
}

if ($action === 'new' || $action === 'edit') {
    $page = $action === 'edit'
        ? Database::one('SELECT * FROM pages WHERE id = :id', ['id' => $id])
        : ['status' => 'draft', 'hero_style' => 'standard', 'show_cta' => 1, 'sort_order' => 0];
    if ($action === 'edit' && !$page) {
        http_response_code(404);
        exit('Page not found.');
    }
    $errors = [];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Enter a page title.';
        }
        $slug = slugify($d('slug') ?? $title);
        /* Never let a page shadow a real script. */
        $reserved = ['index','services','portfolio','portfolio-item','marketplace','product','contact','login','logout','page','admin','client','install','app','assets','uploads','config','partials'];
        if (in_array($slug, $reserved, true)) {
            $errors[] = 'That address is reserved. Choose a different slug.';
        }
        if (!$errors) {
            $data = [
                'title'      => $title,
                'slug'       => unique_slug('pages', $slug, $action === 'edit' ? $id : null),
                'subtitle'   => $d('subtitle'),
                'eyebrow'    => $d('eyebrow'),
                'body'       => $d('body'),
                'meta_title' => $d('meta_title'),
                'meta_desc'  => $d('meta_desc'),
                'hero_style' => in_array((string)($_POST['hero_style'] ?? ''), ['standard','large','plain'], true) ? (string)$_POST['hero_style'] : 'standard',
                'status'     => ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                'show_cta'   => !empty($_POST['show_cta']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'updated_at' => now(),
            ];
            if ($action === 'edit') {
                Database::update('pages', $data, $id);
                Flash::ok('Page saved.');
            } else {
                $data['created_at'] = now();
                $id = Database::insert('pages', $data);
                Flash::ok('Page created.');
            }
            log_activity('page.save', 'page', $id, $title);
            redirect('admin/pages.php');
        }
        $page = array_merge($page, $_POST);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit page' : 'New page';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form">
      <?= Csrf::field() ?>
      <div class="split">
        <div class="fieldset">
          <p class="legend">Content</p>
          <label class="field"><span>Title</span>
            <input name="title" data-slug-source required maxlength="200" value="<?= e($page['title'] ?? '') ?>"></label>
          <div class="row two">
            <label class="field"><span>Eyebrow <small>the small line above the title</small></span>
              <input name="eyebrow" maxlength="120" value="<?= e($page['eyebrow'] ?? '') ?>"></label>
            <label class="field"><span>Address <small>auto</small></span>
              <input name="slug" data-slug-target maxlength="180" value="<?= e($page['slug'] ?? '') ?>"></label>
          </div>
          <label class="field"><span>Subtitle</span>
            <textarea name="subtitle" rows="2" maxlength="400"><?= e($page['subtitle'] ?? '') ?></textarea></label>
          <label class="field"><span>Body <small>blank lines start a new paragraph; a line ending in a colon becomes a heading</small></span>
            <textarea name="body" rows="18"><?= e($page['body'] ?? '') ?></textarea></label>
        </div>

        <div class="stack">
          <div class="fieldset">
            <p class="legend">Publishing</p>
            <label class="field"><span>Status</span>
              <select name="status">
                <option value="draft"<?= ($page['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Draft — only you can see it</option>
                <option value="published"<?= ($page['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
              </select></label>
            <label class="field"><span>Header style</span>
              <select name="hero_style">
                <option value="standard"<?= ($page['hero_style'] ?? '') === 'standard' ? ' selected' : '' ?>>Standard</option>
                <option value="large"<?= ($page['hero_style'] ?? '') === 'large' ? ' selected' : '' ?>>Large</option>
                <option value="plain"<?= ($page['hero_style'] ?? '') === 'plain' ? ' selected' : '' ?>>Plain</option>
              </select></label>
            <label class="field check"><input type="checkbox" name="show_cta" value="1"<?= !empty($page['show_cta']) ? ' checked' : '' ?>>
              <span>Show the closing call to action</span></label>
            <label class="field"><span>Sort order</span>
              <input name="sort_order" type="number" value="<?= e((string)($page['sort_order'] ?? 0)) ?>"></label>
            <?php if ($action === 'edit' && ($page['status'] ?? '') === 'published'): ?>
              <a class="btn ghost sm" href="<?= e(url('page.php?slug=' . urlencode((string)$page['slug']))) ?>" target="_blank" rel="noopener">View page ↗</a>
            <?php endif; ?>
          </div>
          <div class="fieldset">
            <p class="legend">Search engines</p>
            <label class="field"><span>Meta title <small>falls back to the page title</small></span>
              <input name="meta_title" maxlength="200" value="<?= e($page['meta_title'] ?? '') ?>"></label>
            <label class="field"><span>Meta description</span>
              <textarea name="meta_desc" rows="3" maxlength="400"><?= e($page['meta_desc'] ?? '') ?></textarea></label>
          </div>
        </div>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save page' : 'Create page' ?></button>
        <a class="btn ghost" href="pages.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$pages = Database::all('SELECT * FROM pages ORDER BY sort_order, title');
$PAGE_TITLE = 'Pages';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="pages.php?action=new">New page</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<section class="card">
  <?php if (!$pages): ?>
    <div class="empty"><b>No pages yet</b><p>Create About, Privacy, Terms — or anything else you need.</p>
      <a class="btn sm" href="pages.php?action=new">New page</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Page</th><th>Address</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($pages as $p): ?>
        <tr>
          <td><span class="t-main"><?= e($p['title']) ?></span>
              <?php if ($p['subtitle']): ?><span class="t-sub"><?= e(excerpt($p['subtitle'], 80)) ?></span><?php endif; ?></td>
          <td class="mono dim">/page.php?slug=<?= e($p['slug']) ?></td>
          <td><form method="post" action="pages.php?action=toggle&id=<?= (int)$p['id'] ?>"><?= Csrf::field() ?>
            <button class="badge <?= $p['status'] === 'published' ? 'ok' : 'warn' ?>" type="submit" style="cursor:pointer" title="Click to switch">
              <?= $p['status'] === 'published' ? 'Published' : 'Draft' ?></button></form></td>
          <td><div class="acts">
            <?php if ($p['status'] === 'published'): ?>
              <a class="btn ghost sm" href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
            <a class="btn ghost sm" href="pages.php?action=edit&id=<?= (int)$p['id'] ?>">Edit</a>
            <form method="post" action="pages.php?action=delete&id=<?= (int)$p['id'] ?>"
                  data-confirm="Delete “<?= e($p['title']) ?>”? Menu links to it are removed too.">
              <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
