<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'content.php');
$pdo = db();

// A curated set of icon keys that exist in the public site's icon set
// (assets/app.js ICONS) — kept separate from the admin panel's own
// smaller icon set in includes/icons.php.
$ICON_CHOICES = [
    'monitor', 'code', 'globe', 'rocket', 'chart', 'cart', 'heart', 'gear',
    'spark', 'flag', 'box', 'shield', 'users', 'chat', 'star', 'compass',
    'target', 'bolt', 'layers', 'refresh', 'mail', 'phone', 'calendar',
    'lock', 'book', 'search', 'cloud', 'puzzle',
];

/**
 * One entry per Content tab. Each field's key is also its form field
 * name; 'column' overrides the DB column name when it differs (e.g. a
 * "lines" field stored as JSON in a `..._json` column). 'title' says
 * which field to show as each list row's heading.
 */
$SECTIONS = [
    'services' => [
        'table' => 'content_services', 'label' => 'Service', 'title' => 'name',
        'fields' => [
            'icon' => ['type' => 'icon', 'label' => 'Icon'],
            'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
            'blurb' => ['type' => 'text', 'label' => 'Short description'],
            'bullets' => ['type' => 'lines', 'label' => 'Bullet points (one per line)', 'column' => 'bullets_json'],
        ],
    ],
    'industries' => [
        'table' => 'content_industries', 'label' => 'Industry', 'title' => 'name',
        'fields' => [
            'icon' => ['type' => 'icon', 'label' => 'Icon'],
            'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
            'out' => ['type' => 'lines', 'label' => 'What they get (one per line)', 'column' => 'out_json'],
        ],
    ],
    'cases' => [
        'table' => 'content_case_studies', 'label' => 'Case study', 'title' => 'client',
        'fields' => [
            'client' => ['type' => 'text', 'label' => 'Client name', 'required' => true],
            'sector' => ['type' => 'text', 'label' => 'Sector', 'placeholder' => 'e.g. Bakery'],
            'icon' => ['type' => 'icon', 'label' => 'Icon'],
            'stat' => ['type' => 'text', 'label' => 'Stat', 'placeholder' => 'e.g. +64%'],
            'stat_label' => ['type' => 'text', 'label' => 'Stat label', 'placeholder' => 'e.g. online orders in month one'],
            'quote' => ['type' => 'text', 'label' => 'Quote'],
            'body' => ['type' => 'textarea', 'label' => 'Story'],
        ],
    ],
    'faqs' => [
        'table' => 'content_pricing_faqs', 'label' => 'FAQ', 'title' => 'question',
        'fields' => [
            'question' => ['type' => 'text', 'label' => 'Question', 'required' => true],
            'answer' => ['type' => 'textarea', 'label' => 'Answer'],
        ],
    ],
    'team' => [
        'table' => 'content_team', 'label' => 'Team member', 'title' => 'name',
        'fields' => [
            'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
            'role' => ['type' => 'text', 'label' => 'Role'],
            'initials' => ['type' => 'text', 'label' => 'Initials', 'maxlength' => 3],
        ],
    ],
    'values' => [
        'table' => 'content_values', 'label' => 'Value', 'title' => 'title',
        'fields' => [
            'icon' => ['type' => 'icon', 'label' => 'Icon'],
            'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
            'description' => ['type' => 'textarea', 'label' => 'Description'],
        ],
    ],
];

