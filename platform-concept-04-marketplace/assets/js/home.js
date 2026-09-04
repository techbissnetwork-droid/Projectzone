/* ==========================================================================
   TECHBISS Platform — index.html only
   Services grid (expandable, marketplace-style cards), hero product
   showcase (auto-advancing, scroll-snap track), marketplace teaser grid,
   trust tile toggles, and the scroll-reactive process timeline.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = (window.TECHBISS && window.TECHBISS.reduced) ||
    (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  /* ---------------- Services grid ---------------- */
  var SERVICES = [
    { id:'websites', name:'Website Design & Development', desc:'Custom, responsive websites built to convert.', from:'$199',
      icon:'<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M9 20h6M12 16v4"/>',
      details:['Custom design systems','Copy & content structure','Launch-ready in days'] },
    { id:'ecommerce', name:'E-commerce Development', desc:'Full online stores with checkout, inventory and payments.', from:'$349',
      icon:'<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2.2 11h9.6L19 8H6.2"/>',
      details:['Product catalog & variants','Secure checkout','Inventory & order management'] },
    { id:'apps', name:'Mobile App Development', desc:'iOS & Android apps connected to your existing systems.', from:'$1,499',
      icon:'<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 18.2h2"/>',
      details:['Native & cross-platform builds','Push notifications','App store submission support'] },
    { id:'webapps', name:'Custom Web Applications', desc:'Internal tools and platforms built around your workflow.', from:'$2,499',
      icon:'<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
      details:['Custom dashboards','Role-based access','API-first architecture'] },
    { id:'digitization', name:'Business Digitization', desc:'Move manual, offline operations into digital systems.', from:'$299',
      icon:'<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 3v5h-5"/>',
      details:['Process mapping','Data migration','Staff onboarding support'] },
    { id:'domain', name:'Domain Registration', desc:'Secure the right name across every extension.', from:'$12/yr',
      icon:'<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.6 2.3 4 5.3 4 8.5s-1.4 6.2-4 8.5c-2.6-2.3-4-5.3-4-8.5s1.4-6.2 4-8.5z"/>',
      details:['Bulk & multi-domain support','Auto-renewal included','Free WHOIS privacy'] },
    { id:'hosting', name:'Premium Hosting', desc:'Fast, managed hosting tuned for uptime.', from:'$9/mo',
      icon:'<rect x="3" y="4" width="18" height="6" rx="1.6"/><rect x="3" y="14" width="18" height="6" rx="1.6"/><circle cx="7" cy="7" r="1"/><circle cx="7" cy="17" r="1"/>',
      details:['Global CDN','Automatic backups','99.9% uptime target'] },
    { id:'vps', name:'VPS / Cloud Infrastructure', desc:'Scalable cloud servers for demanding workloads.', from:'$29/mo',
      icon:'<path d="M7 18a4.5 4.5 0 0 1-1-8.9A5.5 5.5 0 0 1 16.6 8 4 4 0 0 1 17 18H7z"/>',
      details:['Root access','Auto-scaling ready','Isolated resources'] },
    { id:'ssl', name:'SSL & Security', desc:'Encryption and hardening on every site we ship.', from:'Included',
      icon:'<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/>',
      details:['Free SSL certificates','Firewall & malware scanning','Security headers by default'] },
    { id:'email', name:'Business Email', desc:'Professional @yourbusiness.com inboxes.', from:'$5/mo',
      icon:'<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
      details:['Custom domain inboxes','Calendar & contacts','Spam & phishing filtering'] },
    { id:'payments', name:'Online Payment Integration', desc:'Accept cards, wallets and local payment methods.', from:'$199',
      icon:'<rect x="2.5" y="5.5" width="19" height="13" rx="2.2"/><path d="M2.5 10h19M6 15h4"/>',
      details:['Multiple gateway support','Recurring billing ready','PCI-compliant setup'] },
    { id:'booking', name:'Booking Systems', desc:'Appointments, tables and reservations, handled automatically.', from:'$249',
      icon:'<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9.5h18M8 3v4M16 3v4"/>',
      details:['Calendar sync','Automated reminders','No-show reduction rules'] },
    { id:'crm', name:'CRM / Business Systems', desc:'Track leads, customers and deals in one place.', from:'$399',
      icon:'<circle cx="9" cy="9" r="3.2"/><path d="M3.5 19c.8-3.3 3-5 5.5-5s4.7 1.7 5.5 5"/><circle cx="17.5" cy="8" r="2.4"/><path d="M15.8 12.1c2 .2 3.6 1.8 4.2 4.4"/>',
      details:['Pipeline & contact management','Team assignment','Custom fields & tags'] },
    { id:'automation', name:'Automation', desc:'Cut out the manual, repetitive parts of running the business.', from:'$299',
      icon:'<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
      details:['Workflow triggers','Email & SMS automation','System-to-system syncing'] },
    { id:'api', name:'API Integration', desc:'Connect the tools you already use.', from:'$349',
      icon:'<path d="M9 15 15 9"/><path d="M8 13.5 5.6 15.9a3 3 0 0 0 4.24 4.24L12.3 17.7"/><path d="M16 10.5l2.4-2.4a3 3 0 0 0-4.24-4.24L11.7 6.3"/>',
      details:['Third-party API connections','Custom middleware','Webhook support'] },
    { id:'maintenance', name:'Maintenance', desc:'Ongoing updates so nothing breaks quietly.', from:'$49/mo',
      icon:'<path d="M14.7 6.3a4 4 0 0 0-5.4 5.1L3 18l3 3 6.6-6.3a4 4 0 0 0 5.1-5.4l-2.9 2.9-2.2-.6-.6-2.2 2.7-2.1z"/>',
      details:['Regular updates & patches','Uptime monitoring','Monthly health reports'] },
    { id:'support', name:'Technical Support', desc:'A team to call when something needs fixing.', from:'$29/mo',
      icon:'<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2.5" y="13" width="4" height="6" rx="1.6"/><rect x="17.5" y="13" width="4" height="6" rx="1.6"/><path d="M20 19v.5a3 3 0 0 1-3 3h-3"/>',
      details:['Priority response times','Direct developer access','Issue tracking included'] },
    { id:'growth', name:'Digital Growth Solutions', desc:'SEO, content and campaigns that compound.', from:'$399/mo',
      icon:'<path d="M3 17l6-6 4 4 8-8"/><path d="M15 6h6v6"/>',
      details:['SEO & content strategy','Conversion optimization','Monthly growth reporting'] }
  ];

  var serviceGrid = document.querySelector('.service-grid');
  if (serviceGrid) {
    serviceGrid.innerHTML = SERVICES.map(function(s){
      return '' +
        '<div class="service-card accordion-item" id="service-' + s.id + '">' +
          '<button type="button" class="accordion-trigger service-trigger" aria-expanded="false">' +
            '<span class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + s.icon + '</svg></span>' +
            '<span class="service-trigger-body"><h3>' + s.name + '</h3><p>' + s.desc + '</p></span>' +
            '<span class="service-from"><span class="amount">' + s.from + '</span><span class="label">from</span></span>' +
            '<svg class="service-chevron" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>' +
          '</button>' +
          '<div class="accordion-panel"><div class="service-panel-inner"><ul>' + s.details.map(function(d){ return '<li>' + d + '</li>'; }).join('') + '</ul></div></div>' +
        '</div>';
    }).join('');
  }

  /* ---------------- Hero product showcase ---------------- */
  var showcase = document.querySelector('[data-showcase]');
  if (showcase && window.TB_PRODUCTS) {
    var track = showcase.querySelector('[data-showcase-track]');
    var dotsWrap = showcase.querySelector('[data-showcase-dots]');
    var prevBtn = showcase.querySelector('[data-showcase-prev]');
    var nextBtn = showcase.querySelector('[data-showcase-next]');
    var ids = ['ember-table','nova-commerce','harborline-suites','studio-forty'];
    var items = ids.map(function(id){ return window.TB_PRODUCTS.byId(id); }).filter(Boolean);
    track.innerHTML = items.map(function(p){ return window.TB_PRODUCTS.renderCard(p, { view:'compact' }); }).join('');
    dotsWrap.innerHTML = items.map(function(_, i){
      return '<button type="button" aria-label="Show product ' + (i + 1) + ' of ' + items.length + '"' + (i === 0 ? ' class="is-active"' : '') + '></button>';
    }).join('');

    var dots = Array.prototype.slice.call(dotsWrap.children);
    var cards = Array.prototype.slice.call(track.children);

    function cardStep(){
      if (!cards[0]) return 250;
      var style = getComputedStyle(track);
      var gap = parseFloat(style.columnGap || style.gap || '16') || 16;
      return cards[0].getBoundingClientRect().width + gap;
    }
    function currentIndex(){
      var step = cardStep();
      return step ? Math.round(track.scrollLeft / step) : 0;
    }
    function goTo(i){
      var n = cards.length;
      var idx = ((i % n) + n) % n;
      track.scrollTo({ left: idx * cardStep(), behavior: reduced ? 'auto' : 'smooth' });
    }
    function syncDots(){
      var idx = currentIndex();
      dots.forEach(function(d, i){ d.classList.toggle('is-active', i === idx); });
    }
    dots.forEach(function(d, i){ d.addEventListener('click', function(){ goTo(i); }); });
    if (prevBtn) prevBtn.addEventListener('click', function(){ goTo(currentIndex() - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function(){ goTo(currentIndex() + 1); });
    track.addEventListener('scroll', function(){
      clearTimeout(track._syncT);
      track._syncT = setTimeout(syncDots, 90);
    }, { passive: true });

    var paused = false;
    ['mouseenter','focusin','pointerdown'].forEach(function(ev){ showcase.addEventListener(ev, function(){ paused = true; }); });
    ['mouseleave','focusout'].forEach(function(ev){ showcase.addEventListener(ev, function(){ paused = false; }); });

    if (!reduced && cards.length > 1) {
      setInterval(function(){ if (!paused) goTo(currentIndex() + 1); }, 4200);
    }
  }

  /* ---------------- Marketplace teaser ---------------- */
  var teaserGrid = document.getElementById('teaser-grid');
  if (teaserGrid && window.TB_PRODUCTS) {
    var teaserIds = ['ember-table','nova-commerce','harborline-suites','meridian-properties','studio-forty','wanderlux-travel'];
    teaserGrid.innerHTML = teaserIds.map(function(id){
      var p = window.TB_PRODUCTS.byId(id);
      return p ? window.TB_PRODUCTS.renderCard(p, { view:'grid' }) : '';
    }).join('');
  }

  /* ---------------- Trust tiles ---------------- */
  document.querySelectorAll('[data-trust]').forEach(function(btn){
    var panel = btn.querySelector('.accordion-panel');
    if (!panel) return;
    btn.addEventListener('click', function(){
      var open = btn.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.style.maxHeight = open ? panel.scrollHeight + 'px' : '';
    });
  });

  /* ---------------- Process timeline (scroll-reactive) ---------------- */
  var steps = document.querySelectorAll('[data-process-step]');
  var rail = document.querySelector('[data-process]');
  if (steps.length && rail) {
    if (reduced || !('IntersectionObserver' in window)) {
      steps.forEach(function(s){ s.classList.add('is-done'); });
      steps[steps.length - 1].classList.remove('is-done');
      steps[steps.length - 1].classList.add('is-active');
      rail.style.setProperty('--progress', 100);
    } else {
      var stepIO = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (!entry.isIntersecting) return;
          var idx = Array.prototype.indexOf.call(steps, entry.target);
          steps.forEach(function(s, i){
            s.classList.toggle('is-active', i === idx);
            s.classList.toggle('is-done', i < idx);
          });
          rail.style.setProperty('--progress', (idx / (steps.length - 1)) * 100);
        });
      }, { threshold: 0.5, rootMargin: '-35% 0px -35% 0px' });
      steps.forEach(function(s){ stepIO.observe(s); });
    }
  }
})();
