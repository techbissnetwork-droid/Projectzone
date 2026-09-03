<?php
/** @var App\Core\View $view @var array $products @var array $statusCounts */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <?php
    $published = 0; $revenue = 0.0; $orders = 0; $rating = 0.0;
    foreach ($products as $p) {
      if ($p['status'] === 'published') { $published++; }
      $revenue += (float) $p['revenue'];
      $orders += (int) $p['order_count'];
      $rating += (float) $p['rating'];
    }
    $avgRating = $products ? $rating / count($products) : 0;
  ?>
  <?php foreach ([
    ['tag', 'Published', (string) $published],
    ['cart', 'Catalogue revenue', money($revenue)],
    ['download', 'Licence sales', number_format($orders)],
    ['star', 'Average rating', number_format($avgRating, 2)],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel mt-5">
  <div class="panel__head">
    <h3>Catalogue</h3>
    <a class="btn btn--sm btn--quiet" href="<?= e(url('/marketplace')) ?>">View storefront</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Revenue</th><th>Rating</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <tr>
            <td data-label="Product">
              <strong><a href="<?= e(url('/marketplace/' . $product['slug'])) ?>"><?= e($product['name']) ?></a></strong><br>
              <span class="tiny dim">v<?= e($product['version']) ?> · updated <?= e(human_date($product['updated_at'])) ?><?= $product['featured'] ? ' · featured' : '' ?></span>
            </td>
            <td data-label="Category"><span class="badge badge--neutral"><?= e(App\Models\Product::CATEGORIES[$product['category']] ?? $product['category']) ?></span></td>
            <td data-label="Price" class="num"><?= e(money((float) $product['price'])) ?></td>
            <td data-label="Revenue" class="num"><?= e(money((float) $product['revenue'])) ?></td>
            <td data-label="Rating"><?= number_format((float) $product['rating'], 1) ?> <span class="tiny dim">(<?= (int) $product['reviews_count'] ?>)</span></td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $product['status']]); ?></td>
            <td data-label="" class="num">
              <form method="post" action="<?= e(url('/admin/products/status')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="status" value="<?= $product['status'] === 'published' ? 'retired' : 'published' ?>">
                <button class="btn btn--sm btn--ghost" type="submit">
                  <?= $product['status'] === 'published' ? 'Retire' : 'Publish' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
