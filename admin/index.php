<?php
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$total  = (int) scalar('SELECT COUNT(*) FROM enquiries');
$new    = (int) scalar("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
$week   = (int) scalar('SELECT COUNT(*) FROM enquiries WHERE created_at > :d', ['d' => date('Y-m-d H:i:s', strtotime('-7 days'))]);
$unsent = (int) scalar('SELECT COUNT(*) FROM enquiries WHERE mailed = 0');
$latest = all('SELECT * FROM enquiries ORDER BY created_at DESC, id DESC LIMIT 8');

admin_header('Dashboard');
?>
<h1>Dashboard</h1>

<div class="acards">
  <div class="acard-stat"><b><?= $new ?></b><span>New enquiries</span></div>
  <div class="acard-stat"><b><?= $week ?></b><span>In the last 7 days</span></div>
  <div class="acard-stat"><b><?= $total ?></b><span>Enquiries in total</span></div>
  <div class="acard-stat"><b><?= $unsent ?></b><span>Not emailed out</span></div>
</div>

<?php if ($unsent > 0): ?>
  <div class="aflash aflash--warn">
    <?= $unsent ?> enquir<?= $unsent === 1 ? 'y is' : 'ies are' ?> saved here but could not be emailed.
    Check the “to” and “from” addresses in <code>app/config.php</code> — the from address must be on your own domain.
    Nothing is lost: every enquiry is stored and readable below.
  </div>
<?php endif; ?>

<h2>Latest enquiries</h2>
<?php if (!$latest): ?>
  <p class="amuted">Nothing yet. Enquiries sent through the contact form will appear here.</p>
<?php else: ?>
  <table class="atable">
    <thead><tr><th>Received</th><th>Name</th><th>Needs</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($latest as $en): ?>
        <tr>
          <td class="anowrap"><?= e(date('j M, H:i', strtotime($en['created_at']))) ?></td>
          <td><?= e($en['name']) ?><br><span class="amuted"><?= e($en['email']) ?></span></td>
          <td><?= e($en['service'] ?: '—') ?></td>
          <td><span class="atag atag--<?= e($en['status']) ?>"><?= e($en['status']) ?></span></td>
          <td><a class="abtn abtn--sm" href="<?= e(base_url('admin/enquiry.php?id=' . (int) $en['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><a class="abtn" href="<?= e(base_url('admin/enquiries.php')) ?>">All enquiries →</a></p>
<?php endif; ?>

<h2>Edit the website</h2>
<div class="agrid">
  <?php foreach (resources() as $key => $res):
      $count = (int) scalar('SELECT COUNT(*) FROM ' . safe_table($key)); ?>
    <a class="atile" href="<?= e(base_url('admin/resource.php?r=' . $key)) ?>">
      <b><?= e($res['label']) ?></b>
      <span><?= $count ?> item<?= $count === 1 ? '' : 's' ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php admin_footer(); ?>
