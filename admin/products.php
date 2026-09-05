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

/**
 * Handles the optional "downloadable file" upload for a product.
 * Returns: null (no change — keep whatever's already saved), '' (the
 * admin asked to remove the current file), or a new relative path to
 * save into products.download_path.
 */
function product_file_upload(string $productId, ?string &$error): ?string
{
    $uploadDir = __DIR__ . '/../assets/uploads/products';

    $hasNewFile = !empty($_FILES['download_file']['name'])
        && ($_FILES['download_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    // "Remove" used to win outright, so a replacement uploaded in the same
    // submit was deleted along with the old file, without a word.
    if (isset($_POST['remove_download']) && !$hasNewFile) {
        foreach (glob($uploadDir . '/' . $productId . '.*') ?: [] as $old) {
            @unlink($old);
        }
        return '';
    }

    if (!$hasNewFile) {
        return null;
    }
    if ($_FILES['download_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'That file upload failed — please try again.';
        return null;
    }
    if ($_FILES['download_file']['size'] > 25 * 1024 * 1024) {
        $error = 'That file is over 25MB — please use a smaller file.';
        return null;
    }
    $ext = strtolower(pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['zip', 'pdf'], true)) {
        $error = 'Unsupported file type — allowed: zip, pdf.';
        return null;
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        $error = 'Could not create assets/uploads/products/ — check folder permissions.';
        return null;
    }
    $filename = $productId . '.' . $ext;
    if (!move_uploaded_file($_FILES['download_file']['tmp_name'], $uploadDir . '/' . $filename)) {
        $error = 'Could not save the uploaded file — check that assets/uploads/products/ is writable.';
        return null;
    }
    foreach (glob($uploadDir . '/' . $productId . '.*') ?: [] as $old) {
        if (basename($old) !== $filename) {
            @unlink($old);
        }
    }
    return 'assets/uploads/products/' . $filename;
}

/**
 * Same null/''/path contract as product_file_upload(), but for a real
 * photo/screenshot of the product — validated as an actual image, and
 * stored under a distinct "-img" basename so it never collides with the
 * downloadable file above.
 */
function product_image_upload(string $productId, ?string &$error): ?string
{
    $uploadDir = __DIR__ . '/../assets/uploads/products';
    $basename = $productId . '-img';

    $hasNewImage = !empty($_FILES['image_file']['name'])
        && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (isset($_POST['remove_image']) && !$hasNewImage) {
        foreach (glob($uploadDir . '/' . $basename . '.*') ?: [] as $old) {
            @unlink($old);
        }
        return '';
    }

    if (!$hasNewImage) {
        return null;
    }
    if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'That image upload failed — please try again.';
        return null;
    }
    if ($_FILES['image_file']['size'] > 5 * 1024 * 1024) {
        $error = 'That image is over 5MB — please use a smaller file.';
        return null;
    }
    $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $error = 'Unsupported image type — allowed: jpg, png, webp.';
        return null;
    }
    if (!@getimagesize($_FILES['image_file']['tmp_name'])) {
        $error = 'That file does not look like a valid image.';
        return null;
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        $error = 'Could not create assets/uploads/products/ — check folder permissions.';
        return null;
    }
    $filename = $basename . '.' . $ext;
    if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . '/' . $filename)) {
        $error = 'Could not save the uploaded image — check that assets/uploads/products/ is writable.';
        return null;
    }
    foreach (glob($uploadDir . '/' . $basename . '.*') ?: [] as $old) {
        if (basename($old) !== $filename) {
            @unlink($old);
        }
    }
    return 'assets/uploads/products/' . $filename;
}

/**
 * Pre-flight check for one upload field, run for every file before any of
 * them is moved, so a product save is all-or-nothing on disk.
 */
function product_validate_upload(string $field, array $allowedExts, int $maxMb, bool $mustBeImage, ?string &$error): void
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error = 'That upload failed — please try again.';
        return;
    }
    if ($_FILES[$field]['size'] > $maxMb * 1024 * 1024) {
        $error = 'That file is over ' . $maxMb . 'MB — please use a smaller one.';
        return;
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $error = 'Unsupported file type — allowed: ' . implode(', ', $allowedExts) . '.';
        return;
    }
    if ($mustBeImage && !@getimagesize($_FILES[$field]['tmp_name'])) {
        $error = 'That file does not look like a valid image.';
    }
}

