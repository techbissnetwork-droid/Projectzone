<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<!-- ══════════════ 04 · ARCHITECTURE ══════════════ -->
<section class="arch" id="solutions" data-theme="deep">
  <div class="arch__ambient" aria-hidden="true"></div>
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal"><span class="num">04</span> Systems, not pages</p>
        <h2 class="sec__title reveal">We build digital systems,<br>not just websites.</h2>
      </div>
      <p class="sec__lede reveal">Brand, front-end, application, backend, database, payments, hosting, security and analytics — designed as one architecture so nothing is bolted on later.</p>
    </header>

    <div class="arch__stage reveal">
      <div class="arch__diagram" id="archDiagram">
        <canvas id="archCanvas"></canvas>
        <div class="arch__nodes" id="archNodes">
          <div class="anode anode--source" data-arch="business" data-layer="0"><span class="anode__k">Layer 00</span><b>BUSINESS</b><em>Your goals, customers, operations</em></div>

          <div class="anode anode--core" data-arch="core" data-layer="1">
            <span class="anode__k">Layer 01</span><b>TECHBISS CORE</b>
            <em>Architecture · design system · API contracts</em>
            <span class="anode__pulse" aria-hidden="true"></span>
          </div>

          <div class="anode" data-arch="website"  data-layer="2"><b>WEBSITE</b><em>Marketing &amp; conversion</em></div>
          <div class="anode" data-arch="app"      data-layer="2"><b>APP</b><em>iOS · Android · internal</em></div>
          <div class="anode" data-arch="payments" data-layer="2"><b>PAYMENTS</b><em>Checkout &amp; settlement</em></div>
          <div class="anode" data-arch="email"    data-layer="2"><b>EMAIL</b><em>Domain communication</em></div>

          <div class="anode anode--infra" data-arch="hosting"  data-layer="3"><b>HOSTING</b><em>Edge + origin</em></div>
          <div class="anode anode--infra" data-arch="database" data-layer="3"><b>DATABASE</b><em>Single source of truth</em></div>
          <div class="anode anode--infra" data-arch="security" data-layer="3"><b>SECURITY</b><em>TLS · WAF · access</em></div>
          <div class="anode anode--infra" data-arch="cloud"    data-layer="3"><b>CLOUD</b><em>Scale &amp; backups</em></div>
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
          <p class="mono">Brand → Website → App → Backend → Database → Payments → Hosting → Security → Analytics</p>
        </div>
        <div class="apanel__bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
      </aside>
    </div>
  </div>
</section>
