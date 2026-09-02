<?php
/**
 * One screen that lists, edits, creates and deletes any table defined in
 * app/resources.php. Everything the site shows — services, pricing,
 * portfolio, marketplace listings — is edited here.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';
require_once APP_DIR . '/resources.php';

$type = get('type');
$res  = resource($type);
if (!$res) {
    http_response_code(404);
    admin_head('Not found');
    admin_page_head('Unknown section', 'There is nothing to edit at that address.');
    admin_foot();
    exit;
}

$table  = $res['table'];
$listUrl = 'resource.php?type=' . urlencode($type);
$action = get('action', 'list');
$id     = get_int('id');
$row    = $id ? db_one("SELECT * FROM {$table} WHERE id = ?", [$id]) : null;
if ($id && !$row) {
    flash('That ' . strtolower($res['singular']) . ' no longer exists.', 'bad');
    redirect($listUrl);
}

/* --- delete ---------------------------------------------------------- */
if (is_post() && post('action') === 'delete') {
    csrf_check();
    $victim = db_one("SELECT * FROM {$table} WHERE id = ?", [post_int('id')]);
    if ($victim) {
        if (!empty($res['image']) && !empty($victim[$res['image']])) {
            delete_upload($victim[$res['image']]);
        }
        if (!empty($victim['file_path'])) {
            delete_stored_file($victim['file_path']);
        }
        db_delete($table, (int) $victim['id']);
        log_activity('Deleted ' . strtolower($res['singular']), $type, (int) $victim['id']);
        flash(esc($res['singular']) . ' deleted.');
    }
    redirect($listUrl);
}

/* --- save ------------------------------------------------------------- */
$errors = [];
if (is_post() && post('action') === 'save') {
    csrf_check();
    $data = [];

    foreach ($res['fields'] as $f) {
        [$key, $label, $ftype] = $f;

        switch ($ftype) {
            case 'check':
                $data[$key] = isset($_POST[$key]) ? 1 : 0;
                break;

            case 'number':
                $data[$key] = post_int($key);
                break;

            case 'money':
                $raw = str_replace([',', ' '], '', post($key));
                if ($raw === '') {
                    $data[$key] = $key === 'sale_price' ? null : 0;
                } elseif (!is_numeric($raw)) {
                    $errors[] = $label . ' must be a number.';
                } else {
                    $data[$key] = round((float) $raw, 2);
                }
                break;

            case 'date':
                $v = post($key);
                if ($v === '') {
                    $data[$key] = null;
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $errors[] = $label . ' is not a valid date.';
                } else {
                    $data[$key] = $v;
                }
                break;

            case 'file':
                $err = null;
                $new = handle_file_upload($key, $err);
                if ($err) {
                    $errors[] = $err;
                } elseif ($new) {
                    if ($row && $row[$key]) {
                        delete_stored_file($row[$key]);
                    }
                    $data[$key] = $new;
                    $data['file_name'] = (string) ($_FILES[$key]['name'] ?? '');
                } elseif (post('remove_' . $key) === '1') {
                    if ($row && $row[$key]) {
                        delete_stored_file($row[$key]);
                    }
                    $data[$key]        = '';
                    $data['file_name'] = '';
                }
                break;

            case 'image':
                $err = null;
                $new = handle_upload($key, $err);
                if ($err) {
                    $errors[] = $err;
                } elseif ($new) {
                    if ($row && $row[$key]) {
                        delete_upload($row[$key]);
                    }
                    $data[$key] = $new;
                } elseif (post('remove_' . $key) === '1') {
                    if ($row && $row[$key]) {
                        delete_upload($row[$key]);
                    }
                    $data[$key] = '';
                }
                break;

            case 'slug':
                $src         = post($key) !== '' ? post($key) : post($res['slug_from'] ?? 'title');
                $data[$key]  = unique_slug($table, slugify($src), $id);
                break;

            default:
                $data[$key] = post($key);
        }
    }

    /* the first field is always required */
    $firstKey = $res['fields'][0][0];
    if (($data[$firstKey] ?? '') === '') {
        $errors[] = $res['fields'][0][1] . ' cannot be empty.';
    }

    if (!$errors) {
        if ($row) {
            db_update($table, (int) $row['id'], $data);
            log_activity('Updated ' . strtolower($res['singular']), $type, (int) $row['id']);
            flash(esc($res['singular']) . ' saved.');
            redirect($listUrl);
        }
        if (!empty($res['timestamps'])) {
            $data['created_at'] = now();
        }
        $newId = db_insert($table, $data);
        log_activity('Added ' . strtolower($res['singular']), $type, $newId);
        flash(esc($res['singular']) . ' added.');
        redirect($listUrl);
    }

    /* keep what was typed so nothing is lost on an error */
    $row = array_merge($row ?? [], $data);
}

