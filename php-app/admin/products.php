<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'products.php');
$pdo = db();

$CATEGORIES = ['Templates', 'AI Agents', 'Dashboards', 'Bundles', 'Themes'];
$PRICING_TYPES = ['monthly' => 'Monthly subscription', 'fixed' => 'One-time fixed price'];

function slugify_id(string $name, PDO $pdo, ?string $keepId = null): string
{
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    $base = trim($base, '-') ?: 'product';
    $base = substr($base, 0, 16);
    $id = $base;
    $n = 1;
    while (true) {
        if ($id === $keepId) {
            return $id;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $id;
        }
        $n++;
        $id = $base . '-' . $n;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $existingId = trim((string)($_POST['existing_id'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $category = in_array($_POST['category'] ?? '', $CATEGORIES, true) ? $_POST['category'] : $CATEGORIES[0];
        $icon = trim((string)($_POST['icon'] ?? 'box'));
        $price = max(0, (float)($_POST['price'] ?? 0));
        $pricingType = array_key_exists($_POST['pricing_type'] ?? '', $PRICING_TYPES) ? $_POST['pricing_type'] : 'monthly';
        $rating = min(5, max(0, (float)($_POST['rating'] ?? 4.5)));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $specsRaw = (string)($_POST['specs'] ?? '');
        $specs = array_values(array_filter(array_map('trim', explode("\n", $specsRaw))));

        if ($name === '' || $tagline === '') {
            flash('Name and tagline are required.', 'error');
        } elseif ($existingId !== '') {
            $stmt = $pdo->prepare('UPDATE products SET name=?, category=?, icon=?, price=?, pricing_type=?, rating=?, tagline=?, description=?, specs_json=? WHERE id=?');
            $stmt->execute([$name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $existingId]);
            flash('Product updated.');
        } else {
            $id = slugify_id($name, $pdo);
            $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM products')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO products (id, name, category, icon, price, pricing_type, rating, tagline, description, specs_json, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$id, $name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $maxSort + 1]);
            flash('Product added — now live on the public marketplace.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([(string)($_POST['id'] ?? '')]);
        flash('Product removed from the marketplace.');
    }
    header('Location: products.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([(string)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}
$editingSpecs = $editing ? implode("\n", json_decode($editing['specs_json'], true) ?: []) : '';

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC')->fetchAll();
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() . ui_zoom_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Products — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'products.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Marketplace products</h1><p class="lede" style="margin-bottom:0;">Changes here go live on the public marketplace immediately.</p></div>
    <?php if (!$editing): ?><button class="btn btn-primary" type="button" id="addBtn" onclick="toggleAddForm('add')"><?= ico('plus') ?> Add a product</button><?php endif; ?>
  </div>

  <div class="card admin-form-card" id="addCard"<?= $editing ? '' : ' hidden' ?>>
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit product' : 'Add a product' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="existing_id" value="<?= e($editing['id'] ?? '') ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Name</label><input name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="field"><label>Category</label><select name="category">
          <?php foreach ($CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= ($editing['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="grid grid-4" style="gap:16px;">
        <div class="field"><label>Icon name</label><input name="icon" value="<?= e($editing['icon'] ?? 'box') ?>" placeholder="cart, chat, box, star…"></div>
        <div class="field"><label>Price (USD)</label><input type="number" min="0" step="1" name="price" value="<?= e((string)($editing['price'] ?? 99)) ?>"></div>
        <div class="field"><label>Pricing type</label><select name="pricing_type">
          <?php foreach ($PRICING_TYPES as $val => $label): ?><option value="<?= e($val) ?>" <?= ($editing['pricing_type'] ?? 'monthly') === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Rating (0–5)</label><input type="number" min="0" max="5" step="0.1" name="rating" value="<?= e((string)($editing['rating'] ?? 4.8)) ?>"></div>
      </div>
      <div class="field"><label>Tagline (one line, shown on the card)</label><input name="tagline" required value="<?= e($editing['tagline'] ?? '') ?>"></div>
      <div class="field"><label>Full description</label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
      <div class="field"><label>What's included (one per line)</label><textarea name="specs" placeholder="One feature per line"><?= e($editingSpecs) ?></textarea></div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add product' ?></button>
        <?php if ($editing): ?><a href="products.php" class="btn btn-ghost">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" onclick="toggleAddForm('add')">Cancel</button><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Rating</th><th></th></tr></thead><tbody>
      <?php foreach ($products as $p): ?>
      <tr>
        <td style="font-weight:600;"><?= e($p['name']) ?></td>
        <td><?= e($p['category']) ?></td>
        <td>$<?= number_format((float)$p['price'], 0) ?><?= $p['pricing_type'] === 'monthly' ? '/mo' : '' ?></td>
        <td><span class="badge"><?= ico('star') ?> <?= number_format((float)$p['rating'], 1) ?></span></td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="products.php?edit=<?= urlencode($p['id']) ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($p['name'])) ?> from the marketplace?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="5" style="color:var(--ink-faint);">No products yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'products.php') ?>
</body>
</html>