/** Resolves an upload helper's null/''/path result against the current
 * saved value: null keeps it unchanged, '' clears it, anything else
 * replaces it. */
function resolve_upload(?string $uploadResult, ?string $current): ?string
{
    if ($uploadResult === null) {
        return $current;
    }
    return $uploadResult === '' ? null : $uploadResult;
}

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
            flash_input($_POST);
            flash('Name and tagline are required.', 'error');
        } else {
            $id = $existingId !== '' ? $existingId : slugify_id($name, $pdo);
            $currentDownload = null;
            $currentImage = null;
            if ($existingId !== '') {
                $cur = $pdo->prepare('SELECT download_path, image_path FROM products WHERE id = ?');
                $cur->execute([$existingId]);
                $curRow = $cur->fetch();
                $currentDownload = $curRow['download_path'] ?? null;
                $currentImage = $curRow['image_path'] ?? null;
            }
            // Validate both files before moving either. Previously the image
            // was moved to disk first, so a failing download upload left an
            // orphaned image behind with the row never updated to match.
            $uploadError = null;
            $imageError = null;
            product_validate_upload('download_file', ['zip', 'pdf'], 25, false, $uploadError);
            product_validate_upload('image_file', ['jpg', 'jpeg', 'png', 'webp'], 5, true, $imageError);

            if ($uploadError || $imageError) {
                flash_input($_POST);
                flash($uploadError ?: $imageError, 'error');
            } else {
            $downloadPath = resolve_upload(product_file_upload($id, $uploadError), $currentDownload);
            $imagePath = resolve_upload(product_image_upload($id, $imageError), $currentImage);

            if ($uploadError || $imageError) {
                flash($uploadError ?: $imageError, 'error');
            } elseif ($existingId !== '') {
                $pdo->prepare('UPDATE products SET name=?, category=?, icon=?, price=?, pricing_type=?, rating=?, tagline=?, description=?, specs_json=?, download_path=?, image_path=? WHERE id=?')
                    ->execute([$name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $downloadPath, $imagePath, $existingId]);
                flash('Product updated.');
            } else {
                $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM products')->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO products (id, name, category, icon, price, pricing_type, rating, tagline, description, specs_json, download_path, image_path, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$id, $name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $downloadPath, $imagePath, $maxSort + 1]);
                flash('Product added — now live on the public marketplace.');
            }
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = (string)($_POST['id'] ?? '');
        // product_orders used to cascade with the product, silently erasing
        // every sale of it and every buyer's download access. The foreign
        // key now restricts, so say why rather than surfacing a raw error.
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM product_orders WHERE product_id = ?');
        $inUse->execute([$deleteId]);
        if ((int)$inUse->fetchColumn() > 0) {
            flash('That product has orders against it, so deleting it would erase those customers\' purchase history and downloads. Remove its file instead to take it off sale.', 'error');
        } else {
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$deleteId]);
            // Leftover uploads used to linger forever, and the next product
            // that slugified to the same id inherited them.
            foreach (glob(__DIR__ . '/../assets/uploads/products/' . $deleteId . '.*') ?: [] as $old) {
                @unlink($old);
            }
            foreach (glob(__DIR__ . '/../assets/uploads/products/' . $deleteId . '-img.*') ?: [] as $old) {
                @unlink($old);
            }
            flash('Product removed from the marketplace.');
        }
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
$old = take_old_input();
// A failed save re-renders with whatever was typed, falling back to the
// stored row (when editing) and then to the field's default.
$val = function (string $key, $fallback = '') use ($old, $editing) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $editing[$key] ?? $fallback;
};
$editingSpecs = array_key_exists('specs', $old)
    ? (string)$old['specs']
    : ($editing ? implode("\n", json_decode($editing['specs_json'], true) ?: []) : '');