/* ====================================================================== */
/* form                                                                   */
/* ====================================================================== */
if ($action === 'edit' || $action === 'new') {
    $isNew = $action === 'new';
    admin_head(($isNew ? 'Add ' : 'Edit ') . $res['singular'], 'resource.php?type=' . $type);
    admin_page_head(
        $isNew ? 'Add a ' . strtolower($res['singular']) : 'Edit ' . strtolower($res['singular']),
        $res['blurb'],
        [],
        [[$listUrl, $res['title']], [null, $isNew ? 'New' : ($row['title'] ?? $row['name'] ?? 'Edit')]]
    );

    foreach ($errors as $e) {
        echo '<div class="flash bad">' . esc($e) . '</div>';
    }

    /* group fields into full-width and half-width runs */
    ?>
    <form method="post" class="admin" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <fieldset>
        <legend><?= esc($res['singular']) ?></legend>
<?php
    $buffer = [];
    $flush = function () use (&$buffer) {
        if (!$buffer) {
            return;
        }
        echo '<div class="grid2">';
        foreach ($buffer as $html) {
            echo $html;
        }
        echo '</div>';
        $buffer = [];
    };

    foreach ($res['fields'] as $f) {
        $key   = $f[0];
        $label = $f[1];
        $ftype = $f[2];
        $width = $f[3] ?? 'full';
        $hint  = $f[4] ?? '';
        $opts  = $f[5] ?? [];
        $value = $row[$key] ?? ($ftype === 'check' && $isNew ? 1 : '');

        ob_start();
        ?>
        <div class="f">
          <label for="<?= esc($key) ?>"><?= esc($label) ?></label>
<?php
        switch ($ftype) {
            case 'textarea':
            case 'lines':
                ?><textarea id="<?= esc($key) ?>" name="<?= esc($key) ?>"
                    class="<?= $ftype === 'textarea' ? 'tall' : '' ?>"><?= esc((string) $value) ?></textarea><?php
                break;

            case 'select':
                ?><select id="<?= esc($key) ?>" name="<?= esc($key) ?>">
<?php           foreach ($opts as $ov => $ol): ?>
                  <option value="<?= esc((string) $ov) ?>"<?= (string) $value === (string) $ov ? ' selected' : '' ?>>
                    <?= esc($ol) ?></option>
<?php           endforeach; ?>
                </select><?php
                break;

            case 'check':
                ?><label class="check">
                    <input type="checkbox" name="<?= esc($key) ?>" value="1"<?= $value ? ' checked' : '' ?>>
                    <span><?= esc($hint ?: $label) ?></span></label><?php
                $hint = '';
                break;

            case 'file':
                if ($value) {
                    $full = APP_ROOT . '/' . $value;
                    ?><p style="font-size:14px;color:var(--mute);margin-bottom:8px">
                        Currently: <strong style="color:var(--fg)"><?=
                          esc($row['file_name'] ?: basename((string) $value)) ?></strong>
                        <?= is_file($full) ? '(' . number_format(filesize($full) / 1048576, 1) . ' MB)'
                                           : '<span style="color:var(--bad)">— missing from disk</span>' ?>
                      </p>
                    <label class="check" style="margin-bottom:8px">
                      <input type="checkbox" name="remove_<?= esc($key) ?>" value="1">
                      <span>Remove this file</span></label><?php
                }
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="file"
                         accept=".zip,.gz,.tgz,.rar,.7z,.pdf"><?php
                break;

            case 'image':
                if ($value) {
                    ?><img class="thumb" src="../<?= esc((string) $value) ?>" alt="">
                    <label class="check" style="margin-top:8px">
                      <input type="checkbox" name="remove_<?= esc($key) ?>" value="1">
                      <span>Remove this image</span></label><?php
                }
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="file"
                         accept="image/jpeg,image/png,image/gif,image/webp"><?php
                break;

            case 'number':
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="number"
                         value="<?= esc((string) ($value === '' ? 0 : $value)) ?>"><?php
                break;

            case 'money':
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="text" inputmode="decimal"
                         value="<?= esc($value === null ? '' : (string) $value) ?>"><?php
                break;

            case 'date':
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" type="date"
                         value="<?= esc((string) $value) ?>"><?php
                break;

            case 'slug':
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" data-slug-target
                         value="<?= esc((string) $value) ?>"><?php
                break;

            default:
                $isSource = ($res['slug_from'] ?? '') === $key;
                ?><input id="<?= esc($key) ?>" name="<?= esc($key) ?>"
                         <?= $isSource ? 'data-slug-source' : '' ?>
                         value="<?= esc((string) $value) ?>"><?php
        }

        if ($hint) {
            echo '<span class="hint">' . esc($hint) . '</span>';
        }
        ?>
        </div>
<?php
        $html = (string) ob_get_clean();

        if ($width === 'half') {
            $buffer[] = $html;
            if (count($buffer) === 2) {
                $flush();
            }
        } else {
            $flush();
            echo $html;
        }
    }
    $flush();
    ?>
      </fieldset>
      <div class="formbar">
        <button class="btn" type="submit">
          <?= $isNew ? 'Add ' . esc(strtolower($res['singular'])) : 'Save changes' ?></button>
        <a class="btn ghost" href="<?= esc($listUrl) ?>">Cancel</a>
<?php if (!$isNew && !empty($res['view']) && !empty($row['slug'])): ?>
        <a class="btn ghost" href="../<?= esc($res['view'] . urlencode($row['slug'])) ?>"
           target="_blank" rel="noopener">View on the site &nearr;</a>
<?php endif; ?>
      </div>
    </form>

<?php if (!$isNew): ?>
    <form method="post" class="admin" style="margin-top:26px"
          data-confirm="Delete this <?= esc(strtolower($res['singular'])) ?>? This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
      <fieldset>
        <legend>Danger</legend>
        <p style="color:var(--mute);font-size:14px;margin-bottom:14px">
          Deleting removes this <?= esc(strtolower($res['singular'])) ?> and its image for good.
          There is no undo and no recycle bin.</p>
        <button class="btn danger" type="submit">Delete this <?= esc(strtolower($res['singular'])) ?></button>
      </fieldset>
    </form>
<?php endif; ?>
<?php
    admin_foot();
    exit;
}

