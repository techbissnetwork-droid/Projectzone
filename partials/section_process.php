<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<!-- ══════════════ 05 · PROCESS ══════════════ -->
<section class="proc" id="process">
  <div class="proc__track" id="procTrack">
    <div class="proc__stage">
      <div class="proc__glow" aria-hidden="true"></div>
      <div class="shell proc__inner">

        <header class="proc__head">
          <p class="eyebrow"><span class="num">05</span> The TECHBISS experience</p>
          <h2 class="proc__title">How we work</h2>
          <div class="proc__meter" aria-hidden="true"><i id="procBar"></i></div>
          <span class="proc__count mono"><b id="procNow">01</b> / 05</span>
        </header>

        <ol class="steps" id="steps">
          <li class="step is-active" data-step="0">
            <div class="step__row">
              <span class="step__num mono">01</span>
              <h3 class="step__name">Discover</h3>
              <span class="step__tag mono">week 1</span>
            </div>
            <div class="step__open"><div class="step__openIn">
              <p>We understand your business, your customers and your goals — how orders actually arrive, where time is lost, what growth is blocked on.</p>
              <ul class="ticks"><li>Business &amp; operations audit</li><li>Customer journey mapping</li><li>Technical requirements</li><li>Scope, budget and timeline</li></ul>
            </div></div>
          </li>
          <li class="step" data-step="1">
            <div class="step__row">
              <span class="step__num mono">02</span>
              <h3 class="step__name">Design</h3>
              <span class="step__tag mono">week 2–3</span>
            </div>
            <div class="step__open"><div class="step__openIn">
              <p>We create the digital experience and the architecture behind it — interface, brand system and the data model they sit on.</p>
              <ul class="ticks"><li>Design system &amp; UI</li><li>Interactive prototype</li><li>System architecture</li><li>Content &amp; copy structure</li></ul>
            </div></div>
          </li>
          <li class="step" data-step="2">
            <div class="step__row">
              <span class="step__num mono">03</span>
              <h3 class="step__name">Build</h3>
              <span class="step__tag mono">week 3–8</span>
            </div>
            <div class="step__open"><div class="step__openIn">
              <p>We develop the website, the application and the infrastructure together, shipping to a staging environment you can watch grow.</p>
              <ul class="ticks"><li>Front-end &amp; backend build</li><li>Integrations &amp; automation</li><li>QA across real devices</li><li>Weekly staging reviews</li></ul>
            </div></div>
          </li>
          <li class="step" data-step="3">
            <div class="step__row">
              <span class="step__num mono">04</span>
              <h3 class="step__name">Launch</h3>
              <span class="step__tag mono">launch week</span>
            </div>
            <div class="step__open"><div class="step__openIn">
              <p>We configure domain, hosting, SSL, email and deployment — then move you across without downtime and hand over the keys.</p>
              <ul class="ticks"><li>DNS, SSL &amp; mail records</li><li>Zero-downtime cutover</li><li>Analytics &amp; monitoring</li><li>Team training &amp; handover</li></ul>
            </div></div>
          </li>
          <li class="step" data-step="4">
            <div class="step__row">
              <span class="step__num mono">05</span>
              <h3 class="step__name">Grow</h3>
              <span class="step__tag mono">ongoing</span>
            </div>
            <div class="step__open"><div class="step__openIn">
              <p>We maintain, optimise and keep improving the digital side of your business — because launch day is the beginning of the work, not the end.</p>
              <ul class="ticks"><li>Maintenance &amp; support</li><li>Performance &amp; SEO tuning</li><li>Conversion improvements</li><li>New features each quarter</li></ul>
            </div></div>
          </li>
        </ol>

        <div class="proc__visual" id="procVisual" aria-hidden="true">
          <div class="pv" data-pv="0"><div class="pv__radar"><i></i><i></i><i></i><span></span></div><p class="mono">mapping business logic</p></div>
          <div class="pv" data-pv="1"><div class="pv__frames"><i></i><i></i><i></i><i></i></div><p class="mono">composing the system</p></div>
          <div class="pv" data-pv="2"><div class="pv__code"><span></span><span></span><span></span><span></span><span></span><span></span></div><p class="mono">building &amp; testing</p></div>
          <div class="pv" data-pv="3"><div class="pv__launch"><i></i><i></i><i></i><b>DNS · SSL · MX</b></div><p class="mono">cutting over</p></div>
          <div class="pv" data-pv="4"><div class="pv__growth"><i></i><i></i><i></i><i></i><i></i><i></i></div><p class="mono">compounding results</p></div>
        </div>

      </div>
    </div>
  </div>
</section>
