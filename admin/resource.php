<?php
/**
 * One screen for every editable list. Which list is shown, and which fields it
 * has, comes from app/resources.php.
 */
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$key = (string) ($_GET['r'] ?? '');
$res = resource($key);
$table = safe_table($res['table']);

$editId = (int) ($_GET['edit'] ?? 0);
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'delete' && $id) {
        db_delete($table, $id);
        flash(ucfirst($res['singular']) . ' deleted.');
        redirect('admin/resource.php?r=' . $key);
    }

    if ($action === 'move' && $id) {
        // Swap sort values with the neighbour in the requested direction.
        $dir = post('dir') === 'up' ? 'up' : 'down';
        $row = one("SELECT * FROM {$table} WHERE id = :id", ['id' => $id]);
        if ($row) {
            $cmp = $dir === 'up' ? '<' : '>';
            $ord = $dir === 'up' ? 'DESC' : 'ASC';
            $neighbour = one(
                "SELECT * FROM {$table} WHERE (sort {$cmp} :s) OR (sort = :s AND id {$cmp} :id)
                 ORDER BY sort {$ord}, id {$ord} LIMIT 1",
                ['s' => $row['sort'], 'id' => $id]
            );
            if ($neighbour) {
                db_update($table, (int) $row['id'], ['sort' => (int) $neighbour['sort']]);
                db_update($table, (int) $neighbour['id'], ['sort' => (int) $row['sort']]);
                // Equal sort values would not reorder; nudge them apart.
                if ((int) $row['sort'] === (int) $neighbour['sort']) {
                    db_update($table, (int) $row['id'], ['sort' => (int) $row['sort'] + ($dir === 'up' ? -1 : 1)]);
                }
            }
        }
        redirect('admin/resource.php?r=' . $key);
    }

    $data   = resource_input($res);
    $errors = resource_validate($res, $data);

    if (!$errors) {
        if ($id) {
            db_update($table, $id, $data);
            flash('Saved.');
        } else {
            db_insert($table, $data);
            flash(ucfirst($res['singular']) . ' added.');
        }
        redirect('admin/resource.php?r=' . $key);
    }
    $editId = $id;
}

$list = all("SELECT * FROM {$table} ORDER BY sort ASC, id ASC");

/* The form shows either the row being edited, POSTed values after an error, or defaults. */
$current = [];
foreach ($res['fields'] as $name => $f) {
    $current[$name] = $f['default'] ?? '';
}
if ($editId) {
    foreach ($list as $row) {
        if ((int) $row['id'] === $editId) {
            $current = array_merge($current, $row);
            break;
        }
    }
}
if (is_post() && $errors) {
    $current = array_merge($current, resource_input($res), ['id' => $editId]);
}

admin_header($res['label']);
?>
<h1><?= e($res['label']) ?></h1>
<?php if (!empty($res['note'])): ?><p class="amuted"><?= e($res['note']) ?></p><?php endif; ?>

<?php if ($errors): ?>
  <div class="aflash aflash--err"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="asplit asplit--wide">
  <div>
    <?php if (!$list): ?>
      <p class="amuted">Nothing here yet. Add the first one with the form.</p>
    <?php else: ?>
      <table class="atable">
        <thead>
          <tr>
            <th class="anowrap">Order</th>
            <?php foreach ($res['list'] as $col): ?>
              <th><?= e($res['fields'][$col]['label'] ?? ucfirst($col)) ?></th>
            <?php endforeach; ?>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $row): ?>
            <tr<?= (int) $row['id'] === $editId ? ' class="ison"' : '' ?>>
              <td class="anowrap">
                <form method="post" class="ainline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="move">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button name="dir" value="up" class="abtn abtn--icon" title="Move up">↑</button>
                  <button name="dir" value="down" class="abtn abtn--icon" title="Move down">↓</button>
                </form>
              </td>
              <?php foreach ($res['list'] as $col):
                  $f = $res['fields'][$col] ?? ['type' => 'text'];
                  $v = (string) ($row[$col] ?? '');
                  if ($f['type'] === 'checkbox') {
                      $out = $v ? '<span class="atag atag--replied">yes</span>' : '<span class="atag">no</span>';
                  } elseif ($f['type'] === 'select') {
                      $out = e((string) ($f['options'][$v] ?? $v));
                  } else {
                      $out = e(mb_strimwidth($v, 0, 60, '…'));
                  }
              ?>
                <td><?= $out ?></td>
              <?php endforeach; ?>
              <td class="anowrap">
                <a class="abtn abtn--sm" href="<?= e(base_url('admin/resource.php?r=' . $key . '&edit=' . (int) $row['id'])) ?>">Edit</a>
                <form method="post" class="ainline" onsubmit="return confirm('Delete this <?= e($res['singular']) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="abtn abtn--sm abtn--danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="apanel">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $editId ?>">
      <h2><?= $editId ? 'Edit' : 'Add' ?> <?= e($res['singular']) ?></h2>

      <?php foreach ($res['fields'] as $name => $f):
          $val = (string) ($current[$name] ?? ''); ?>
        <div class="afield">
          <?php if ($f['type'] === 'checkbox'): ?>
            <label class="acheck">
              <input type="checkbox" name="<?= e($name) ?>" value="1"<?= $val ? ' checked' : '' ?>>
              <?= e($f['label']) ?>
            </label>
          <?php else: ?>
            <label for="f_<?= e($name) ?>"><?= e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea id="f_<?= e($name) ?>" name="<?= e($name) ?>" rows="4"><?= e($val) ?></textarea>
            <?php elseif ($f['type'] === 'select'): ?>
              <select id="f_<?= e($name) ?>" name="<?= e($name) ?>">
                <?php foreach ($f['options'] as $ov => $ol): ?>
                  <option value="<?= e((string) $ov) ?>"<?= (string) $ov === $val ? ' selected' : '' ?>><?= e($ol) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($f['hint'])): ?><p class="ahint"><?= e($f['hint']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="arow">
        <button type="submit"><?= $editId ? 'Save changes' : 'Add ' . e($res['singular']) ?></button>
        <?php if ($editId): ?>
          <a class="abtn" href="<?= e(base_url('admin/resource.php?r=' . $key)) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </aside>
</div>
<?php admin_footer(); ?>
