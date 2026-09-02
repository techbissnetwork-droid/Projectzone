<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** Business transformations from content_items (kind = transform). */
$trans = Content::items('transform');
if (!$trans) { return; }
$secNum = $secNum ?? 7;
?>
<section class="trans" id="transform">
  <div class="shell">
    <header class="sec__head sec__head--center">
      <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('transform_eyebrow')) ?></p>
      <h2 class="sec__title sec__title--xl reveal">
        <?php $tl = explode("\n", Settings::get('transform_title')); ?>
        <?= e(trim($tl[0])) ?><?php if (isset($tl[1])): ?><br><span class="grad"><?= e(trim($tl[1])) ?></span><?php endif; ?>
      </h2>
    </header>

    <div class="trans__panel">
      <ul class="trans__list" id="transList" role="tablist" aria-label="Business types">
        <?php foreach ($trans as $i => $t): ?>
          <li><button role="tab" id="tb-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                      aria-controls="tp-<?= $i ?>" class="<?= $i === 0 ? 'is-active' : '' ?>" data-t="<?= $i ?>">
            <span class="mono"><?= sprintf('%02d', $i + 1) ?></span> <?= e($t['label']) ?> <i aria-hidden="true">→</i></button></li>
        <?php endforeach; ?>
      </ul>

      <div class="trans__stage" id="transStage">
        <?php foreach ($trans as $i => $t): ?>
          <div class="tpanel<?= $i === 0 ? ' is-active' : '' ?>" id="tp-<?= $i ?>" role="tabpanel"
               aria-labelledby="tb-<?= $i ?>" tabindex="0"<?= $i === 0 ? '' : ' hidden' ?>>
            <p class="tpanel__k mono"><?= e($t['label']) ?></p>
            <h3 class="tpanel__title"><?= e($t['title']) ?></h3>
            <?php if ($t['body']): ?><p class="tpanel__desc"><?= e($t['body']) ?></p><?php endif; ?>
            <?php $chips = lines($t['extra']); if ($chips): ?>
              <div class="tags"><?php foreach ($chips as $c): ?><span><?= e($c) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <span class="trans__beam" aria-hidden="true"></span>
      </div>
    </div>
  </div>
</section>
