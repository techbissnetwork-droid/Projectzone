<?php
require_once __DIR__ . '/../includes/db.php';

/**
 * A deploy that replaces some files but not others leaves this page calling
 * into an includes/ folder from an older release, which PHP answers with a
 * blank 500 and no clue. Say what actually happened instead. asset_version()
 * is the canary: it lives in includes/db.php and arrived with this release.
 */
if (!function_exists('asset_version')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Update incomplete</title></head>'
        . '<body style="font:16px/1.6 system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 24px;color:#2c2318;">'
        . '<h1 style="font-size:1.4rem;">This update was uploaded incomplete</h1>'
        . '<p>The <code>includes/</code> folder on this server is from an older release than the rest of the files, '
        . 'so the site is calling functions that do not exist yet.</p>'
        . '<p><b>Fix:</b> upload the release again and let it overwrite <em>every</em> folder — '
        . '<code>includes/</code>, <code>assets/</code>, <code>admin/</code>, <code>api/</code> and <code>install/</code>. '
        . 'Some file managers silently skip folders that already exist, which is how this happens.</p>'
        . '<p style="color:#786a5b;font-size:.9rem;">Nothing is broken in the database — this is only about which files are on disk.</p>'
        . '</body></html>');
}
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'products.php');
$pdo = db();

$CATEGORIES = ['Templates', 'AI Agents', 'Dashboards', 'Bundles', 'Themes'];
$PRICING_TYPES = ['fixed' => 'One-time fixed price', 'monthly' => 'Monthly subscription'];

// The icons the public marketplace can actually draw (assets/app.js ICONS).
// The field used to be free text, so a typo like "carts" silently rendered
// the generic fallback icon — a dropdown of real keys can't miss.
$ICON_CHOICES = [
    'box', 'cart', 'star', 'rocket', 'cloud', 'code', 'chart', 'shield', 'gear',
    'users', 'chat', 'compass', 'target', 'bolt', 'puzzle', 'globe', 'lock',
    'monitor', 'calendar', 'mail', 'phone', 'download', 'heart', 'flag', 'book',
    'search', 'refresh', 'spark', 'layers',
];

/**
 * A product id is only ever minted by slugify_id(), which emits lowercase
 * letters, digits and hyphens. Every id that reaches the filesystem
 * (glob/unlink/move_uploaded_file below) must match that shape.
 *
 * Without this, `existing_id` — supplied by the client on an "edit" —
 * flowed straight into those paths, so a staff member with only the
 * "products" permission could send `../../../config` and have the server
 * delete or overwrite files well outside the uploads folder. The id is
 * the file's basename, so a single character-class check closes it.
 */
function product_id_is_safe(string $id): bool
{
    return $id !== '' && preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $id) === 1;
}

/**
 * Handles the optional "downloadable file" upload for a product.
 * Returns: null (no change — keep whatever's already saved), '' (the
 * admin asked to remove the current file), or a new relative path to
 * save into products.download_path.
 */
function product_file_upload(string $productId, ?string &$error): ?string
{
    // Defence in depth: never let a traversing id reach the filesystem,
    // whatever the caller did or didn't validate.
    if (!product_id_is_safe($productId)) {
        $error = 'Invalid product reference.';
        return null;
    }
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
/**
 * Every gallery image for a product, primary (lowest sort_order) first.
 * @return list<array{id:int,path:string}>
 */
function product_gallery(PDO $pdo, string $productId): array
{
    $stmt = $pdo->prepare('SELECT id, path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$productId]);
    return array_map(fn($r) => ['id' => (int)$r['id'], 'path' => $r['path']], $stmt->fetchAll());
}

/**
 * Pre-flight validate every file in the multi-image field before any of
 * them is written, so a product save stays all-or-nothing on disk. An
 * empty field (nothing chosen) is fine — it just means "no new images".
 */
function product_validate_gallery(string $field, array $allowedExts, int $maxMb, ?string &$error): void
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return;
    }
    foreach ($_FILES[$field]['name'] as $i => $name) {
        $err = $_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $error = 'One of the images failed to upload — please try again.';
            return;
        }
        if (($_FILES[$field]['size'][$i] ?? 0) > $maxMb * 1024 * 1024) {
            $error = 'Each image must be under ' . $maxMb . 'MB.';
            return;
        }
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $error = 'Unsupported image type — allowed: ' . implode(', ', $allowedExts) . '.';
            return;
        }
        if (!@getimagesize($_FILES[$field]['tmp_name'][$i])) {
            $error = 'One of the files does not look like a valid image.';
            return;
        }
    }
}

/**
 * Move every newly uploaded gallery image into place and record it,
 * appended after whatever the product already has. Each file gets a random
 * suffix so uploads never collide and are addressed by their stored path,
 * not a guessable name. Returns how many were added.
 */
