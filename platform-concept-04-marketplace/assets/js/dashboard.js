/* ==========================================================================
   TECHBISS Client Dashboard — dashboard.html only
   Real client-side tab switching (sidebar + mobile tab strip, hash
   deep-linking), sample data rendered for projects, websites (with inline
   SVG sparklines), marketplace purchases, domains/hosting, billing and
   support tickets. Everything here is clearly-labeled sample data.
   ========================================================================== */
(function(){
  "use strict";
  var sidebar = document.querySelector('.dash-sidebar');
  if (!sidebar) return;

  /* ---------------- Sample data ---------------- */
  var PROJECTS = [
    { name:'Online Ordering Expansion', type:'Web Application', status:'in-progress', statusLabel:'In Progress', progress:65, updated:'2 days ago',
      desc:'Adding delivery-zone logic and real-time order status to the ordering flow.' },
    { name:'Ember & Table Mobile App', type:'Mobile App', status:'review', statusLabel:'In Review', progress:90, updated:'5 days ago',
      desc:'iOS and Android app for reservations, loyalty points and push offers.' },
    { name:'Catering Microsite', type:'Website', status:'planning', statusLabel:'Planning', progress:10, updated:'1 week ago',
      desc:'A dedicated site for the catering and private-events side of the business.' },
    { name:'POS Integration', type:'Custom Web Application', status:'completed', statusLabel:'Completed', progress:100, updated:'3 weeks ago',
      desc:'Connected the online ordering system directly to the in-house POS.' }
  ];
  var WEBSITES = [
    { name:'Ember & Table', url:'emberandtable.com', status:'live', data:[40,42,38,45,50,48,55,60,58,64,70,68,74,80] },
    { name:'Online Ordering', url:'order.emberandtable.com', status:'live', data:[20,22,25,24,30,35,33,38,42,40,45,50,48,52] },
    { name:'Staging Preview', url:'staging.emberandtable.com', status:'staging', data:[5,5,6,4,5,7,6,5,8,6,7,5,6,6] }
  ];
  var DOMAINS = [
    ['emberandtable.com','Primary Domain','Active','valid','Mar 14, 2027'],
    ['order.emberandtable.com','Subdomain','Active','valid','—'],
    ['emberandtable.net','Redirect Domain','Active','valid','Nov 2, 2026'],
    ['TECHBISS Premium Hosting','Hosting Plan','Active','—','Jan 8, 2027']
  ];
  var INVOICES = [
    ['INV-1051','Sep 1, 2026','Premium Hosting — Monthly','$29.00','due'],
    ['INV-1042','Aug 1, 2026','Premium Hosting — Monthly','$29.00','paid'],
    ['INV-1039','Jul 15, 2026','Online Ordering Expansion — Milestone 2','$850.00','paid'],
    ['INV-1031','Jul 1, 2026','Premium Hosting — Monthly','$29.00','paid'],
    ['INV-1025','Jun 20, 2026','Mobile App Development — Deposit','$1,200.00','paid']
  ];
  var TICKETS = [
    { id:'#4821', subject:'Order notifications delayed on iOS app', status:'open', updated:'4 hours ago',
      preview:'Push notifications for new orders are arriving 3-5 minutes late on iOS only — investigating the queue.' },
    { id:'#4790', subject:'Add a new staff account', status:'resolved', updated:'6 days ago',
      preview:'Requested an additional staff login for the new evening manager. Access granted.' },
    { id:'#4756', subject:'SSL renewal question', status:'resolved', updated:'3 weeks ago',
      preview:'Asked whether SSL renews automatically ahead of the March expiry — confirmed it does.' }
  ];

  /* ---------------- Renderers ---------------- */
  function renderProjects(){
    var wrap = document.getElementById('project-list');
    if (!wrap) return;
    wrap.innerHTML = PROJECTS.map(function(p){
      var pillClass = p.status === 'completed' ? 'badge-success' : p.status === 'review' ? 'badge-warning' : p.status === 'planning' ? 'badge-outline' : 'badge-soft';
      return '' +
        '<div class="project-card">' +
          '<div class="project-card-top">' +
            '<div><h3>' + p.name + '</h3><span class="project-type">' + p.type + '</span></div>' +
            '<span class="badge ' + pillClass + '">' + p.statusLabel + '</span>' +
          '</div>' +
          '<p>' + p.desc + '</p>' +
          '<div class="project-progress"><div class="progress-bar"><div class="progress-fill" style="width:' + p.progress + '%"></div></div><span>' + p.progress + '%</span></div>' +
          '<span class="project-updated">Updated ' + p.updated + '</span>' +
        '</div>';
    }).join('');
  }

  function sparkline(data, w, h){
    w = w || 160; h = h || 40;
    var max = Math.max.apply(null, data), min = Math.min.apply(null, data);
    var range = (max - min) || 1;
    var step = w / (data.length - 1);
    var points = data.map(function(v, i){
      var x = i * step;
      var y = h - ((v - min) / range) * (h - 6) - 3;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    return '<svg class="sparkline" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true"><polyline points="' + points + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }
  function renderWebsites(){
    var wrap = document.getElementById('website-list');
    if (!wrap) return;
    wrap.innerHTML = WEBSITES.map(function(w){
      return '' +
        '<div class="website-card">' +
          '<div class="website-card-top"><h3>' + w.name + '</h3><span class="badge ' + (w.status === 'live' ? 'badge-success' : 'badge-outline') + '">' + (w.status === 'live' ? 'Live' : 'Staging') + '</span></div>' +
          '<span class="website-url">' + w.url + '</span>' +
          '<div class="website-spark">' + sparkline(w.data) + '</div>' +
          '<span class="website-spark-label">Visits — last 14 days</span>' +
          '<div class="pc-actions"><a class="btn btn-outline btn-sm" href="marketplace-product.html?id=ember-table">Visit Site</a><a class="btn btn-primary btn-sm" href="installer.html?product=ember-table">Manage</a></div>' +
        '</div>';
    }).join('');
  }

  function renderPurchases(){
    if (!window.TB_PRODUCTS) return;
    var ids = ['ember-table','plateside-bistro'];
    var html = ids.map(function(id){
      var p = window.TB_PRODUCTS.byId(id);
      return p ? window.TB_PRODUCTS.renderCard(p, { view:'grid', actions:'dashboard' }) : '';
    }).join('');
    var full = document.getElementById('purchases-list');
    var mini = document.getElementById('overview-purchases');
    if (full) full.innerHTML = html;
    if (mini) mini.innerHTML = html;
  }

  function renderDomains(){
    var tbody = document.querySelector('#domains-table tbody');
    if (!tbody) return;
    tbody.innerHTML = DOMAINS.map(function(d){
      var ssl = d[3] === 'valid'
        ? '<span class="ssl-valid"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 8.5l3.2 3.2L13 4.8"/></svg>Valid</span>'
        : '<span class="text-dim">—</span>';
      return '<tr><td>' + d[0] + '</td><td>' + d[1] + '</td><td><span class="badge badge-success">' + d[2] + '</span></td><td>' + ssl + '</td><td>' + d[4] + '</td></tr>';
    }).join('');
  }

  function renderBilling(){
    var tbody = document.querySelector('#billing-table tbody');
    if (!tbody) return;
    tbody.innerHTML = INVOICES.map(function(inv){
      var paid = inv[4] === 'paid';
      return '<tr><td>' + inv[0] + '</td><td>' + inv[1] + '</td><td>' + inv[2] + '</td><td>' + inv[3] + '</td><td><span class="badge ' + (paid ? 'badge-success' : 'badge-warning') + '">' + (paid ? 'Paid' : 'Due') + '</span></td></tr>';
    }).join('');
  }

  function renderTickets(){
    var wrap = document.getElementById('ticket-list');
    if (!wrap) return;
    wrap.innerHTML = TICKETS.map(function(t){
      var open = t.status === 'open';
      return '' +
        '<div class="ticket-card">' +
          '<div class="ticket-top"><span class="ticket-id">' + t.id + '</span><span class="badge ' + (open ? 'badge-warning' : 'badge-success') + '">' + (open ? 'Open' : 'Resolved') + '</span></div>' +
          '<h3>' + t.subject + '</h3>' +
          '<p>' + t.preview + '</p>' +
          '<span class="ticket-updated">Updated ' + t.updated + '</span>' +
        '</div>';
    }).join('');
  }

  renderProjects();
  renderWebsites();
  renderPurchases();
  renderDomains();
  renderBilling();
  renderTickets();

  /* ---------------- Tab switching ---------------- */
  var TABS = ['overview','projects','websites','purchases','domains','billing','support'];
  var panels = {};
  TABS.forEach(function(t){ panels[t] = document.querySelector('[data-panel="' + t + '"]'); });
  var sideLinks = document.querySelectorAll('.dash-nav-link');
  var mobileTabs = document.querySelectorAll('.dash-mobile-tab');

  function activate(tab, updateHash){
    if (!panels[tab]) return;
    TABS.forEach(function(t){ if (panels[t]) panels[t].hidden = (t !== tab); });
    sideLinks.forEach(function(b){ b.classList.toggle('is-active', b.getAttribute('data-tab') === tab); });
    mobileTabs.forEach(function(b){ b.classList.toggle('is-active', b.getAttribute('data-tab') === tab); });
    if (updateHash) { try { history.replaceState(null, '', '#' + tab); } catch (e) {} }
  }
  sideLinks.forEach(function(b){ b.addEventListener('click', function(){ activate(b.getAttribute('data-tab'), true); }); });
  mobileTabs.forEach(function(b){ b.addEventListener('click', function(){ activate(b.getAttribute('data-tab'), true); }); });
  document.querySelectorAll('[data-goto]').forEach(function(b){
    b.addEventListener('click', function(){ activate(b.getAttribute('data-goto'), true); });
  });

  var initialTab = (location.hash || '').replace('#', '');
  activate(TABS.indexOf(initialTab) !== -1 ? initialTab : 'overview', false);
})();
