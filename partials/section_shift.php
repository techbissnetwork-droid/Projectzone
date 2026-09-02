<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<!-- ══════════════ 02 · OFFLINE → ONLINE ══════════════ -->
<section class="shift" id="shift" data-theme="deep">
  <div class="shift__track" id="shiftTrack">
    <div class="shift__stage">
      <div class="shift__aura" aria-hidden="true"></div>

      <div class="shell shift__head">
        <p class="eyebrow"><span class="num">02</span> The transformation</p>
        <div class="shift__words" aria-hidden="true">
          <div class="shift__wordsTrack" id="shiftWords">
            <span>OFFLINE</span><span>DIGITAL</span><span>ONLINE</span><span>GROWING</span>
          </div>
        </div>
      </div>

      <div class="shift__body shell">
        <div class="shift__copy">
          <h2 class="shift__title" id="shiftTitle">A business that only exists in one place</h2>
          <p class="shift__text" id="shiftText">Paper records, phone orders, manual follow-ups and a customer base limited by walking distance. Every process depends on someone remembering it.</p>
          <div class="shift__meta">
            <span class="chip chip--ghost" id="shiftStageLabel">Stage 01 — Offline</span>
          </div>
        </div>

        <div class="shift__scene" id="shiftScene">
          <!-- Panel 1 : OFFLINE -->
          <div class="panel panel--offline" data-panel="0">
            <div class="paper paper--a"><span class="paper__line"></span><span class="paper__line"></span><span class="paper__line short"></span><em>Order book</em></div>
            <div class="paper paper--b"><span class="paper__line"></span><span class="paper__line short"></span><em>Cash ledger</em></div>
            <div class="paper paper--c"><em>“Call to order”</em><span class="paper__line short"></span></div>
            <div class="store">
              <div class="store__awning"></div>
              <div class="store__body"><span></span><span></span></div>
              <div class="store__sign">OPEN 9–6</div>
            </div>
            <div class="reach reach--small"><i></i><span>Local reach only</span></div>
          </div>

          <!-- Panel 2 : DIGITAL -->
          <div class="panel panel--digital" data-panel="1">
            <div class="scanline"></div>
            <div class="convert">
              <div class="convert__row"><span>Order book</span><i class="arrow">→</i><b>Orders API</b></div>
              <div class="convert__row"><span>Cash ledger</span><i class="arrow">→</i><b>Payments</b></div>
              <div class="convert__row"><span>Phone calls</span><i class="arrow">→</i><b>Bookings</b></div>
              <div class="convert__row"><span>Notebook</span><i class="arrow">→</i><b>Database</b></div>
              <div class="convert__row"><span>Shopfront</span><i class="arrow">→</i><b>Website</b></div>
            </div>
            <div class="convert__status"><i class="dot dot--live"></i>Migrating business logic</div>
          </div>

          <!-- Panel 3 : ONLINE -->
          <div class="panel panel--online" data-panel="2">
            <div class="browser">
              <div class="browser__bar"><i></i><i></i><i></i><span class="browser__url">yourbusiness.com</span><span class="browser__lock">SSL</span></div>
              <div class="browser__view">
                <span class="sk sk--h"></span><span class="sk sk--t"></span>
                <div class="browser__cards"><i></i><i></i><i></i></div>
              </div>
            </div>
            <div class="phone">
              <div class="phone__notch"></div>
              <div class="phone__view"><span class="sk sk--h2"></span><i class="sk sk--b"></i><i class="sk sk--b"></i><span class="phone__cta">Order now</span></div>
            </div>
            <div class="tile tile--pay"><span>Payment received</span><b>Rs 4,850</b></div>
            <div class="tile tile--mail"><span>hello@yourbusiness.com</span><b>Business email live</b></div>
            <div class="tile tile--cloud"><span>Cloud infrastructure</span><b>3 regions</b></div>
          </div>

          <!-- Panel 4 : GROWING -->
          <div class="panel panel--growth" data-panel="3">
            <div class="chart">
              <svg viewBox="0 0 320 150" preserveAspectRatio="none" aria-hidden="true">
                <defs><linearGradient id="gFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#7DA2FF" stop-opacity=".35"/><stop offset="100%" stop-color="#7DA2FF" stop-opacity="0"/>
                </linearGradient></defs>
                <path class="chart__area" d="M0,140 L0,116 C40,110 58,96 84,86 C116,74 132,86 158,66 C186,45 206,52 236,34 C266,16 292,20 320,8 L320,150 L0,150 Z" fill="url(#gFill)"/>
                <path class="chart__line" d="M0,116 C40,110 58,96 84,86 C116,74 132,86 158,66 C186,45 206,52 236,34 C266,16 292,20 320,8" fill="none" stroke="#9BB8FF" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <div class="chart__grid" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            </div>
            <div class="kpis">
              <div class="kpi"><b data-count="284">284</b><span>Online orders / mo</span></div>
              <div class="kpi"><b data-count="46">46</b><span>Countries reached</span></div>
              <div class="kpi"><b data-count="0">0</b><span>Manual entries</span></div>
            </div>
            <div class="reach reach--global"><i></i><span>Global reach</span></div>
          </div>
        </div>
      </div>

      <div class="shell shift__rail">
        <ol class="rail" id="shiftRail">
          <li data-step="0"><b>01</b> Offline</li>
          <li data-step="1"><b>02</b> Digital</li>
          <li data-step="2"><b>03</b> Online</li>
          <li data-step="3"><b>04</b> Growing</li>
        </ol>
        <div class="rail__bar" aria-hidden="true"><i id="shiftBar"></i></div>
      </div>
    </div>
  </div>
</section>
