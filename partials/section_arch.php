<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** System architecture. Nodes come from content_items (kind = arch). */
$archNodes = Content::items('arch');
if (!$archNodes) { return; }
$secNum = $secNum ?? 4;
?>
<!-- ══════════════ 04 · ARCHITECTURE ══════════════ -->
<section class="arch" id="solutions" data-theme="deep">
  <div class="arch__ambient" aria-hidden="true"></div>
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('arch_eyebrow')) ?></p>
        <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('arch_title')), false) ?></h2>
      </div>
      <p class="sec__lede reveal"><?= e(Settings::get('arch_lede')) ?></p>
    </header>

    <div class="arch__stage reveal">
      <div class="arch__diagram" id="archDiagram">
        <canvas id="archCanvas"></canvas>
        <div class="arch__nodes" id="archNodes">
          <?php foreach ($archNodes as $n):
            $layer = (int)($n['meta1'] ?? 2);
            $cls = $layer === 0 ? ' anode--source' : ($layer === 1 ? ' anode--core' : ($layer === 3 ? ' anode--infra' : ''));
          ?>
            <div class="anode<?= $cls ?>" data-arch="<?= e(slugify((string)$n['label'])) ?>" data-layer="<?= $layer ?>">
              <?php if ($layer <= 1): ?><span class="anode__k">Layer <?= sprintf('%02d', $layer) ?></span><?php endif; ?>
              <b><?= e($n['label']) ?></b>
              <?php if ($n['title']): ?><em><?= e($n['title']) ?></em><?php endif; ?>
              <?php if ($layer === 1): ?><span class="anode__pulse" aria-hidden="true"></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        </div>
      </div>

      <aside class="arch__panel">
        <div class="apanel__head"><i class="dot dot--live"></i><span>system status</span><b class="mono">operational</b></div>
        <ul class="apanel__list">
          <li><span>Requests / min</span><b class="mono" data-tick="req">1,284</b></li>
          <li><span>P95 latency</span><b class="mono" data-tick="lat">142 ms</b></li>
          <li><span>Error rate</span><b class="mono" data-tick="err">0.01%</b></li>
          <li><span>Deployments</span><b class="mono">continuous</b></li>
        </ul>
        <div class="apanel__flow">
          <span class="apanel__k">Data path</span>
          <p class="mono"><?= e(Settings::get('arch_flow')) ?></p>
        </div>
        <div class="apanel__bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
      </aside>
    </div>
  </div>
</section>
