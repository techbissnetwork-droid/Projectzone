<?php
/** Service modules. Expects $services (array of rows). */
declare(strict_types=1);
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
$GLYPHS = [
  'websites'   => '<rect x="1" y="1" width="38" height="30" rx="4" stroke="currentColor" stroke-width="1.2"/><path d="M1 9h38" stroke="currentColor" stroke-width="1.2"/><circle cx="6" cy="5" r="1"/><circle cx="10" cy="5" r="1"/><path class="dash" d="M7 16h14M7 21h20M7 26h9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>',
  'apps'       => '<rect x="12" y="1" width="16" height="30" rx="3.5" stroke="currentColor" stroke-width="1.2"/><path d="M18 4.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path class="dash" d="M16 12h8M16 17h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><circle cx="20" cy="26" r="1.6" stroke="currentColor" stroke-width="1.2"/>',
  'domains'    => '<circle cx="20" cy="16" r="12" stroke="currentColor" stroke-width="1.2"/><ellipse cx="20" cy="16" rx="5" ry="12" stroke="currentColor" stroke-width="1.2"/><path d="M8.5 12h23M8.5 20h23" stroke="currentColor" stroke-width="1.2"/>',
  'hosting'    => '<rect x="7" y="4" width="26" height="8" rx="2" stroke="currentColor" stroke-width="1.2"/><rect x="7" y="15" width="26" height="8" rx="2" stroke="currentColor" stroke-width="1.2"/><circle class="blink" cx="12" cy="8" r="1.4"/><circle class="blink b2" cx="12" cy="19" r="1.4"/><path d="M22 8h7M22 19h7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity=".5"/>',
  'security'   => '<path d="M20 2 32 7v9c0 7-5 12-12 14C13 28 8 23 8 16V7z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path class="tick" d="M14.5 15.5 18.5 19.5 26 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
  'email'      => '<rect x="5" y="6" width="30" height="20" rx="3" stroke="currentColor" stroke-width="1.2"/><path class="dash" d="m6.5 9 13.5 10L33.5 9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>',
  'ecommerce'  => '<path d="M6 6h4l3.4 14.2a2 2 0 0 0 2 1.6h11.8a2 2 0 0 0 2-1.5L32 11H12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="17" cy="27" r="1.8" stroke="currentColor" stroke-width="1.2"/><circle cx="27" cy="27" r="1.8" stroke="currentColor" stroke-width="1.2"/>',
  'automation' => '<circle cx="11" cy="9" r="3.5" stroke="currentColor" stroke-width="1.2"/><circle cx="29" cy="9" r="3.5" stroke="currentColor" stroke-width="1.2"/><circle cx="20" cy="24" r="3.5" stroke="currentColor" stroke-width="1.2"/><path class="dash" d="M14.5 9h11M12.8 12 18 21M27.2 12 22 21" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>',
  'payments'   => '<rect x="4" y="7" width="32" height="19" rx="3" stroke="currentColor" stroke-width="1.2"/><path d="M4 14h32" stroke="currentColor" stroke-width="1.2"/><path class="dash" d="M9 20h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
];
/* The bento composition repeats every nine cards: two wide, one tall, the rest even. */
$WIDE = [0, 6];
$TALL = [3];
foreach ($services as $i => $s):
    $pos = $i % 9;
    $cls = in_array($pos, $WIDE, true) ? ' svc--wide' : (in_array($pos, $TALL, true) ? ' svc--tall' : '');
    $glyph = $GLYPHS[$s['icon']] ?? $GLYPHS['websites'];
    $features = lines($s['features']);
    $tech = csv_list($s['tech']);
?>
  <article class="svc<?= $cls ?> tilt" data-svc="<?= e($s['slug']) ?>" id="<?= e($s['slug']) ?>">
    <div class="svc__head">
      <span class="svc__index"><?= sprintf('%02d', $i + 1) ?></span>
      <span class="svc__glyph"><svg viewBox="0 0 40 32" fill="none" aria-hidden="true"><?= $glyph ?></svg></span>
      <h3 class="svc__name"><?= e($s['title']) ?></h3>
      <p class="svc__desc"><?= e($s['summary']) ?></p>
    </div>
    <div class="svc__detail">
      <?php if ($features): ?>
        <ul class="ticks"><?php foreach (array_slice($features, 0, 4) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($tech): ?>
        <div class="tags"><?php foreach (array_slice($tech, 0, 4) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
