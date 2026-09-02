<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT * FROM products WHERE id = :id', ['id' => $id]);
    if ($row) {
        $sold = (int)Database::value('SELECT COUNT(*) FROM orders WHERE product_id = :id', ['id' => $id], 0);
        if ($sold > 0) {
            Database::update('products', ['is_active' => 0], $id);
            Flash::ok('“' . $row['title'] . '” has ' . $sold . ' order(s), so it was unlisted instead of deleted — the order history stays intact.');
        } else {
            Upload::remove($row['cover_image']);
            Database::delete('products', $id);
            log_activity('product.delete', 'product', $id, $row['title']);
            Flash::ok('“' . $row['title'] . '” was deleted.');
        }
    }
    redirect('admin/products.php');
}

if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, title, is_active FROM products WHERE id = :id', ['id' => $id]);
    if ($row) {
        Database::update('products', ['is_active' => $row['is_active'] ? 0 : 1], $id);
        Flash::ok('“' . $row['title'] . '” is now ' . ($row['is_active'] ? 'unlisted.' : 'live in the marketplace.'));
    }
    redirect('admin/products.php');
}

if ($action === 'new' || $action === 'edit') {
    $item = $action === 'edit'
        ? Database::one('SELECT * FROM products WHERE id = :id', ['id' => $id])
        : ['is_active' => 1, 'is_featured' => 0, 'price' => '', 'sort_order' => 0];
    if ($action === 'edit' && !$item) {
        http_response_code(404);
        exit('Product not found.');
    }
    $errors = [];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        $title = trim((string)($_POST['title'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $sale  = $d('sale_price') !== null ? (float)$_POST['sale_price'] : null;

        if ($title === '')            $errors[] = 'Enter a product title.';
        if ($price <= 0)              $errors[] = 'Enter a price greater than zero.';
        if ($sale !== null && $sale >= $price) $errors[] = 'The sale price must be lower than the regular price.';

        $cover = $item['cover_image'] ?? null;
        [$path, $upErr] = Upload::image('cover_image', 'products');
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
                'title'       => $title,
                'slug'        => unique_slug('products', $d('slug') ?? $title, $action === 'edit' ? $id : null),
                'category'    => $d('category'),
                'summary'     => $d('summary'),
                'body'        => $d('body'),
                'tech'        => $d('tech'),
                'includes'    => $d('includes'),
                'price'       => $price,
                'sale_price'  => $sale,
                'demo_url'    => $d('demo_url'),
                'cover_image' => $cover,
                'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
                'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
                'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($action === 'edit') {
                Database::update('products', $data, $id);
                log_activity('product.update', 'product', $id, $title);
                Flash::ok('Product saved.');
            } else {
                $data['created_at'] = now();
                $id = Database::insert('products', $data);
                log_activity('product.create', 'product', $id, $title);
                Flash::ok('Product created.');
            }
            redirect('admin/products.php');
        }
        $item = array_merge($item, $_POST, ['cover_image' => $cover]);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit product' : 'New product';
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
            <p class="legend">Listing</p>
            <label class="field"><span>Title</span>
              <input name="title" data-slug-source value="<?= e($item['title'] ?? '') ?>" required maxlength="180"></label>
            <div class="row two">
              <label class="field"><span>URL slug <small>auto</small></span>
                <input name="slug" data-slug-target value="<?= e($item['slug'] ?? '') ?>" maxlength="180"></label>
              <label class="field"><span>Category</span>
                <input name="category" placeholder="E-Commerce" value="<?= e($item['category'] ?? '') ?>"></label>
            </div>
            <label class="field"><span>Summary <small>shown on the marketplace grid</small></span>
              <textarea name="summary" rows="2" maxlength="500"><?= e($item['summary'] ?? '') ?></textarea></label>
            <label class="field"><span>Full description</span>
              <textarea name="body" rows="7"><?= e($item['body'] ?? '') ?></textarea></label>
          </div>
          <div class="fieldset">
            <p class="legend">What the buyer gets</p>
            <label class="field"><span>Included <small>one per line</small></span>
              <textarea name="includes" rows="5" placeholder="Full source code
Database schema &amp; seed data
Admin panel
Installation guide"><?= e($item['includes'] ?? '') ?></textarea></label>
            <label class="field"><span>Built with <small>comma separated</small></span>
              <input name="tech" placeholder="PHP, MySQL, Bootstrap" value="<?= e($item['tech'] ?? '') ?>"></label>
          </div>
        </div>

        <div class="stack">
          <div class="fieldset">
            <p class="legend">Price</p>
            <label class="field"><span>Price (<?= e(Settings::get('currency', 'NPR')) ?>)</span>
              <input name="price" type="number" step="0.01" min="0" value="<?= e((string)($item['price'] ?? '')) ?>" required></label>
            <label class="field"><span>Sale price <small>optional</small></span>
              <input name="sale_price" type="number" step="0.01" min="0" value="<?= e((string)($item['sale_price'] ?? '')) ?>"></label>
          </div>
          <div class="fieldset">
            <p class="legend">Visibility</p>
            <label class="field check"><input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>>
              <span>Listed in the marketplace</span></label>
            <label class="field check"><input type="checkbox" name="is_featured" value="1"<?= !empty($item['is_featured']) ? ' checked' : '' ?>>
              <span>Feature on the home page</span></label>
            <label class="field"><span>Sort order</span>
              <input name="sort_order" type="number" value="<?= e((string)($item['sort_order'] ?? 0)) ?>"></label>
          </div>
          <div class="fieldset">
            <p class="legend">Media</p>
            <?php if (!empty($item['cover_image'])): ?>
              <img class="thumb" src="<?= e(url($item['cover_image'])) ?>" alt="">
              <label class="field check"><input type="checkbox" name="remove_cover" value="1"><span>Remove this image</span></label>
            <?php endif; ?>
            <img class="thumb" id="pcPreview" alt="" hidden>
            <label class="field"><span>Cover image</span>
              <input type="file" name="cover_image" accept="image/*" data-preview="#pcPreview"></label>
            <label class="field"><span>Demo URL</span>
              <input name="demo_url" type="url" placeholder="https://" value="<?= e($item['demo_url'] ?? '') ?>"></label>
          </div>
        </div>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save product' : 'Create product' ?></button>
        <a class="btn ghost" href="products.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$items = Database::all('SELECT * FROM products ORDER BY sort_order, id DESC');
$PAGE_TITLE = 'Marketplace';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="products.php?action=new">New product</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<section class="card">
  <?php if (!$items): ?>
    <div class="empty"><b>No products yet</b><p>List a premade project so visitors can buy it.</p>
      <a class="btn sm" href="products.php?action=new">New product</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Product</th><th>Category</th><th class="right">Price</th><th class="right">Sold</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><span class="t-main"><?= e($it['title']) ?><?= $it['is_featured'] ? ' <span class="badge info">Featured</span>' : '' ?></span>
              <span class="t-sub"><?= e(excerpt($it['summary'], 72)) ?></span></td>
          <td><?= e($it['category'] ?: '—') ?></td>
          <td class="num"><?php if ($it['sale_price'] !== null): ?>
              <span class="muted" style="text-decoration:line-through"><?= e(money($it['price'])) ?></span><br><?= e(money($it['sale_price'])) ?>
            <?php else: ?><?= e(money($it['price'])) ?><?php endif; ?></td>
          <td class="num"><?= (int)$it['sales_count'] ?></td>
          <td>
            <form method="post" action="products.php?action=toggle&id=<?= (int)$it['id'] ?>">
              <?= Csrf::field() ?>
              <button class="badge <?= $it['is_active'] ? 'ok' : 'muted' ?>" type="submit" style="cursor:pointer" title="Click to switch">
                <?= $it['is_active'] ? 'Listed' : 'Unlisted' ?></button>
            </form>
          </td>
          <td><div class="acts">
            <a class="btn ghost sm" href="<?= e(url('product.php?slug=' . urlencode($it['slug']))) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn ghost sm" href="products.php?action=edit&id=<?= (int)$it['id'] ?>">Edit</a>
            <form method="post" action="products.php?action=delete&id=<?= (int)$it['id'] ?>"
                  data-confirm="Delete “<?= e($it['title']) ?>”? Products with orders are unlisted instead.">
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
