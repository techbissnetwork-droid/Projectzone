<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

if (post()) {
    Csrf::check();
    $order = [];
    $rank  = [];
    foreach (array_keys(Content::SECTIONS) as $k) {
        $rank[$k] = (int)($_POST['pos'][$k] ?? 999);
    }
    asort($rank);
    foreach (array_keys($rank) as $k) {
        $order[] = $k;
    }
    $save = ['home_sections' => implode(',', $order)];
    foreach (array_keys(Content::SECTIONS) as $k) {
        $save['section_' . $k] = !empty($_POST['on'][$k]) ? '1' : '0';
    }
    /* Hero fields live here too — this is the page people reach for. */
    foreach (['hero_eyebrow','hero_title_a','hero_title_b','hero_title_c','hero_lede',
              'hero_cta_primary','hero_cta_secondary','quote'] as $k) {
        $save[$k] = trim((string)($_POST[$k] ?? ''));
    }
    Settings::setMany($save);
    log_activity('homepage.update');
    Flash::ok('Home page saved.');
    redirect('admin/homepage.php');
}

$saved = array_filter(array_map('trim', explode(',', Settings::get('home_sections', ''))));
$pos = [];
$i = 1;
foreach ($saved as $k) {
    if (isset(Content::SECTIONS[$k])) {
        $pos[$k] = $i++;
    }
}
foreach (array_keys(Content::SECTIONS) as $k) {
    if (!isset($pos[$k])) {
        $pos[$k] = $i++;
    }
}
asort($pos);

$counts = [];
foreach (['journey','process','pillar','arch','transform','stat'] as $k) {
    $counts[$k] = count(Content::items($k));
}
$LINKS = [
    'journey'   => ['content.php?kind=journey', 'journey'],
    'services'  => ['services.php', null],
    'arch'      => ['content.php?kind=arch', 'arch'],
    'process'   => ['content.php?kind=process', 'process'],
    'work'      => ['portfolio.php', null],
    'transform' => ['content.php?kind=transform', 'transform'],
    'pillars'   => ['content.php?kind=pillar', 'pillar'],
    'marketplace' => ['products.php', null],
    'quote'     => ['#quote', null],
];

$PAGE_TITLE = 'Home page';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="' . e(url()) . '" target="_blank" rel="noopener">Preview ↗</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<form method="post" class="form">
  <?= Csrf::field() ?>
  <div class="split">
    <section class="card">
      <div class="card__head"><h2>Sections</h2>
        <span class="badge muted">Set the number to reorder</span></div>
      <div class="tablewrap"><table class="data">
        <thead><tr><th style="width:76px">Order</th><th>Section</th><th>Content</th><th>Shown</th></tr></thead>
        <tbody>
        <?php foreach ($pos as $k => $n): [$href, $countKey] = $LINKS[$k]; ?>
          <tr>
            <td><input type="number" name="pos[<?= e($k) ?>]" value="<?= (int)$n ?>" min="1"
                       style="width:66px;padding:6px 8px;border-radius:6px;border:1px solid var(--line-2);background:rgba(255,255,255,.03);color:var(--tx)"></td>
            <td><span class="t-main"><?= e(Content::SECTIONS[$k]) ?></span></td>
            <td><?php if ($countKey !== null): ?>
                  <a class="linkish" href="<?= e($href) ?>"><?= (int)$counts[$countKey] ?> items — edit</a>
                <?php elseif ($href !== '#quote'): ?>
                  <a class="linkish" href="<?= e($href) ?>">Manage</a>
                <?php else: ?><span class="muted">Set below</span><?php endif; ?></td>
            <td><label class="field check" style="gap:6px">
              <input type="checkbox" name="on[<?= e($k) ?>]" value="1"<?= Settings::bool('section_' . $k, true) ? ' checked' : '' ?>><span></span></label></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>

    <div class="stack">
      <div class="fieldset">
        <p class="legend">Hero</p>
        <label class="field"><span>Eyebrow</span><input name="hero_eyebrow" value="<?= e(Settings::get('hero_eyebrow')) ?>"></label>
        <label class="field"><span>Headline line 1</span><input name="hero_title_a" value="<?= e(Settings::get('hero_title_a')) ?>"></label>
        <label class="field"><span>Headline line 2</span><input name="hero_title_b" value="<?= e(Settings::get('hero_title_b')) ?>"></label>
        <label class="field"><span>Headline line 3 <small>shown in the accent gradient</small></span>
          <input name="hero_title_c" value="<?= e(Settings::get('hero_title_c')) ?>"></label>
        <label class="field"><span>Paragraph</span><textarea name="hero_lede" rows="3"><?= e(Settings::get('hero_lede')) ?></textarea></label>
        <div class="row two">
          <label class="field"><span>Primary button</span><input name="hero_cta_primary" value="<?= e(Settings::get('hero_cta_primary')) ?>"></label>
          <label class="field"><span>Secondary button</span><input name="hero_cta_secondary" value="<?= e(Settings::get('hero_cta_secondary')) ?>"></label>
        </div>
        <p class="hint">The three figures under the hero are <a class="linkish" href="content.php?kind=stat">hero statistics</a>.</p>
      </div>
      <div class="fieldset" id="quote">
        <p class="legend">Statement quote</p>
        <label class="field"><span>Quote</span><textarea name="quote" rows="3"><?= e(Settings::get('quote')) ?></textarea></label>
      </div>
    </div>
  </div>
  <div class="formfoot"><button class="btn" type="submit">Save home page</button></div>
</form>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
