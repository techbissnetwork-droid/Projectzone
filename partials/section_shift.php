<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** Offline → online. Stages come from content_items (kind = journey). */
$journey = Content::items('journey');
if (!$journey) { return; }
$secNum = $secNum ?? 2;
?>
<!-- ══════════════ 02 · OFFLINE → ONLINE ══════════════ -->
<section class="flowsec" id="shift" data-theme="deep">
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('journey_eyebrow')) ?></p>
        <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('journey_title')), false) ?></h2>
      </div>
      <p class="sec__lede reveal"><?= e(Settings::get('journey_lede')) ?></p>
    </header>

    <?php $cols = max(2, min(6, count($journey))); /* One row, however many stages there are. */ ?>
    <ol class="flow" style="--n:<?= $cols ?>">
      <?php foreach ($journey as $ji => $j): ?>
        <li class="flow__step reveal">
          <div class="flow__mark" aria-hidden="true"><span class="flow__num mono"><?= sprintf('%02d', $ji + 1) ?></span></div>
          <?php if ($j['meta1']): ?><span class="flow__tag mono"><?= e($j['meta1']) ?></span><?php endif; ?>
          <h3 class="flow__name"><?= e($j['title'] ?: $j['label']) ?></h3>
          <?php if ($j['body']): ?><p class="flow__text"><?= e($j['body']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