$formOpen = $editing || $old;

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC')->fetchAll();
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<title>Products — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
<?= ui_zoom_style() ?>
</head>
<body>
<?= admin_header($staff, 'products.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Marketplace products</h1><p class="lede" style="margin-bottom:0;">Changes here go live on the public marketplace immediately.</p></div>
    <?php if (!$formOpen): ?><button class="btn btn-primary" type="button" id="addBtn" onclick="toggleAddForm('add')"><?= ico('plus') ?> Add a product</button><?php endif; ?>
  </div>

  <div class="card admin-form-card" id="addCard"<?= $formOpen ? '' : ' hidden' ?>>
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit product' : 'Add a product' ?></h3></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="existing_id" value="<?= e($editing['id'] ?? '') ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Name</label><input name="name" required value="<?= e((string)$val('name')) ?>"></div>
        <div class="field"><label>Category</label><select name="category">
          <?php foreach ($CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= (string)$val('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="grid grid-4" style="gap:16px;">
        <div class="field"><label>Icon name</label><input name="icon" value="<?= e((string)$val('icon', 'box')) ?>" placeholder="cart, chat, box, star…"></div>
        <div class="field"><label>Price (USD)</label><input type="number" min="0" step="1" name="price" value="<?= e((string)$val('price', 99)) ?>"></div>
        <div class="field"><label>Pricing type</label><select name="pricing_type">
          <?php foreach ($PRICING_TYPES as $pt => $label): ?><option value="<?= e($pt) ?>" <?= (string)$val('pricing_type', 'monthly') === $pt ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Rating (0–5)</label><input type="number" min="0" max="5" step="0.1" name="rating" value="<?= e((string)$val('rating', 4.8)) ?>"></div>
      </div>
      <div class="field"><label>Tagline (one line, shown on the card)</label><input name="tagline" required value="<?= e((string)$val('tagline')) ?>"></div>
      <div class="field"><label>Full description</label><textarea name="description"><?= e((string)$val('description')) ?></textarea></div>
      <div class="field"><label>What's included (one per line)</label><textarea name="specs" placeholder="One feature per line"><?= e($editingSpecs) ?></textarea></div>
      <div class="field">
        <label>Product image <small style="font-weight:400;color:var(--ink-faint);">(jpg, png or webp, up to 5MB — shown instead of the icon on the marketplace card and product page)</small></label>
        <?php if (!empty($editing['image_path'])): ?>
          <div class="flex items-center gap-12" style="margin-bottom:10px;">
            <img src="../<?= e($editing['image_path']) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:12px;">
            <label class="flex items-center gap-8" style="font-size:.85rem;"><input type="checkbox" name="remove_image"> Remove the current image</label>
          </div>
        <?php endif; ?>
        <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp">
      </div>
      <div class="field">
        <label>Downloadable file <small style="font-weight:400;color:var(--ink-faint);">(zip or pdf, up to 25MB — required for customers to actually receive something after buying)</small></label>
        <?php if (!empty($editing['download_path'])): ?>
          <?php // Deliberately not a direct link: assets/uploads/products/.htaccess denies .zip/.pdf so nobody can bypass checkout, which made the old "view current file" link a guaranteed 403. ?>
          <p style="font-size:.85rem;margin-bottom:8px;"><span class="badge success"><?= ico('check') ?> File attached</span> — <code style="font-size:.8rem;"><?= e(basename((string)$editing['download_path'])) ?></code><?php $fp = __DIR__ . '/../' . $editing['download_path']; ?><?= is_file($fp) ? ' (' . number_format(filesize($fp) / 1024, 0) . ' KB)' : ' <span class="badge danger">missing on disk</span>' ?></p>
          <label class="flex items-center gap-8" style="font-size:.85rem;margin-bottom:10px;"><input type="checkbox" name="remove_download"> Remove the current file</label>
        <?php endif; ?>
        <input type="file" name="download_file" accept=".zip,.pdf">
      </div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add product' ?></button>
        <?php if ($editing): ?><a href="products.php" class="btn btn-ghost">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" onclick="toggleAddForm('add')">Cancel</button><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Rating</th><th>Download</th><th></th></tr></thead><tbody>
      <?php foreach ($products as $p): ?>
      <tr>
        <td><?php if (!empty($p['image_path'])): ?><img src="../<?= e($p['image_path']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;"><?php else: ?><span style="color:var(--ink-faint);">—</span><?php endif; ?></td>
        <td style="font-weight:600;"><?= e($p['name']) ?></td>
        <td><?= e($p['category']) ?></td>
        <td>$<?= number_format((float)$p['price'], 0) ?><?= $p['pricing_type'] === 'monthly' ? '/mo' : '' ?></td>
        <td><span class="badge"><?= ico('star') ?> <?= number_format((float)$p['rating'], 1) ?></span></td>
        <td><?= !empty($p['download_path']) ? '<span class="badge success">Attached</span>' : '<span class="badge warning">None</span>' ?></td>
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
      <?php if (!$products): ?><tr><td colspan="7" style="color:var(--ink-faint);">No products yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'products.php') ?>
</body>
</html>