function icon_select(string $name, string $selected, array $choices): string
{
    $out = '<select name="' . e($name) . '">';
    foreach ($choices as $choice) {
        $sel = $choice === $selected ? ' selected' : '';
        $out .= '<option value="' . e($choice) . '"' . $sel . '>' . e($choice) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

function lines_to_array(string $text): array
{
    $lines = array_map('trim', explode("\n", str_replace("\r\n", "\n", $text)));
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

$activeTab = $_GET['tab'] ?? 'services';
if (!isset($SECTIONS[$activeTab])) {
    $activeTab = 'services';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? '')) || !isset($SECTIONS[$section])) {
        flash('Your session expired — please try again.', 'error');
        header('Location: content.php?tab=' . e($activeTab));
        exit;
    }

    $cfg = $SECTIONS[$section];
    $table = $cfg['table'];
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [];
        $errors = [];
        foreach ($cfg['fields'] as $key => $f) {
            $col = $f['column'] ?? $key;
            switch ($f['type']) {
                case 'lines':
                    $data[$col] = json_encode(lines_to_array((string)($_POST[$key] ?? '')));
                    break;
                case 'number_nullable':
                    $v = trim((string)($_POST[$key] ?? ''));
                    $data[$col] = $v === '' ? null : max(0, (int)$v);
                    break;
                case 'checkbox':
                    $data[$col] = isset($_POST[$key]) ? 1 : 0;
                    break;
                default:
                    $data[$col] = trim((string)($_POST[$key] ?? ''));
            }
            if (!empty($f['required']) && trim((string)($_POST[$key] ?? '')) === '') {
                $errors[] = $f['label'] . ' is required.';
            }
        }

        if ($errors) {
            flash(implode(' ', $errors), 'error');
        } elseif ($id > 0) {
            $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
            $pdo->prepare("UPDATE `$table` SET $sets WHERE id = ?")->execute([...array_values($data), $id]);
            flash($cfg['label'] . ' updated.');
        } else {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM `$table`")->fetchColumn();
            $data['sort_order'] = $maxOrder + 1;
            $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
            $qs = implode(',', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($qs)")->execute(array_values($data));
            flash($cfg['label'] . ' added.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        flash($cfg['label'] . ' removed.');
    }
    header('Location: content.php?tab=' . e($section));
    exit;
}

$editingId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editingId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `{$SECTIONS[$activeTab]['table']}` WHERE id = ?");
    $stmt->execute([$editingId]);
    $editing = $stmt->fetch() ?: null;
}

$rowsBySection = [];
foreach ($SECTIONS as $key => $cfg) {
    $rowsBySection[$key] = $pdo->query("SELECT * FROM `{$cfg['table']}` ORDER BY sort_order ASC, id ASC")->fetchAll();
}

$token = csrf_token();

function content_field_html(string $key, array $f, ?array $editing): string
{
    global $ICON_CHOICES;
    $col = $f['column'] ?? $key;
    $value = $editing[$col] ?? '';
    switch ($f['type']) {
        case 'icon':
            return icon_select($key, (string)$value, $ICON_CHOICES);
        case 'lines':
            $lines = $value !== '' ? (json_decode((string)$value, true) ?: []) : [];
            return '<textarea name="' . e($key) . '">' . e(implode("\n", $lines)) . '</textarea>';
        case 'textarea':
            return '<textarea name="' . e($key) . '">' . e((string)$value) . '</textarea>';
        case 'number_nullable':
            return '<input name="' . e($key) . '" value="' . e($value === null ? '' : (string)$value) . '" inputmode="numeric">';
        case 'checkbox':
            return '<input type="checkbox" name="' . e($key) . '" ' . (!empty($value) ? 'checked' : '') . '>';
        default:
            $attrs = !empty($f['maxlength']) ? ' maxlength="' . (int)$f['maxlength'] . '"' : '';
            $placeholder = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
            return '<input name="' . e($key) . '" value="' . e((string)$value) . '"' . $attrs . $placeholder . '>';
    }
}
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<title>Content — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
<style>.content-row{ display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border-soft); }
.content-row:last-child{ border-bottom:none; }
.content-row-title{ font-weight:600; font-size:.92rem; }
.content-row-sub{ font-size:.8rem; color:var(--ink-faint); }</style>
</head>
<body>
<?= admin_header($staff, 'content.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Site content</h1><p class="lede" style="margin-bottom:0;">Add, edit or remove the Services, Industries, Case Studies, Pricing FAQ and About page content shown on the public site. The Pricing page's "Starting from" price is in Settings.</p></div>
  </div>

  <?php
  $tabList = [
      'services' => ['grid', 'Services'], 'industries' => ['users', 'Industries'], 'cases' => ['star', 'Case Studies'],
      'faqs' => ['chat', 'Pricing FAQ'], 'team' => ['edit', 'Team'], 'values' => ['shield', 'Values'],
  ];
  ?>
  <div class="tab-labels">
    <?php foreach ($tabList as $slug => [$icon, $label]): ?>
    <a href="content.php?tab=<?= e($slug) ?>" class="<?= $activeTab === $slug ? 'active' : '' ?>"><?= ico($icon) ?> <?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php $cfg = $SECTIONS[$activeTab]; ?>
  <?php if (!$editing): ?>
  <div class="flex" style="justify-content:flex-end;margin-bottom:16px;">
    <button class="btn btn-primary" type="button" id="addBtn" onclick="toggleAddForm('add')"><?= ico('plus') ?> Add a <?= strtolower($cfg['label']) ?></button>
  </div>
  <?php endif; ?>
  <div class="card admin-form-card" id="addCard"<?= $editing ? '' : ' hidden' ?>>
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit ' . strtolower($cfg['label']) : 'Add a ' . strtolower($cfg['label']) ?></h3></div>
    <form method="post">
      <input type="hidden" name="section" value="<?= e($activeTab) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <?php foreach ($cfg['fields'] as $key => $f): ?>
      <?php if ($f['type'] === 'checkbox'): ?>
      <label class="flex items-center gap-8" style="font-size:.85rem;margin-bottom:14px;"><?= content_field_html($key, $f, $editing) ?> <?= e($f['label']) ?></label>
      <?php else: ?>
      <div class="field"><label><?= e($f['label']) ?></label><?= content_field_html($key, $f, $editing) ?></div>
      <?php endif; ?>
      <?php endforeach; ?>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add ' . strtolower($cfg['label']) ?></button>
        <?php if ($editing): ?><a href="content.php?tab=<?= e($activeTab) ?>" class="btn btn-ghost">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" onclick="toggleAddForm('add')">Cancel</button><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <?php $rows = $rowsBySection[$activeTab]; ?>
    <?php foreach ($rows as $r): ?>
    <div class="content-row">
      <div>
        <div class="content-row-title"><?= e((string)($r[$cfg['title']] ?? '')) ?></div>
        <?php if ($activeTab === 'cases'): ?><div class="content-row-sub"><?= e($r['sector']) ?></div><?php endif; ?>
        <?php if ($activeTab === 'team'): ?><div class="content-row-sub"><?= e($r['role']) ?></div><?php endif; ?>
      </div>
      <div class="admin-actions-cell">
        <a class="icon-btn" href="content.php?tab=<?= e($activeTab) ?>&edit=<?= (int)$r['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
        <form method="post" onsubmit="return confirm('Remove this <?= strtolower($cfg['label']) ?>?');">
          <input type="hidden" name="section" value="<?= e($activeTab) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p style="color:var(--ink-faint);padding:12px 0;">None yet — add the first one above.</p><?php endif; ?>
  </div>
</main>
<?= admin_bottomnav($staff, 'content.php') ?>
</body>
</html>