function product_gallery_add(PDO $pdo, string $productId, ?string &$error): int
{
    if (!product_id_is_safe($productId)) {
        $error = 'Invalid product reference.';
        return 0;
    }
    if (empty($_FILES['image_files']) || !is_array($_FILES['image_files']['name'] ?? null)) {
        return 0;
    }
    $uploadDir = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        $error = 'Could not create assets/uploads/products/ — check folder permissions.';
        return 0;
    }
    $ord = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?');
    $ord->execute([$productId]);
    $nextOrder = (int)$ord->fetchColumn();

    $ins = $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, ?)');
    $added = 0;
    foreach ($_FILES['image_files']['name'] as $i => $name) {
        $err = $_FILES['image_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK || $name === '') {
            continue;
        }
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }
        $filename = $productId . '-g' . bin2hex(random_bytes(5)) . '.' . $ext;
        if (move_uploaded_file($_FILES['image_files']['tmp_name'][$i], $uploadDir . '/' . $filename)) {
            $ins->execute([$productId, 'assets/uploads/products/' . $filename, $nextOrder++]);
            $added++;
        }
    }
    return $added;
}

/**
 * Delete the gallery images the admin unticked, by their row id — both the
 * row and the file. The path comes from the row (written by this app), and
 * is realpath-confined to the uploads folder before unlinking, so a
 * tampered id can only ever remove a product image, never anything else.
 */
function product_gallery_remove(PDO $pdo, string $productId, array $ids): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return;
    }
    $uploadRoot = realpath(__DIR__ . '/../assets/uploads/products');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sel = $pdo->prepare("SELECT path FROM product_images WHERE product_id = ? AND id IN ($in)");
    $sel->execute(array_merge([$productId], $ids));
    foreach ($sel->fetchAll(PDO::FETCH_COLUMN) as $relPath) {
        $abs = realpath(__DIR__ . '/../' . $relPath);
        if ($uploadRoot !== false && $abs !== false && str_starts_with($abs, $uploadRoot . DIRECTORY_SEPARATOR)) {
            @unlink($abs);
        }
    }
    $del = $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND id IN ($in)");
    $del->execute(array_merge([$productId], $ids));
}

/**
 * Keep products.image_path — the single column the marketplace card and
 * every pre-gallery code path still read — pointing at the current primary
 * (lowest sort_order) gallery image, or NULL when the product has none.
 */