/* ====================================================================== */
/* list                                                                   */
/* ====================================================================== */
$rows = db_all("SELECT * FROM {$table} ORDER BY {$res['order']}");

admin_head($res['title'], 'resource.php?type=' . $type);
admin_page_head($res['title'], $res['blurb'], [
    [$listUrl . '&action=new', 'Add ' . esc(strtolower($res['singular'])), ''],
]);
?>

<div class="panel">
<?php if (!$rows): ?>
  <div class="empty">
    <strong>Nothing here yet</strong>
    <p>Add the first <?= esc(strtolower($res['singular'])) ?> and it appears on the site straight away.</p>
    <p style="margin-top:16px"><a class="btn" href="<?= esc($listUrl) ?>&amp;action=new">Add
      <?= esc(strtolower($res['singular'])) ?></a></p>
  </div>
<?php else: ?>
  <div class="tablewrap">
    <table>
      <thead><tr>
<?php foreach ($res['list'] as $col => $label): ?>
        <th><?= esc($label) ?></th>
<?php endforeach; ?>
        <th class="right">&nbsp;</th>
      </tr></thead>
      <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
<?php   $first = true;
        foreach ($res['list'] as $col => $label):
          $v = $r[$col] ?? ''; ?>
          <td>
<?php     if ($col === ($res['image'] ?? '')): ?>
<?php       if ($v): ?><img class="thumb sm" src="../<?= esc((string) $v) ?>" alt="">
<?php       else: ?><span class="pill none">None</span><?php endif; ?>
<?php     elseif ($col === 'is_active' || $col === 'is_featured'): ?>
            <span class="pill <?= $v ? 'ok' : '' ?>"><?= $v ? 'Live' : 'Hidden' ?></span>
<?php     elseif ($col === 'visibility'): ?>
            <span class="pill <?= $v === 'public' ? 'ok' : 'urgent' ?>">
              <?= $v === 'public' ? 'Public' : 'Admin only' ?></span>
<?php     elseif ($col === 'kind'): ?>
            <span class="pill"><?= $v === 'care' ? 'Care plan' : 'Build' ?></span>
<?php     elseif ($col === 'completed_on'): ?>
            <?= esc(date_human($v)) ?>
<?php     elseif ($col === 'price'): ?>
            <span class="num"><?= esc($v === '' || $v === null ? 'Quoted'
                : setting('site.currency', '$') . rtrim(rtrim(number_format((float) $v, 2), '0'), '.')) ?></span>
<?php     elseif ($first): ?>
            <a class="rowlink" href="<?= esc($listUrl) ?>&amp;action=edit&amp;id=<?= (int) $r['id'] ?>">
              <?= esc(excerpt((string) $v, 12)) ?></a>
<?php     else: ?>
            <?= esc(excerpt((string) $v, 12)) ?>
<?php     endif; ?>
          </td>
<?php     $first = false;
        endforeach; ?>
          <td class="right">
            <div class="rowacts">
              <a class="btn ghost sm" href="<?= esc($listUrl) ?>&amp;action=edit&amp;id=<?= (int) $r['id'] ?>">Edit</a>
              <form method="post" data-confirm="Delete &quot;<?= esc(strip_tags((string) ($r['title'] ?? $r['name'] ?? $r['question'] ?? $r['author'] ?? 'this item'))) ?>&quot;? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn danger sm" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
