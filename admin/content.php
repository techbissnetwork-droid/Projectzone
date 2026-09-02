<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$kind = (string)($_GET['kind'] ?? 'stat');
if (!isset(Content::KINDS[$kind])) {
    $kind = 'stat';
}
$spec   = Content::KINDS[$kind];
$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT label FROM content_items WHERE id = :id AND kind = :k', ['id' => $id, 'k' => $kind]);
    if ($row) {
        Database::delete('content_items', $id);
        log_activity('content.delete', 'content', $id, $kind . ' · ' . $row['label']);
        Flash::ok('“' . $row['label'] . '” was deleted.');
    }
    redirect('admin/content.php?kind=' . $kind);
}

if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, is_active FROM content_items WHERE id = :id AND kind = :k', ['id' => $id, 'k' => $kind]);
    if ($row) {
        Database::update('content_items', ['is_active' => $row['is_active'] ? 0 : 1], $id);
    }
    redirect('admin/content.php?kind=' . $kind);
}

/* Reorder the whole list in one save. */
if ($action === 'reorder' && post()) {
    Csrf::check();
    foreach ((array)($_POST['sort'] ?? []) as $rowId => $pos) {
        Database::run('UPDATE content_items SET sort_order = :s WHERE id = :i AND kind = :k',
            ['s' => (int)$pos, 'i' => (int)$rowId, 'k' => $kind]);
    }
    Flash::ok('Order saved.');
    redirect('admin/content.php?kind=' . $kind);
}