function product_sync_primary_image(PDO $pdo, string $productId): void
{
    $stmt = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
    $stmt->execute([$productId]);
    $primary = $stmt->fetchColumn();
    $pdo->prepare('UPDATE products SET image_path = ? WHERE id = ?')
        ->execute([$primary !== false ? $primary : null, $productId]);
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
        $pricingType = array_key_exists($_POST['pricing_type'] ?? '', $PRICING_TYPES) ? $_POST['pricing_type'] : 'fixed';
        $rating = min(5, max(0, (float)($_POST['rating'] ?? 4.5)));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $specsRaw = (string)($_POST['specs'] ?? '');
        $specs = array_values(array_filter(array_map('trim', explode("\n", $specsRaw))));

        if ($name === '' || $tagline === '') {
            flash_input($_POST);
            flash('Name and tagline are required.', 'error');
        } elseif ($existingId !== '' && !product_id_is_safe($existingId)) {
            // An edit carries the id of the row being edited; a real one
            // always passes. Anything else is a tampered request.
            flash('That product could not be found.', 'error');
        } else {
            $id = $existingId !== '' ? $existingId : slugify_id($name, $pdo);
            $currentDownload = null;
            if ($existingId !== '') {
                $cur = $pdo->prepare('SELECT download_path FROM products WHERE id = ?');
                $cur->execute([$existingId]);
                $currentDownload = $cur->fetchColumn() ?: null;
            }
            // Validate every uploaded file before moving any of them, so a
            // save is all-or-nothing on disk — one bad image doesn't leave
            // the others half-applied.
            $uploadError = null;
            $imageError = null;
            product_validate_upload('download_file', ['zip', 'pdf'], 25, false, $uploadError);
            product_validate_gallery('image_files', ['jpg', 'jpeg', 'png', 'webp'], 5, $imageError);

            if ($uploadError || $imageError) {
                flash_input($_POST);
                flash($uploadError ?: $imageError, 'error');
            } else {
            $downloadPath = resolve_upload(product_file_upload($id, $uploadError), $currentDownload);

            if ($uploadError) {
                flash($uploadError, 'error');
            } else {
                // The product row is written FIRST: product_images has a
                // foreign key to products(id), so the row it references must
                // exist before any gallery image can be inserted. image_path
                // is filled in afterwards by product_sync_primary_image().
                if ($existingId !== '') {
                    $pdo->prepare('UPDATE products SET name=?, category=?, icon=?, price=?, pricing_type=?, rating=?, tagline=?, description=?, specs_json=?, download_path=? WHERE id=?')
                        ->execute([$name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $downloadPath, $existingId]);
                    $savedMessage = 'Product updated.';
                } else {
                    $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM products')->fetchColumn();
                    $pdo->prepare('INSERT INTO products (id, name, category, icon, price, pricing_type, rating, tagline, description, specs_json, download_path, image_path, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$id, $name, $category, $icon, $price, $pricingType, $rating, $tagline, $desc, json_encode($specs), $downloadPath, null, $maxSort + 1]);
                    $savedMessage = 'Product added — now live on the public marketplace.';
                }
                // Gallery: drop the images the admin unticked, add any newly
                // uploaded ones, then repoint products.image_path at the new
                // primary (the marketplace card still reads that column).
                product_gallery_remove($pdo, $id, (array)($_POST['remove_gallery'] ?? []));
                product_gallery_add($pdo, $id, $imageError);
                product_sync_primary_image($pdo, $id);
                flash($savedMessage);
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
        if (!product_id_is_safe($deleteId)) {
            // The id is glob()'d against the uploads folder below; a
            // traversing value would have unlinked files outside it.
            flash('That product could not be found.', 'error');
        } elseif ((int)$inUse->fetchColumn() > 0) {
            flash('That product has orders against it, so deleting it would erase those customers\' purchase history and downloads. Remove its file instead to take it off sale.', 'error');
        } else {
            // Unlink the gallery files by the paths stored against the
            // product before the row (and its cascading product_images
            // rows) go away.
            $uploadRoot = realpath(__DIR__ . '/../assets/uploads/products');
            $imgs = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ?');
            $imgs->execute([$deleteId]);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $relPath) {
                $abs = realpath(__DIR__ . '/../' . $relPath);
                if ($uploadRoot !== false && $abs !== false && str_starts_with($abs, $uploadRoot . DIRECTORY_SEPARATOR)) {
                    @unlink($abs);
                }
            }
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$deleteId]);
            // Leftover download/legacy-image uploads used to linger forever,
            // and the next product that slugified to the same id inherited
            // them. product_id_is_safe() above keeps this glob confined.
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
$editingGallery = $editing ? product_gallery($pdo, (string)$editing['id']) : [];
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
<link rel="stylesheet" href="../assets/style.css?v=<?= asset_version() ?>">
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
        <div class="field"><label>Icon <small style="font-weight:400;color:var(--ink-faint);">(shown when a product has no image)</small></label>
          <?php $curIcon = (string)$val('icon', 'box'); if (!in_array($curIcon, $ICON_CHOICES, true)) { array_unshift($ICON_CHOICES, $curIcon); } ?>
          <select name="icon"><?php foreach ($ICON_CHOICES as $ic): ?><option value="<?= e($ic) ?>" <?= $curIcon === $ic ? 'selected' : '' ?>><?= e($ic) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Price (USD)</label><input type="number" min="0" step="1" name="price" value="<?= e((string)$val('price', 99)) ?>"></div>
        <div class="field"><label>Pricing type</label><select name="pricing_type">
          <?php foreach ($PRICING_TYPES as $pt => $label): ?><option value="<?= e($pt) ?>" <?= (string)$val('pricing_type', 'fixed') === $pt ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Rating (0–5)</label><input type="number" min="0" max="5" step="0.1" name="rating" value="<?= e((string)$val('rating', 4.8)) ?>"></div>
      </div>
      <div class="field"><label>Tagline (one line, shown on the card)</label><input name="tagline" required value="<?= e((string)$val('tagline')) ?>"></div>
      <div class="field"><label>Full description</label><textarea name="description"><?= e((string)$val('description')) ?></textarea></div>
      <div class="field"><label>What's included (one per line)</label><textarea name="specs" placeholder="One feature per line"><?= e($editingSpecs) ?></textarea></div>
      <div class="field">
        <label>Product images <small style="font-weight:400;color:var(--ink-faint);">(jpg, png or webp, up to 5MB each — the first is used on the marketplace card; the rest form a gallery on the product page)</small></label>
        <?php if ($editingGallery): ?>
          <div class="flex items-center gap-12" style="flex-wrap:wrap;margin-bottom:12px;">
            <?php foreach ($editingGallery as $gi => $img): ?>
            <div style="text-align:center;">
              <img src="../<?= e($img['path']) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:12px;display:block;<?= $gi === 0 ? 'outline:2px solid var(--accent-1);outline-offset:2px;' : '' ?>">
              <?php if ($gi === 0): ?><span style="font-size:.68rem;color:var(--accent-1);font-weight:600;">Primary</span><?php endif; ?>
              <label class="flex items-center gap-8" style="font-size:.72rem;justify-content:center;margin-top:2px;"><input type="checkbox" name="remove_gallery[]" value="<?= (int)$img['id'] ?>"> Remove</label>
            </div>
            <?php endforeach; ?>
          </div>
          <p style="font-size:.78rem;color:var(--ink-faint);margin:-4px 0 8px;">Tick "Remove" under any image to delete it. New images you add below are appended after these.</p>
        <?php endif; ?>
        <input type="file" name="image_files[]" accept=".jpg,.jpeg,.png,.webp" multiple>
      </div>
      <div class="field">
        <label>Downloadable file <small style="font-weight:400;color:var(--ink-faint);">(zip or pdf, up to 25MB — the file a customer downloads once you deliver their order)</small></label>
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
