<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT title FROM services WHERE id = :id', ['id' => $id]);
    if ($row) {
        Database::delete('services', $id);
        log_activity('service.delete', 'service', $id, $row['title']);
        Flash::ok('“' . $row['title'] . '” was deleted.');
    }
    redirect('admin/services.php');
}

if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, is_active FROM services WHERE id = :id', ['id' => $id]);
    if ($row) {
        Database::update('services', ['is_active' => $row['is_active'] ? 0 : 1], $id);
    }
    redirect('admin/services.php');
}

if ($action === 'new' || $action === 'edit') {
    $item = $action === 'edit'
        ? Database::one('SELECT * FROM services WHERE id = :id', ['id' => $id])
        : ['is_active' => 1, 'icon' => 'websites', 'sort_order' => 0];
    if ($action === 'edit' && !$item) {
        http_response_code(404);
        exit('Service not found.');
    }
    $errors = [];
    $icons = ['websites','apps','domains','hosting','security','email','ecommerce','automation','payments'];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Enter a service title.';
        }
        if (!$errors) {
            $data = [
                'title'      => $title,
                'slug'       => unique_slug('services', $d('slug') ?? $title, $action === 'edit' ? $id : null),
                'summary'    => $d('summary'),
                'body'       => $d('body'),
                'features'   => $d('features'),
                'tech'       => $d('tech'),
                'icon'       => in_array((string)($_POST['icon'] ?? ''), $icons, true) ? (string)$_POST['icon'] : 'websites',
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active'  => !empty($_POST['is_active']) ? 1 : 0,
            ];
            if ($action === 'edit') {
                Database::update('services', $data, $id);
                Flash::ok('Service saved.');
            } else {
                $data['created_at'] = now();
                Database::insert('services', $data);
                Flash::ok('Service created.');
            }
            redirect('admin/services.php');
        }
        $item = array_merge($item, $_POST);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit service' : 'New service';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:760px">
      <?= Csrf::field() ?>
      <div class="fieldset">
        <p class="legend">Service</p>
        <div class="row two">
          <label class="field"><span>Title</span>
            <input name="title" data-slug-source value="<?= e($item['title'] ?? '') ?>" required></label>
          <label class="field"><span>URL slug <small>auto</small></span>
            <input name="slug" data-slug-target value="<?= e($item['slug'] ?? '') ?>"></label>
        </div>
        <label class="field"><span>Summary <small>one line, shown on the module card</small></span>
          <input name="summary" maxlength="400" value="<?= e($item['summary'] ?? '') ?>"></label>
        <label class="field"><span>Description</span>
          <textarea name="body" rows="4"><?= e($item['body'] ?? '') ?></textarea></label>
        <label class="field"><span>Features <small>one per line</small></span>
          <textarea name="features" rows="5"><?= e($item['features'] ?? '') ?></textarea></label>
        <label class="field"><span>Technologies <small>comma separated</small></span>
          <input name="tech" value="<?= e($item['tech'] ?? '') ?>"></label>
        <div class="row three">
          <label class="field"><span>Icon</span>
            <select name="icon">
              <?php foreach ($icons as $ic): ?>
                <option value="<?= $ic ?>"<?= ($item['icon'] ?? '') === $ic ? ' selected' : '' ?>><?= e(label($ic)) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="field"><span>Sort order</span>
            <input name="sort_order" type="number" value="<?= e((string)($item['sort_order'] ?? 0)) ?>"></label>
          <label class="field check" style="align-self:end"><input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>>
            <span>Active</span></label>
        </div>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save service' : 'Create service' ?></button>
        <a class="btn ghost" href="services.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$items = Database::all('SELECT * FROM services ORDER BY sort_order, id');
$PAGE_TITLE = 'Services';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="services.php?action=new">New service</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<section class="card">
  <?php if (!$items): ?>
    <div class="empty"><b>No services</b><p>These are the modules shown on the home page and services page.</p>
      <a class="btn sm" href="services.php?action=new">New service</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th class="right">#</th><th>Service</th><th>Icon</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td class="num muted"><?= (int)$it['sort_order'] ?></td>
          <td><span class="t-main"><?= e($it['title']) ?></span><span class="t-sub"><?= e(excerpt($it['summary'], 90)) ?></span></td>
          <td class="muted mono"><?= e($it['icon']) ?></td>
          <td><form method="post" action="services.php?action=toggle&id=<?= (int)$it['id'] ?>"><?= Csrf::field() ?>
            <button class="badge <?= $it['is_active'] ? 'ok' : 'muted' ?>" type="submit" style="cursor:pointer">
              <?= $it['is_active'] ? 'Active' : 'Hidden' ?></button></form></td>
          <td><div class="acts">
            <a class="btn ghost sm" href="services.php?action=edit&id=<?= (int)$it['id'] ?>">Edit</a>
            <form method="post" action="services.php?action=delete&id=<?= (int)$it['id'] ?>"
                  data-confirm="Delete “<?= e($it['title']) ?>”?">
              <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