if ($action === 'new' || $action === 'edit') {
    $item = $action === 'edit'
        ? Database::one('SELECT * FROM content_items WHERE id = :id AND kind = :k', ['id' => $id, 'k' => $kind])
        : ['is_active' => 1, 'sort_order' => (int)Database::value('SELECT COALESCE(MAX(sort_order),0)+10 FROM content_items WHERE kind = :k', ['k' => $kind], 10)];
    if ($action === 'edit' && !$item) {
        http_response_code(404);
        exit('Item not found.');
    }
    $errors = [];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        if ($d('label') === null) {
            $errors[] = 'Enter a ' . strtolower($spec['label'][0]) . '.';
        }
        if (!$errors) {
            $data = [
                'label'      => $d('label'),
                'title'      => $d('title'),
                'body'       => $d('body'),
                'extra'      => $d('extra'),
                'meta1'      => $d('meta1'),
                'meta2'      => $d('meta2'),
                'icon'       => $d('icon'),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active'  => !empty($_POST['is_active']) ? 1 : 0,
            ];
            if ($action === 'edit') {
                Database::update('content_items', $data, $id);
                Flash::ok('Saved.');
            } else {
                $data['kind'] = $kind;
                $data['created_at'] = now();
                Database::insert('content_items', $data);
                Flash::ok('Added.');
            }
            log_activity('content.save', 'content', $id ?: null, $kind);
            redirect('admin/content.php?kind=' . $kind);
        }
        $item = array_merge($item, $_POST);
    }

    $PAGE_TITLE = ($action === 'edit' ? 'Edit ' : 'New ') . rtrim(strtolower($spec['name']), 's');
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:760px">
      <?= Csrf::field() ?>
      <div class="fieldset">
        <p class="legend"><?= e($spec['name']) ?></p>
        <label class="field"><span><?= e($spec['label'][0]) ?></span>
          <input name="label" required maxlength="200" placeholder="<?= e((string)$spec['label'][1]) ?>"
                 value="<?= e($item['label'] ?? '') ?>"></label>

        <?php if (!empty($spec['title'][0])): ?>
          <label class="field"><span><?= e($spec['title'][0]) ?></span>
            <input name="title" maxlength="250" placeholder="<?= e((string)$spec['title'][1]) ?>"
                   value="<?= e($item['title'] ?? '') ?>"></label>
        <?php endif; ?>

        <?php if (!empty($spec['body'][0])): ?>
          <label class="field"><span><?= e($spec['body'][0]) ?></span>
            <textarea name="body" rows="4" placeholder="<?= e((string)$spec['body'][1]) ?>"><?= e($item['body'] ?? '') ?></textarea></label>
        <?php endif; ?>

        <?php if (!empty($spec['extra'][0])): ?>
          <label class="field"><span><?= e($spec['extra'][0]) ?> <small>one per line</small></span>
            <textarea name="extra" rows="5" placeholder="<?= e((string)$spec['extra'][1]) ?>"><?= e($item['extra'] ?? '') ?></textarea></label>
        <?php endif; ?>

        <div class="row three">
          <?php if (!empty($spec['meta1'][0])): ?>
            <label class="field"><span><?= e($spec['meta1'][0]) ?></span>
              <input name="meta1" maxlength="200" placeholder="<?= e((string)$spec['meta1'][1]) ?>"
                     value="<?= e($item['meta1'] ?? '') ?>"></label>
          <?php endif; ?>
          <?php if ($kind === 'pillar'): ?>
            <label class="field"><span>Indicator style</span>
              <select name="icon">
                <?php foreach (['meter' => 'Progress meter', 'bars' => 'Equaliser bars', 'gauge' => 'Gauge',
                                'ticks' => 'Uptime ticks', 'orbits' => 'Orbits', 'devices' => 'Devices'] as $k => $v): ?>
                  <option value="<?= $k ?>"<?= ($item['icon'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select></label>
          <?php endif; ?>
          <label class="field"><span>Sort order</span>
            <input name="sort_order" type="number" value="<?= e((string)($item['sort_order'] ?? 0)) ?>"></label>
          <label class="field check" style="align-self:end">
            <input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>>
            <span>Shown on the site</span></label>
        </div>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save' : 'Add' ?></button>
        <a class="btn ghost" href="content.php?kind=<?= e($kind) ?>">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$items = Content::items($kind, false);
$PAGE_TITLE = 'Content';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="content.php?kind=' . e($kind) . '&action=new">Add item</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach (Content::KINDS as $k => $spec2): ?>
    <a href="?kind=<?= e($k) ?>" class="<?= $kind === $k ? 'on' : '' ?>"><?= e($spec2['name']) ?></a>
  <?php endforeach; ?>
</div>
<p class="hint"><?= e($spec['hint']) ?></p>

<section class="card">
  <?php if (!$items): ?>
    <div class="empty"><b>Nothing here yet</b><p><?= e($spec['hint']) ?></p>
      <a class="btn sm" href="content.php?kind=<?= e($kind) ?>&action=new">Add the first one</a></div>
  <?php else: ?>
    <form method="post" action="content.php?kind=<?= e($kind) ?>&action=reorder">
      <?= Csrf::field() ?>
      <div class="tablewrap"><table class="data">
        <thead><tr><th style="width:80px">Order</th><th><?= e($spec['label'][0]) ?></th>
          <th><?= e((string)($spec['title'][0] ?? 'Detail')) ?></th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><input type="number" name="sort[<?= (int)$it['id'] ?>]" value="<?= (int)$it['sort_order'] ?>"
                       style="width:70px;padding:6px 8px;border-radius:6px;border:1px solid var(--line-2);background:rgba(255,255,255,.03);color:var(--tx)"></td>
            <td><span class="t-main"><?= e($it['label']) ?></span>
                <?php if ($it['body']): ?><span class="t-sub"><?= e(excerpt($it['body'], 80)) ?></span><?php endif; ?></td>
            <td class="dim"><?= e(excerpt($it['title'] ?? '', 60) ?: '—') ?></td>
            <td><span class="badge <?= $it['is_active'] ? 'ok' : 'muted' ?>"><?= $it['is_active'] ? 'Shown' : 'Hidden' ?></span></td>
            <td><div class="acts">
              <a class="btn ghost sm" href="content.php?kind=<?= e($kind) ?>&action=edit&id=<?= (int)$it['id'] ?>">Edit</a>
              <button class="btn ghost sm" type="submit" formaction="content.php?kind=<?= e($kind) ?>&action=toggle&id=<?= (int)$it['id'] ?>" formnovalidate>
                <?= $it['is_active'] ? 'Hide' : 'Show' ?></button>
              <button class="btn danger sm" type="submit" formaction="content.php?kind=<?= e($kind) ?>&action=delete&id=<?= (int)$it['id'] ?>"
                      formnovalidate onclick="return confirm('Delete “<?= e(addslashes($it['label'])) ?>”?')">Delete</button>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card__body"><button class="btn ghost sm" type="submit">Save order</button></div>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
