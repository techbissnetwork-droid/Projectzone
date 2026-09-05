(function(){
"use strict";

/* ===================================================================
   0. UTIL / MOTION PREFS
=================================================================== */
var BP = window.BASE_PATH || '';
var reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
var motionOK = !reduceQuery.matches;
reduceQuery.addEventListener && reduceQuery.addEventListener('change', function(e){ motionOK = !e.matches; });
function $(sel,ctx){ return (ctx||document).querySelector(sel); }
function $all(sel,ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
function fmtMoney(n){ return '$' + n.toLocaleString('en-US'); }
var ESC_MAP = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'};
/* Every field that ultimately comes from an admin-editable source (Content,
   Settings, a business's projects, etc.) must go through this before being
   concatenated into an innerHTML string — none of it is safe to trust,
   since a staff member with only a narrow section permission (e.g.
   "content" or "businesses") could otherwise plant script that runs for
   every site visitor or customer. */
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, function(c){ return ESC_MAP[c]; }); }

/* ===================================================================
   1. ICONS — small shared line-icon set
=================================================================== */
var ICONS = {
  rocket:'<path d="M12 2c3 2 5 6 4.5 11.5L14 16l-2 4-2-4-2.5-2.5C7 8 9 4 12 2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="9" r="1.6" stroke="currentColor" stroke-width="1.8"/>',
  cloud:'<path d="M7 18a4 4 0 0 1-.6-7.96A5 5 0 0 1 16.2 8.1 3.8 3.8 0 0 1 16 18H7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
  code:'<path d="M9 8 4.5 12 9 16M15 8l4.5 4-4.5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  chart:'<path d="M4 20V10M11 20V4M18 20v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  shield:'<path d="M12 3 5 6v5c0 5 3 7.5 7 10 4-2.5 7-5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  cart:'<circle cx="9" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><path d="M3 4h2l2.2 11h10.4L20 8H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  gear:'<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M12 3v2.4M12 18.6V21M21 12h-2.4M5.4 12H3M18.4 5.6l-1.7 1.7M7.3 16.7l-1.7 1.7M18.4 18.4l-1.7-1.7M7.3 7.3 5.6 5.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  users:'<circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 19c.7-3 3-5 5.5-5s4.8 2 5.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.6"/><path d="M15.8 14c2.1.3 3.8 2 4.4 4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
  chat:'<path d="M4 6h16v10H9l-4 3.5V16H4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
  compass:'<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M14.8 9.2 13 13l-3.8 1.8L11 11l3.8-1.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
  target:'<circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r=".8" fill="currentColor"/>',
  bolt:'<path d="M13 2 5 13h5l-1 9 8-11h-5l1-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
  puzzle:'<path d="M9 4h4v2.3a1.7 1.7 0 0 0 2.9 1.2A1.7 1.7 0 0 1 19 8.8V13h-2.3a1.7 1.7 0 0 0 0 3.4H19V20H9v-4h-2a2 2 0 1 1 0-4h2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
  globe:'<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 12h17M12 3.5c2.4 2.3 3.6 5.2 3.6 8.5s-1.2 6.2-3.6 8.5c-2.4-2.3-3.6-5.2-3.6-8.5S9.6 5.8 12 3.5Z" stroke="currentColor" stroke-width="1.6"/>',
  lock:'<rect x="5" y="10.5" width="14" height="9" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" stroke="currentColor" stroke-width="1.8"/>',
  logout:'<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 8l4.5 4-4.5 4M18.2 12H9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  monitor:'<rect x="3.5" y="4.5" width="17" height="12" rx="2.4" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 20h7M12 16.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  calendar:'<rect x="4" y="5.5" width="16" height="14.5" rx="2.6" stroke="currentColor" stroke-width="1.8"/><path d="M4 10h16M8 3.5v3M16 3.5v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  mail:'<rect x="3.5" y="5.5" width="17" height="13" rx="2.4" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 7 12 13l7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  phone:'<path d="M6 3.5h3l1.5 4L8.3 9.4a12 12 0 0 0 6.3 6.3l1.9-2.2 4 1.5v3a2 2 0 0 1-2.2 2C11 19.5 4.5 13 4 6.2A2 2 0 0 1 6 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  check:'<path d="M5 12.5 10 17 19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  arrow:'<path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  plus:'<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  minus:'<path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  close:'<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  download:'<path d="M12 3v12m0 0 4.5-4.5M12 15 7.5 10.5M4 19h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  play:'<path d="M8 5.5v13l11-6.5-11-6.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
  box:'<path d="M3.5 8 12 4l8.5 4-8.5 4-8.5-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3.5 8v8L12 20l8.5-4V8M12 12v8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  heart:'<path d="M12 20s-7.5-4.6-9.6-9.3C1 7.1 3 4 6.4 4c2 0 3.4 1.1 4.2 2.4C11.4 5.1 12.8 4 14.8 4 18.2 4 20 7.1 18.4 10.7 16.3 15.4 12 20 12 20Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  flag:'<path d="M6 3.5v17M6 4.5h12l-2.5 3.5L18 11.5H6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  book:'<path d="M4 5.5A2 2 0 0 1 6 4h6v16H6a2 2 0 0 1-2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M20 5.5A2 2 0 0 0 18 4h-6v16h6a2 2 0 0 0 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  search:'<circle cx="10.5" cy="10.5" r="6" stroke="currentColor" stroke-width="1.8"/><path d="m19 19-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  refresh:'<path d="M4 12a8 8 0 0 1 13.9-5.4M20 12a8 8 0 0 1-13.9 5.4M17 3.5V7h-3.5M7 20.5V17h3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
  spark:'<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
  layers:'<path d="M12 3.5 3.5 8 12 12.5 20.5 8 12 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m3.5 12 8.5 4.5L20.5 12M3.5 16l8.5 4.5L20.5 16" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
  star:'<path d="M12 3.4l2.7 5.5 6 .9-4.4 4.2 1 6-5.3-2.8-5.3 2.8 1-6-4.4-4.2 6-.9L12 3.4Z" fill="currentColor"/>'
};
function ico(name,cls){ return '<svg class="icon '+(cls||'')+'" viewBox="0 0 24 24" fill="none" aria-hidden="true">'+(ICONS[name]||ICONS.spark)+'</svg>'; }
function blobIcon(name,size,soft){ return '<div class="blob-icon '+(size||'')+' '+(soft?'soft':'')+'">'+ico(name)+'</div>'; }

/* ===================================================================
   2. THEME
=================================================================== */
var root = document.documentElement;
function applyTheme(t, persist){
  if(t){ root.setAttribute('data-theme', t); } else { root.removeAttribute('data-theme'); }
  if(persist!==false){ try{ localStorage.setItem('bloom-theme', t||''); }catch(e){} }
  var pressed = (t==='dark') || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
  $('#themeToggle').setAttribute('aria-pressed', String(pressed));
}
(function initTheme(){
  var saved = null;
  try{ saved = localStorage.getItem('bloom-theme'); }catch(e){}
  var S = window.SITE_SETTINGS || {};
  var siteDefault = (S.defaultTheme && S.defaultTheme!=='auto') ? S.defaultTheme : '';
  /* Only persist when the visitor has already made an explicit choice —
     otherwise a later admin-configured default couldn't reach anyone
     who merely loaded the site without ever touching the toggle. */
  applyTheme(saved!==null ? saved : siteDefault, false);
})();
$('#themeToggle').addEventListener('click', function(){
  var current = root.getAttribute('data-theme');
  var isDark = current ? current==='dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  applyTheme(isDark ? 'light' : 'dark');
  $all('.logo-mark').forEach(function(mark){
    mark.classList.remove('flourish');
    void mark.offsetWidth;
    mark.classList.add('flourish');
  });
});

/* ===================================================================
   3. SPLASH (once per tab session)
=================================================================== */
(function splash(){
  var s = $('#splash');
  if(window.SITE_SETTINGS && window.SITE_SETTINGS.splashEnabled === false){ s.remove(); return; }
  var seen=false;
  try{ seen = sessionStorage.getItem('bloomIntro')==='1'; }catch(e){}
  if(seen){ s.remove(); return; }
  function dismiss(){
    s.classList.add('hide');
    try{ sessionStorage.setItem('bloomIntro','1'); }catch(e){}
    document.removeEventListener('pointerdown', dismiss, true);
    document.removeEventListener('keydown', dismiss);
    setTimeout(function(){ s.remove(); }, 550);
  }
  // Use the capture-phase pointerdown (not click) so pointer-events:none
  // lands before the browser hit-tests the click, and the click itself
  // reaches whatever the user actually meant to press underneath.
  document.addEventListener('pointerdown', dismiss, true);
  document.addEventListener('keydown', dismiss);
  setTimeout(dismiss, motionOK ? 1400 : 250);
})();

/* ===================================================================
   4. BLOB BACKGROUND FIELD — morphing SVG blobs w/ scroll parallax
=================================================================== */
var BlobField = (function(){
  var wrap = $('#blobField');
  var blobs = [];
  function rand(a,b){ return a + Math.random()*(b-a); }
  function makeRadii(n){ var r=[]; for(var i=0;i<n;i++) r.push(rand(.78,1.22)); return r; }
  function pathFromRadii(radii, cx, cy, base){
    var n = radii.length, pts=[];
    for(var i=0;i<n;i++){
      var a = (i/n)*Math.PI*2;
      pts.push([cx+Math.cos(a)*base*radii[i], cy+Math.sin(a)*base*radii[i]]);
    }
    var d = 'M '+pts[0][0].toFixed(1)+' '+pts[0][1].toFixed(1)+' ';
    for(var j=0;j<n;j++){
      var p0=pts[(j-1+n)%n], p1=pts[j], p2=pts[(j+1)%n], p3=pts[(j+2)%n];
      var c1x=p1[0]+(p2[0]-p0[0])/6, c1y=p1[1]+(p2[1]-p0[1])/6;
      var c2x=p2[0]-(p3[0]-p1[0])/6, c2y=p2[1]-(p3[1]-p1[1])/6;
      d += 'C '+c1x.toFixed(1)+' '+c1y.toFixed(1)+', '+c2x.toFixed(1)+' '+c2y.toFixed(1)+', '+p2[0].toFixed(1)+' '+p2[1].toFixed(1)+' ';
    }
    return d+'Z';
  }
  var defs = [
    {top:'-8%', left:'-6%', size:520, color:'var(--accent-1)', op:.10, parallax:.06},
    {top:'55%', left:'82%', size:620, color:'var(--accent-2)', op:.09, parallax:.1},
    {top:'80%', left:'-10%', size:460, color:'var(--accent-3)', op:.08, parallax:.04},
    {top:'20%', left:'60%', size:380, color:'var(--accent-1)', op:.07, parallax:.14}
  ];
  defs.forEach(function(d,i){
    var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('viewBox','0 0 200 200');
    svg.style.top=d.top; svg.style.left=d.left; svg.style.width=d.size+'px'; svg.style.height=d.size+'px';
    svg.style.opacity=d.op;
    var path = document.createElementNS('http://www.w3.org/2000/svg','path');
    path.setAttribute('fill', d.color);
    svg.appendChild(path);
    wrap.appendChild(svg);
    var radii = makeRadii(8);
    var target = makeRadii(8);
    path.setAttribute('d', pathFromRadii(radii,100,100,78));
    blobs.push({svg:svg, path:path, radii:radii, target:target, parallax:d.parallax, base:d.top});
  });
  var raf=null, last=0;
  function tick(ts){
    if(!last) last=ts;
    var dt = Math.min(ts-last, 50); last=ts;
    blobs.forEach(function(b){
      var changed=false;
      for(var i=0;i<b.radii.length;i++){
        b.radii[i] += (b.target[i]-b.radii[i]) * dt*0.0006;
        if(Math.abs(b.target[i]-b.radii[i])<0.01){ b.target[i]=rand(.78,1.22); }
        changed=true;
      }
      if(changed) b.path.setAttribute('d', pathFromRadii(b.radii,100,100,78));
    });
    raf = requestAnimationFrame(tick);
  }
  function start(){ if(!raf && motionOK && !document.hidden) raf=requestAnimationFrame(tick); }
  function stop(){ if(raf){ cancelAnimationFrame(raf); raf=null; last=0; } }
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
  function onScroll(){
    var y = window.scrollY;
    blobs.forEach(function(b){ b.svg.style.transform = 'translateY('+(y*b.parallax*-1)+'px)'; });
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  if(motionOK) start(); else blobs.forEach(function(b){ /* static shape already set */ });
  return {start:start, stop:stop};
})();

/* ===================================================================
   5. NAV — blob indicator + routing links + mobile sheet
=================================================================== */
var ROUTES = [
  {path:'/', label:'Home'},
  {path:'/services', label:'Services'},
  {path:'/solutions', label:'Solutions'},
  {path:'/marketplace', label:'Marketplace'},
  {path:'/work', label:'Work'},
  {path:'/process', label:'Process'},
  {path:'/pricing', label:'Pricing'},
  {path:'/about', label:'About'},
  {path:'/resources', label:'Resources'},
  {path:'/contact', label:'Contact'}
];
var navLinksEl = $('#navLinks');
ROUTES.forEach(function(r){
  var a = document.createElement('a');
  a.href = BP+r.path; a.className='nav-link'; a.textContent=r.label; a.dataset.path=r.path;
  navLinksEl.appendChild(a);
});
var navBlob = $('#navBlob');
function moveNavBlob(targetEl){
  if(!targetEl){ navBlob.style.opacity=0; return; }
  var navRect = navLinksEl.getBoundingClientRect();
  var r = targetEl.getBoundingClientRect();
  navBlob.style.opacity=1;
  navBlob.style.left = (r.left-navRect.left)+'px';
  navBlob.style.width = r.width+'px';
}
function currentNavLink(){
  var p = currentBasePath();
  return $all('.nav-link').filter(function(a){ return a.dataset.path===p; })[0];
}
navLinksEl.addEventListener('mouseover', function(e){
  var a = e.target.closest('.nav-link'); if(a) moveNavBlob(a);
});
navLinksEl.addEventListener('mouseleave', function(){ moveNavBlob(currentNavLink()); });

var mobileNav = $('#mobileNav');
/* Home is left out of this sheet — the bottom dock (visible at the same
   widths this sheet opens from) already has its own dedicated Home icon,
   so listing it again here would just duplicate it. */
ROUTES.filter(function(r){ return r.path !== '/'; }).forEach(function(r){
  var a = document.createElement('a');
  a.href=BP+r.path; a.dataset.path=r.path;
  a.innerHTML = r.label + ico('arrow');
  mobileNav.appendChild(a);
});
/* "Log in" is intentionally left out of this menu — the bottom dock (visible
   at the same widths this menu opens from) already has its own dedicated
   Log in icon, so listing it again here would just duplicate it. */
var burger=$('#navBurger'), sheet=$('#mobileSheet'), backdrop=$('#sheetBackdrop'), dockMenuBtn=$('#dockMenuBtn');
var burgerIconOpen = burger.querySelector('svg').outerHTML;
var dockMenuIconOpen = dockMenuBtn.querySelector('svg').outerHTML;
var closeIconSvg = ico('close');
function openSheet(o){
  sheet.classList.toggle('open',o); backdrop.classList.toggle('open',o);
  burger.setAttribute('aria-expanded', String(o));
  dockMenuBtn.setAttribute('aria-expanded', String(o));
  burger.setAttribute('aria-label', o ? 'Close menu' : 'Open menu');
  dockMenuBtn.setAttribute('aria-label', o ? 'Close navigation menu' : 'Open navigation menu');
  burger.querySelector('svg').outerHTML = o ? closeIconSvg : burgerIconOpen;
  dockMenuBtn.querySelector('svg').outerHTML = o ? closeIconSvg : dockMenuIconOpen;
  var dockLabel = dockMenuBtn.querySelector('span'); if(dockLabel) dockLabel.textContent = o ? 'Close' : 'Menu';
  document.body.style.overflow = o ? 'hidden':'';
}
burger.addEventListener('click', function(){ openSheet(!sheet.classList.contains('open')); });
dockMenuBtn.addEventListener('click', function(){ openSheet(!sheet.classList.contains('open')); });
backdrop.addEventListener('click', function(){ openSheet(false); });
mobileNav.addEventListener('click', function(){ openSheet(false); });
/* A close button lives inside the sheet itself (not just the backdrop and
   the dock button) because with enough nav items the sheet can grow tall
   enough to cover the dock's own Close icon — this one is always visible
   regardless of how much content is in the sheet. */
var sheetCloseBtn = $('#sheetCloseBtn');
if(sheetCloseBtn) sheetCloseBtn.addEventListener('click', function(){ openSheet(false); });

/* The bottom dock's 4 icons must stay visible and usable at all times,
   even while the menu sheet above it is open — so the sheet and its
   backdrop stop exactly at the dock's top edge (--dock-h), measured here
   instead of guessed, since the dock's real height depends on the
   device's safe-area inset. */
var bottomDock = $('.bottom-dock');
function syncDockHeight(){
  if(bottomDock) document.documentElement.style.setProperty('--dock-h', bottomDock.getBoundingClientRect().height+'px');
}
syncDockHeight();
window.addEventListener('resize', syncDockHeight);

/* ===================================================================
   6. 3D TILT + MAGNETIC
=================================================================== */
function attachTilt(container){
  if(!motionOK) return;
  $all('.tilt', container).forEach(function(card){
    card.addEventListener('mousemove', function(e){
      var r = card.getBoundingClientRect();
      var px = (e.clientX-r.left)/r.width, py=(e.clientY-r.top)/r.height;
      var rx = (py-.5)*-10, ry=(px-.5)*10;
      card.style.transform = 'perspective(900px) rotateX('+rx+'deg) rotateY('+ry+'deg) translateY(-4px)';
    });
    card.addEventListener('mouseleave', function(){ card.style.transform=''; });
  });
  $all('.magnetic', container).forEach(function(btn){
    btn.addEventListener('mousemove', function(e){
      var r = btn.getBoundingClientRect();
      var mx=(e.clientX-r.left-r.width/2)*.25, my=(e.clientY-r.top-r.height/2)*.35;
      btn.style.transform='translate('+mx+'px,'+my+'px)';
    });
    btn.addEventListener('mouseleave', function(){ btn.style.transform=''; });
  });
}

/* ===================================================================
   7. CONFETTI
=================================================================== */
function confettiBurst(x,y){
  var layer = document.createElement('div'); layer.className='confetti-layer';
  document.body.appendChild(layer);
  var colors=['var(--accent-1)','var(--accent-2)','var(--accent-3)','#ffffff'];
  var count = motionOK ? 46 : 12;
  for(var i=0;i<count;i++){
    var p=document.createElement('div'); p.className='confetti-piece';
    var size = 6+Math.random()*8;
    p.style.width=size+'px'; p.style.height=(size*.5+4)+'px';
    p.style.background=colors[i%colors.length];
    p.style.left=(x!=null?x:50+Math.random()*200-100)+ (x!=null?'px':'%');
    if(x==null){ p.style.left=(Math.random()*100)+'%'; } else { p.style.left = (x + (Math.random()*260-130))+'px'; p.style.top=(y||120)+'px'; }
    p.style.animationDuration=(1.6+Math.random()*1.4)+'s';
    p.style.animationDelay=(Math.random()*.3)+'s';
    p.style.transform='rotate('+(Math.random()*360)+'deg)';
    layer.appendChild(p);
  }
  setTimeout(function(){ layer.remove(); }, 3200);
}

/* ===================================================================
   8. SHARED CONTENT DATA
=================================================================== */
var SERVICES = window.SERVICES_DATA || [
  {icon:'monitor', name:'Website Design & Development', blurb:'A site built around your business, not squeezed into a generic template.', bullets:['Custom design & copy','Fast, mobile-friendly pages','Built to grow as you do']},
  {icon:'code', name:'App Development', blurb:'iOS and Android apps built from your idea, not a boilerplate.', bullets:['iOS & Android, one build','Real designs before we code','Built to pass App Store review']},
  {icon:'globe', name:'Domain, Hosting & Email', blurb:'The unglamorous stuff, set up right the first time and never left to lapse.', bullets:['Domain registration & DNS','Fast hosting with SSL included','Business email on your domain']},
  {icon:'rocket', name:'App Store & Play Store Publishing', blurb:'We handle listings, screenshots and the entire review process.', bullets:['Store listing & screenshots','Submission & review handled','Updates after you launch']},
  {icon:'chart', name:'SEO & Search Ranking', blurb:'So being online actually means being found.', bullets:['On-page & technical SEO','Google Maps & local search','Plain-language ranking reports']},
  {icon:'cart', name:'Ready-Made Themes & Templates', blurb:'Buy a theme, brand it as your own, and launch in days.', bullets:['Fully brandable, no lock-in','Your logo, colors & content','Same support as a custom build']}
];
var SOLUTIONS = window.SOLUTIONS_DATA || [
  {icon:'cart', name:'Shops & Local Retail', out:['An online store that matches your storefront','Orders and inventory in one place','Local SEO so nearby customers find you']},
  {icon:'heart', name:'Restaurants & Cafés', out:['Menu, hours & online ordering','Table booking built in','Your Google & Maps listing done right']},
  {icon:'gear', name:'Home & Local Services', out:['Booking & quote requests online','Service-area SEO that actually ranks','Reviews and contact, front and center']},
  {icon:'spark', name:'Creators & Personal Brands', out:['A site or app that looks like you','Portfolio, shop or booking in one place','App store publishing handled']},
  {icon:'flag', name:'Nonprofits & Community Groups', out:['Donation & event pages','Volunteer sign-ups made simple','Discounted plans available']}
];
var CASESTUDIES = window.CASESTUDIES_DATA || [
  {sector:'Bakery', icon:'cart', client:'Maple & Co. Bakery', stat:'+64%', statLabel:'online orders in month one', quote:'We went from a Facebook page to a real website with ordering in under two weeks.', body:'Maple & Co. was taking orders through Facebook comments and DMs. We built them a website with online ordering, connected a custom domain and business email, and got them ranking for "bakery near me" in their own neighborhood.'},
  {sector:'Fitness', icon:'heart', client:'Solstice Yoga Studio', stat:'3x', statLabel:'more class bookings', quote:'Our booking calendar used to be a shared spreadsheet. Now people book from their phone.', body:'Solstice had no website at all — just word of mouth. We built them a site with class booking, set up hosting and email, and helped them show up in local search.'},
  {sector:'Home services', icon:'gear', client:'Corner Hardware & Repair', stat:'+120', statLabel:'quote requests per month', quote:'People find us on Google now instead of just driving past.', body:'Corner Hardware had a storefront but no online presence at all. We built a simple, fast site with a quote-request form and got them ranking on Google Maps for their service area.'},
  {sector:'Creator', icon:'spark', client:'Nomad Coffee Roasters', stat:'2 wks', statLabel:'from first call to a live app', quote:'We had an idea for a loyalty app on a napkin. Two weeks later it was in the App Store.', body:'Nomad wanted a simple loyalty app for regulars. We designed it, built it, and handled the entire App Store submission — they never had to touch a developer account.'},
  {sector:'Nonprofit', icon:'flag', client:'Kinship Pet Rescue', stat:'+210', statLabel:'volunteer sign-ups since launch', quote:'Donations and volunteer sign-ups finally happen without ten emails back and forth.', body:'Kinship ran on a free page builder that couldn\'t handle donations or sign-ups. We rebuilt their site, added donation and volunteer forms, and moved them onto their own domain.'},
  {sector:'Retail', icon:'box', client:'Bloom & Bramble Florist', stat:'+47%', statLabel:'website-driven sales', quote:'Customers can finally order flowers from their phone at 11pm.', body:'Bloom & Bramble took phone orders only. We built an online store with same-day-delivery scheduling and got them showing up first for local flower searches.'}
];
var PRODUCTS = window.PRODUCTS_DATA || [];

/* ===================================================================
   9. SMALL RENDER HELPERS
=================================================================== */
function wave(variant, fill, flip){
  var paths = {
    a:'M0,60 C 200,110 340,10 540,50 C 740,90 900,20 1100,55 C 1250,80 1350,40 1440,50 L1440,120 L0,120 Z',
    b:'M0,40 C 220,90 360,0 620,40 C 880,80 1040,10 1260,45 C 1350,60 1400,50 1440,40 L1440,120 L0,120 Z',
    c:'M0,70 C 180,20 380,100 620,55 C 860,10 1060,90 1280,50 C 1360,35 1410,45 1440,55 L1440,120 L0,120 Z'
  };
  return '<svg class="wave'+(flip?' wave-top':'')+'" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="'+paths[variant]+'" fill="'+fill+'"/></svg>';
}
function serviceCard(s){
  return '<div class="card tilt"><div class="card-head">'+blobIcon(s.icon,'sm')+'<h3>'+esc(s.name)+'</h3></div><p>'+esc(s.blurb)+'</p><ul style="margin:0 0 18px;display:flex;flex-direction:column;gap:8px;">'+
    s.bullets.map(function(b){ return '<li style="display:flex;gap:8px;align-items:flex-start;color:var(--ink-soft);font-size:.9rem;">'+ico('check','').replace('width:22px','width:16px')+'<span>'+esc(b)+'</span></li>'; }).join('')+
    '</ul><a href="'+BP+'/contact" class="card-link">Talk to us '+ico('arrow')+'</a></div>';
}
function solutionCard(s){
  return '<div class="card tilt"><div class="card-head">'+blobIcon(s.icon,'sm')+'<h3>'+esc(s.name)+'</h3></div><ul style="display:flex;flex-direction:column;gap:8px;">'+
    s.out.map(function(o){ return '<li style="display:flex;gap:8px;align-items:flex-start;color:var(--ink-soft);font-size:.9rem;">'+ico('check')+'<span>'+esc(o)+'</span></li>'; }).join('')+
    '</ul></div>';
}
function statBlock(num,label){ return '<div class="stat"><div class="num grad-text">'+esc(num)+'</div><div class="label">'+esc(label)+'</div></div>'; }

/* ===================================================================
   10. PAGE RENDERERS
=================================================================== */
var Pages = {};

Pages['/'] = function(){
  var S = window.SITE_SETTINGS || {};
  return ''
  +'<section class="hero"><div class="container hero-grid">'
    +'<div><span class="eyebrow">Websites & apps, fully handled</span>'
    +'<h1>'+esc(S.heroHeadlineMain||'We help offline businesses')+' <span class="grad-text">'+esc(S.heroHeadlineAccent||'thrive online.')+'</span></h1>'
    +'<p class="lede">'+esc(S.heroSubheadline||'TECHBISS builds your website or app, then sets up your domain, hosting, email and app store listing — so you launch with everything working and ready to be found.')+'</p>'
    +'<div class="hero-cta"><a href="'+BP+'/services" class="btn btn-primary magnetic">See what we build '+ico('arrow')+'</a><a href="'+BP+'/contact" class="btn btn-ghost magnetic">Book a free call</a></div>'
    +'<div class="hero-stats">'+statBlock(S.stat1Value||'1,900+',S.stat1Label||'Businesses & apps launched')+statBlock(S.stat2Value||'38',S.stat2Label||'Countries served')+statBlock(S.stat3Value||'4.9/5',S.stat3Label||'Customer rating')+statBlock(S.stat4Value||'72 hrs',S.stat4Label||'To your first draft')+'</div>'
    +'</div>'
    +'<div class="hero-visual"><svg class="hero-blob-main" viewBox="0 0 200 200"><path fill="url(#heroGrad)" d="M52,-64C67,-54,78,-38,81,-20C84,-2,79,17,68,33C57,49,40,62,20,69C0,76,-24,77,-42,66C-60,55,-72,32,-75,8C-78,-16,-72,-42,-56,-58C-40,-74,-14,-80,7,-83C28,-86,37,-74,52,-64Z" transform="translate(100 100)"/><defs><linearGradient id="heroGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" style="stop-color:var(--accent-1)"/><stop offset="55%" style="stop-color:var(--accent-3)"/><stop offset="100%" style="stop-color:var(--accent-2)"/></linearGradient></defs></svg>'
      +'<div class="float-chip chip-1">'+ico('rocket')+'<span>Live in days, not months</span></div>'
      +'<div class="float-chip chip-2">'+ico('shield')+'<span>Domain & hosting included</span></div>'
      +'<div class="float-chip chip-3">'+ico('users')+'<span>A real person replies</span></div>'
    +'</div>'
  +'</div></section>'

  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<p style="text-align:center;color:var(--ink-faint);font-size:.85rem;font-family:var(--font-display);letter-spacing:.04em;text-transform:uppercase;margin-bottom:22px;">Loved by shop owners, creators and small teams going online</p>'
    +'<div class="trust-strip"><div class="trust-track">'
      + CASESTUDIES.map(function(c){return c.client;}).concat(CASESTUDIES.map(function(c){return c.client;})).map(function(n){return '<span>'+esc(n)+'</span>';}).join('')
    +'</div></div>'
  +'</div></section>'
  +wave('b','var(--bg)',true)

  +'<section class="section tone-a"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">What we do</span><h2>Everything it takes to go from offline to online</h2><p class="lede" style="margin-inline:auto;">One team handles the whole journey — build it, launch it, and make sure people can actually find it.</p></div>'
    +'<div class="grid grid-3">'+SERVICES.slice(0,6).map(serviceCard).join('')+'</div>'
  +'</div></section>'

  +wave('c','var(--bg-alt-2)')
  +'<section class="section tone-c"><div class="container">'
    +'<div class="section-head"><span class="eyebrow">How we work</span><h2>A process built to remove surprises</h2></div>'
    +'<div class="grid grid-4">'
      +['Discover','Design','Build','Grow'].map(function(t,i){
        return '<div class="card tilt"><div class="card-head">'+blobIcon(['compass','spark','code','chart'][i],'sm',true)+'<h3 style="font-size:1.05rem;">'+(i+1)+'. '+t+'</h3></div><p style="font-size:.88rem;">'+['We learn your business and what \'done\' should look like.','Your site or app takes shape before we build anything final.','We build it, and connect your domain, hosting and email.','SEO and small updates keep new customers finding you.'][i]+'</p></div>';
      }).join('')
    +'</div>'
    +'<div class="text-center" style="margin-top:34px;"><a href="'+BP+'/process" class="btn btn-soft magnetic">See the full process '+ico('arrow')+'</a></div>'
  +'</div></section>'
  +wave('a','var(--bg)',true)

  +'<section class="section tone-a"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Recent work</span><h2>Real businesses, now online</h2></div>'
    +'<div class="grid grid-3">'+CASESTUDIES.slice(0,3).map(function(c){
      return '<div class="card tilt"><div class="card-head">'+blobIcon(c.icon,'sm')+'<h3>'+esc(c.client)+'</h3></div><p>'+esc(c.body)+'</p>'
      +'<div class="stat" style="margin-bottom:14px;">'+statBlock(c.stat,c.statLabel)+'</div>'
      +'<div class="flex items-center justify-between"><a href="'+BP+'/work" class="card-link">Read the story '+ico('arrow')+'</a><span class="badge">'+esc(c.sector)+'</span></div></div>';
    }).join('')+'</div>'
  +'</div></section>'

  +wave('b','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +(CASESTUDIES[0]?'<div class="quote-blob"><p>"'+esc(CASESTUDIES[0].quote)+'"</p><cite>— '+esc(CASESTUDIES[0].client)+'</cite></div>':'')
  +'</div></section>'
  +wave('c','var(--bg-alt-2)',true)

  +'<section class="section tone-c"><div class="container text-center">'
    +'<h2 style="max-width:20ch;margin-inline:auto;">Ready to take your business online?</h2>'
    +'<p class="lede" style="margin:0 auto 28px;">Tell us about your business, we\'ll tell you exactly what it takes to get you live.</p>'
    +'<div class="hero-cta" style="justify-content:center;"><a href="'+BP+'/contact" class="btn btn-primary magnetic">Book a free call</a><a href="'+BP+'/pricing" class="btn btn-ghost magnetic">See pricing</a></div>'
  +'</div></section>';
};

Pages['/services'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Services</span><h1 style="max-width:16ch;">Everything you need to take your business online.</h1>'
    +'<p class="lede">From a new website or app to your domain, hosting, email and search visibility — choose only what you need, or let us handle it all.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-2">'+SERVICES.map(function(s,i){
      return '<div class="card tilt" style="display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:flex-start;">'
        +blobIcon(s.icon,'lg')
        +'<div><h3 style="font-size:1.35rem;">'+esc(s.name)+'</h3><p>'+esc(s.blurb)+'</p>'
        +'<ul style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">'+s.bullets.map(function(b){return '<li style="display:flex;gap:8px;font-size:.9rem;color:var(--ink-soft);">'+ico('check')+'<span>'+esc(b)+'</span></li>';}).join('')+'</ul>'
        +'<a href="'+BP+'/contact" class="card-link">Start a conversation '+ico('arrow')+'</a></div></div>';
    }).join('')+'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Ways to work with us</span><h2>Build it, buy a theme, or keep it running</h2></div>'
    +'<div class="grid grid-3">'
      +[['One-time build','A website or app, built once and handed over — fully yours, no lock-in.','box'],
        ['Bring your own template','Buy a theme from our marketplace and we\'ll brand and launch it for you.','cart'],
        ['Care plan','Hosting, updates, small changes and support, handled every month.','refresh']]
      .map(function(m){ return '<div class="card tilt"><div class="card-head">'+blobIcon(m[2],'sm',true)+'<h3>'+esc(m[0])+'</h3></div><p>'+esc(m[1])+'</p></div>'; }).join('')
    +'</div>'
  +'</div></section>';
};

Pages['/solutions'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Who we help</span><h1 style="max-width:18ch;">Built for real businesses, not enterprise IT teams.</h1>'
    +'<p class="lede">Every business is different, but the care we put into your project never changes.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-3">'+SOLUTIONS.map(solutionCard).join('')+'</div>'
  +'</div></section>'
  +wave('b','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Pick your path</span><h2>Build, buy a theme, or add an app</h2></div>'
    +'<div class="table-wrap"><table><thead><tr><th>Path</th><th>Best for</th><th>Typical timeline</th><th>Starting from</th></tr></thead><tbody>'
      +'<tr><td><b>Build</b></td><td>A site or app built from scratch around your business</td><td>2–6 weeks</td><td>'+fmtMoney(S.priceStartBuild||900)+'</td></tr>'
      +'<tr><td><b>Buy</b></td><td>A ready-made theme, branded and launched as your own</td><td>2–5 days</td><td>'+fmtMoney(S.priceStartBuy||59)+'</td></tr>'
      +'<tr><td><b>Publish</b></td><td>Add an app and get it live on the App Store & Play Store</td><td>3–8 weeks</td><td>'+fmtMoney(S.priceStartPublish||1500)+'</td></tr>'
    +'</tbody></table></div>'
  +'</div></section>';
};

Pages['/marketplace'] = function(){
  var S = window.SITE_SETTINGS || {};
  /* Derived from what is actually on sale, so a category added in admin
     can't produce products that no chip is able to filter to. */
  var cats = ['All'].concat(PRODUCTS.map(function(p){ return p.cat; })
    .filter(function(c, i, a){ return c && a.indexOf(c) === i; })
    .sort());
  return '<section class="hero hero-sub" style="padding-bottom:10px;"><div class="container">'
    +'<span class="eyebrow">Marketplace</span><h1 style="max-width:16ch;">Ready-made themes, branded just for you.</h1>'
    +'<p class="lede">Preview any theme, then let us apply your logo and colors and launch it as your own — every listing is built and maintained by the '+esc(S.siteName||'TECHBISS')+' studio.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container">'
    +'<div class="search-field">'+ico('search')+'<input type="search" id="mktSearch" placeholder="Search the marketplace" aria-label="Search marketplace"></div>'
    +'<div class="chip-row" id="mktChips">'+cats.map(function(c,i){return '<button class="chip'+(i===0?' active':'')+'" data-cat="'+c+'">'+c+'</button>';}).join('')+'</div>'
    +'<p class="mkt-count" id="mktCount"></p>'
    +'<div class="grid grid-4" id="mktGrid"></div>'
  +'</div></section>';
};

Pages['/marketplace/detail'] = function(id){
  var p = PRODUCTS.filter(function(x){return x.id===id;})[0];
  /* Falling back to PRODUCTS[0] showed a different product under the
     requested URL — and threw outright when the catalogue was empty. */
  if(!p){
    return '<section class="hero"><div class="container text-center" style="padding:70px 20px;">'
      +'<span class="eyebrow">Marketplace</span>'
      +'<h1 style="max-width:20ch;margin-inline:auto;">That product isn\'t available.</h1>'
      +'<p class="lede" style="max-width:42ch;margin:14px auto 24px;">It may have been renamed or taken off sale. Everything currently available is on the marketplace.</p>'
      +'<a href="'+BP+'/marketplace" class="btn btn-primary">Browse the marketplace '+ico('arrow')+'</a>'
    +'</div></section>';
  }
  return '<section class="hero" style="padding-bottom:0;"><div class="container">'
    +'<a href="'+BP+'/marketplace" class="card-link" style="margin-bottom:18px;">'+ico('arrow').replace('12h14','14h-14').replace('M13 6l6 6-6 6','M11 6l-6 6 6 6')+' Back to marketplace</a>'
    +'<div class="hero-grid" style="align-items:flex-start;">'
      +'<div>'
        +'<span class="badge grad">'+esc(p.cat)+'</span>'
        +'<h1 style="margin-top:14px;">'+esc(p.name)+'</h1>'
        +'<p class="lede">'+esc(p.tagline)+'</p>'
        +'<div class="flex items-center gap-12" style="margin:18px 0 28px;"><span class="stat"><span class="num" style="font-size:1.4rem;">'+fmtMoney(p.price)+'</span></span><span class="badge">★ '+esc(p.rating)+' rating</span></div>'
      +'</div>'
      +(p.image
        ? '<div class="hero-visual" style="aspect-ratio:4/3;overflow:hidden;border-radius:20px;"><img src="'+BP+'/'+esc(p.image)+'" alt="" style="width:100%;height:100%;object-fit:cover;"></div>'
        : '<div class="hero-visual" style="aspect-ratio:4/3;"><svg viewBox="0 0 200 150" style="width:100%;height:100%;"><path fill="url(#pdGrad)" d="M40,-40C56,-30,68,-10,66,10C64,30,48,46,28,54C8,62,-16,62,-34,50C-52,38,-64,14,-62,-8C-60,-30,-44,-50,-24,-58C-4,-66,20,-50,40,-40Z" transform="translate(100 76)"/><defs><linearGradient id="pdGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" style="stop-color:var(--accent-1)"/><stop offset="100%" style="stop-color:var(--accent-2)"/></linearGradient></defs></svg><div style="position:absolute;color:#fff;">'+ico(p.icon).replace('width:22px;height:22px','width:56px;height:56px')+'</div></div>')
    +'</div></div></section>'
  +'<section class="section tone-a" style="padding-top:26px;"><div class="container">'
    +'<div class="tabbar" id="pdTabs" role="tablist">'
      +['Overview', ((window.SITE_SETTINGS||{}).paymentsEnabled ? 'Buy' : 'Get it')].map(function(t,i){return '<button role="tab" class="'+(i===0?'active':'')+'" data-tab="'+(i===0?'overview':'buy')+'">'+(i+1)+'. '+esc(t)+'</button>';}).join('')
    +'</div>'
    +'<div id="pdPanels" data-pid="'+esc(p.id)+'"></div>'
  +'</div></section>';
};

Pages['/work'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Work</span><h1 style="max-width:18ch;">Real businesses, now online.</h1>'
    +'<p class="lede">A few of the shops, studios and teams behind the stats on our homepage.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-2" id="caseGrid">'+CASESTUDIES.map(function(c,i){
      return '<div class="card tilt case-card" data-i="'+i+'"><div class="flex items-center gap-16" style="margin-bottom:14px;">'+blobIcon(c.icon,'sm')+'<div><span class="badge">'+esc(c.sector)+'</span><h3 style="margin:6px 0 0;">'+esc(c.client)+'</h3></div></div>'
      +'<p style="font-style:italic;">"'+esc(c.quote)+'"</p>'
      +'<div class="stat" style="margin:14px 0;">'+statBlock(c.stat,c.statLabel)+'</div>'
      +'<button class="card-link case-toggle" data-i="'+i+'" style="background:none;border:none;padding:0;">Read the full story '+ico('arrow')+'</button>'
      +'<div class="accordion-panel case-panel" data-i="'+i+'"><div class="inner"><p>'+esc(c.body)+'</p></div></div>'
      +'</div>';
    }).join('')+'</div>'
  +'</div></section>'
  /* Projects an admin ticked "show in the public portfolio" on. That
     checkbox previously wrote a column nothing read, so it did nothing. */
  +(function(){
    var port = window.PORTFOLIO_DATA || [];
    if(!port.length) return '';
    return wave('a','var(--bg-alt)')
      +'<section class="section tone-b"><div class="container">'
        +'<div class="section-head center"><span class="eyebrow">Recently launched</span><h2>Live projects from the studio</h2></div>'
        +'<div class="grid grid-3">'+port.map(function(w){
          return '<div class="card tilt">'
            +'<div class="card-head">'+blobIcon('rocket','sm',true)+'<h3 style="font-size:1.05rem;">'+esc(w.client)+'</h3></div>'
            +(w.sector?'<span class="badge">'+esc(w.sector)+'</span>':'')
            +'<p style="font-size:.9rem;margin-top:10px;">'+esc(w.title)+'</p>'
            +(w.domain?'<a class="card-link" href="https://'+esc(String(w.domain).replace(/^https?:\/\//,''))+'" target="_blank" rel="noopener">'+esc(w.domain)+' '+ico('arrow')+'</a>':'')
          +'</div>';
        }).join('')+'</div>'
      +'</div></section>';
  })();
};

Pages['/process'] = function(){
  var steps = [
    {t:'Discover', icon:'compass', d:'A short call about your business and what you want people to find when they land on your site.', out:['What you actually need','A realistic timeline','A fixed price, before we start']},
    {t:'Design', icon:'spark', d:'We mock up your site or app so you can see it and ask for changes before anything is built.', out:['Clickable mockup','Your branding applied','Your sign-off before we build']},
    {t:'Build', icon:'code', d:'We build it, connect your domain, hosting, SSL and email, and test it on real phones.', out:['Working site or app','Domain, hosting & email set up','Tested on real devices']},
    {t:'Launch', icon:'rocket', d:'We publish it — to the web, the app stores, or both — and make sure nothing breaks on day one.', out:['Go-live checklist','App store submission, if needed','You get the keys, not just a login']},
    {t:'Grow', icon:'chart', d:'SEO, small updates and support keep new customers finding and using it after launch.', out:['Monthly SEO check-in','Small updates included','Support when you need it']}
  ];
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Process</span><h1 style="max-width:16ch;">A clear, five-stage process — with no surprises along the way.</h1>'
    +'<p class="lede">Every project — big or small — moves through the same five stages, so you always know what\'s next.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div style="display:flex;flex-direction:column;gap:0;">'
    +steps.map(function(s,i){
      return '<div class="flex" style="gap:28px;padding:30px 0;border-bottom:'+(i<steps.length-1?'1px solid var(--border-soft)':'none')+';align-items:flex-start;">'
        +'<div style="display:flex;flex-direction:column;align-items:center;gap:8px;flex:none;">'+blobIcon(s.icon,'lg')+'<span style="font-family:var(--font-display);font-weight:700;color:var(--ink-faint);">0'+(i+1)+'</span></div>'
        +'<div style="flex:1;"><h3>'+esc(s.t)+'</h3><p class="lede">'+esc(s.d)+'</p>'
        +'<div class="flex gap-8" style="flex-wrap:wrap;">'+s.out.map(function(o){return '<span class="badge">'+esc(o)+'</span>';}).join('')+'</div></div>'
      +'</div>';
    }).join('')
    +'</div>'
  +'</div></section>';
};

Pages['/about'] = function(){
  var S = window.SITE_SETTINGS || {};
  var values = window.VALUES_DATA || [
    {icon:'heart', t:'Plain language, always', d:'No jargon you need a developer to translate. If we can\'t explain it simply, we haven\'t understood it yet.'},
    {icon:'shield', t:'Nothing rented that should be owned', d:'Your domain, your site, your app — registered and built in your name, not locked to us.'},
    {icon:'users', t:'A real person replies', d:'Support that\'s an actual person who knows your project, not a ticket number.'},
    {icon:'spark', t:'Built to be found, not just to exist', d:'A website nobody can find isn\'t really online. SEO is part of the build, not an upsell.'}
  ];
  var team = window.TEAM_DATA || [
    {i:'MA', n:'Mara Aldous', r:'Founder & CEO'},
    {i:'DK', n:'Devon Kwan', r:'Head of Engineering'},
    {i:'RS', n:'Rhea Solano', r:'Head of Design'},
    {i:'JT', n:'Jonah Traeger', r:'VP Client Success'}
  ];
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">About</span><h1 style="max-width:16ch;">We started '+esc(S.siteName||'TECHBISS')+' because getting online shouldn\'t be this hard.</h1>'
    +'<p class="lede">'+esc(S.aboutStory||'TECHBISS began by building one shop owner a website over a weekend. Nine years later, the same conviction runs the platform: we build every project like it\'s the most important one — because to the person running the business, it is.')+'</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-4">'+[[S.stat5Value||'9',S.stat5Label||'Years in business'],[S.stat1Value||'1,900+',S.stat1Label||'Businesses & apps launched'],[S.stat2Value||'38',S.stat2Label||'Countries served'],[S.stat3Value||'4.9/5',S.stat3Label||'Customer rating']].map(function(s){return '<div class="card tilt text-center">'+statBlock(s[0],s[1])+'</div>';}).join('')+'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">What we hold onto</span><h2>Values that show up in the work</h2></div>'
    +'<div class="grid grid-4">'+values.map(function(v){return '<div class="card tilt"><div class="card-head">'+blobIcon(v.icon,'sm',true)+'<h3 style="font-size:1.05rem;">'+esc(v.t)+'</h3></div><p style="font-size:.88rem;">'+esc(v.d)+'</p></div>';}).join('')+'</div>'
  +'</div></section>'
  +wave('b','var(--bg)',true)
  +'<section class="section tone-a"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Leadership</span><h2>The people steering the studio</h2></div>'
    +'<div class="grid grid-4">'+team.map(function(t){return '<div class="card tilt text-center"><div class="avatar-blob" style="margin:0 auto 14px;">'+esc(t.i)+'</div><h3 style="font-size:1.05rem;">'+esc(t.n)+'</h3><p style="font-size:.85rem;">'+esc(t.r)+'</p></div>';}).join('')+'</div>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container text-center">'
    +'<div class="quote-blob"><p>"'+esc(S.careersQuote||'We\'re always looking for people who\'d rather ship a small business\'s first website than polish a slide deck.')+'"</p><cite>— Careers at '+esc(S.siteName||'TECHBISS')+'</cite></div>'
  +'</div></section>';
};

Pages['/resources'] = function(){
  var types=['All','Guides','Webinars','Reports','Changelog'];
  var res = [
    {t:'A beginner\'s guide to getting your business online', k:'Guides', icon:'compass', min:'7 min read'},
    {t:'Domain, hosting, SSL — what you actually need, explained', k:'Guides', icon:'globe', min:'6 min read'},
    {t:'Live: Getting your app approved on the first try', k:'Webinars', icon:'rocket', min:'35 min'},
    {t:'2026 State of Small Business SEO', k:'Reports', icon:'chart', min:'14 min read'},
    {t:'Bloom theme pack — v2.3 release notes', k:'Changelog', icon:'spark', min:'3 min read'},
    {t:'What AI agents can actually do for a small business in 2026', k:'Reports', icon:'bolt', min:'9 min read'},
    {t:'Live: Ready-made theme or custom build — how to choose', k:'Webinars', icon:'shield', min:'30 min'},
    {t:'App Store Launch Kit — v4.0 release notes', k:'Changelog', icon:'box', min:'4 min read'}
  ];
  return '<section class="hero hero-sub" style="padding-bottom:10px;"><div class="container">'
    +'<span class="eyebrow">Resources</span><h1 style="max-width:16ch;">Guides and resources to help you get online.</h1>'
    +'<p class="lede">Practical guides, recorded sessions and product updates from the team building this.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container">'
    +'<div class="chip-row" id="resChips">'+types.map(function(t,i){return '<button class="chip'+(i===0?' active':'')+'" data-cat="'+t+'">'+t+'</button>';}).join('')+'</div>'
    +'<div class="grid grid-3" id="resGrid">'+res.map(function(r){
      return '<div class="card tilt" data-cat="'+esc(r.k)+'"><div class="card-head">'+blobIcon(r.icon,'sm',true)+'<h3 style="font-size:1.08rem;">'+esc(r.t)+'</h3></div><p style="font-size:.85rem;">'+esc(r.min)+'</p><div class="flex items-center justify-between"><span class="badge">'+esc(r.k)+'</span></div></div>';
    }).join('')+'</div>'
  +'</div></section>'
  +wave('c','var(--bg-alt-2)')
  +'<section class="section tone-c"><div class="container text-center">'
    +'<h2 style="max-width:20ch;margin-inline:auto;">Get the next field note before anyone else</h2>'
    +'<form id="newsForm" class="flex" style="max-width:420px;margin:24px auto 0;gap:10px;" onsubmit="return false;">'
      +'<input type="email" id="newsEmail" required placeholder="you@company.com" aria-label="Email address" style="flex:1;min-width:0;padding:14px 18px;border-radius:var(--r-full);border:1.5px solid var(--border);background:var(--surface);outline:none;">'
      +'<div aria-hidden="true" style="position:absolute;left:-9999px;"><label>Website<input id="newsWebsite" type="text" tabindex="-1" autocomplete="off"></label></div>'
      +'<button class="btn btn-primary" type="submit">Subscribe</button>'
    +'</form><p id="newsMsg" class="badge success" style="display:none;margin-top:14px;">'+ico('check')+' You\'re on the list — welcome!</p>'
    +'<p id="newsError" class="badge danger" hidden style="margin-top:14px;"></p>'
  +'</div></section>';
};

Pages['/pricing'] = function(){
  var S = window.SITE_SETTINGS || {};
  var startPrice = S.pricingStartingPrice || 5;
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container text-center">'
    +'<span class="eyebrow">Pricing</span><h1 style="max-width:20ch;margin-inline:auto;">No fixed plans — every project is quoted for your business.</h1>'
    +'<p class="lede" style="max-width:46ch;margin-inline:auto;">A one-page site and a multi-screen app cost different amounts to build and maintain, so we price each project after a quick call, not off a menu.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container">'
    +'<div class="card tilt" style="max-width:560px;margin:0 auto;text-align:center;padding:44px 32px;">'
      +'<span class="eyebrow">Starting from</span>'
      +'<div style="font-family:var(--font-display);font-weight:800;font-size:3rem;margin:10px 0 6px;">'+fmtMoney(startPrice)+'</div>'
      +'<p style="font-size:.92rem;color:var(--ink-faint);margin-bottom:24px;">Final price depends on scope — a quick call gets you an exact number, in writing, before any work starts.</p>'
      +'<ul style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px;text-align:left;max-width:340px;margin-inline:auto;">'
        +['Domain, hosting & SSL included','No hidden add-ons','Quoted upfront, in writing','Ongoing care available after launch'].map(function(f){return '<li style="display:flex;gap:8px;font-size:.9rem;">'+ico('check')+'<span>'+esc(f)+'</span></li>';}).join('')
      +'</ul>'
      +'<a href="'+BP+'/contact" class="btn btn-primary btn-block magnetic">Get a free quote</a>'
    +'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Questions</span><h2>Pricing FAQ</h2></div>'
    +'<div id="priceFaq">'+(window.PRICING_FAQ_DATA || [
      ['How do you land on a final price?','We ask what you need, what you already have, and how complex it is, then send a fixed quote before any work starts — no surprise invoices.'],
      ['What if I already have a website?','We can take over hosting and support for an existing site, or rebuild it if it needs modern love — either way, nothing changes for your visitors during the switch.'],
      ['Can I start with a marketplace theme instead of a custom build?','Yes — a ready-made theme costs less than a custom build, and we\'ll still brand and launch it for you.'],
      ['Is ongoing support included?','Ongoing hosting, domain renewal and small updates can be added as a monthly care plan once your site or app is live — we\'ll go over options on the quote call.'],
      ['Do you offer nonprofit or small business discounts?','Yes, reach out through Contact and we\'ll tailor a plan for community and mission-driven organizations.']
    ]).map(function(f,i){
      return '<div class="accordion-item'+(i===0?' open':'')+'"><button aria-expanded="'+(i===0)+'">'+esc(f[0])+ico('arrow').replace('M13 6l6 6-6 6','m6 9 6 6 6-6').replace('M5 12h14','')+'</button><div class="accordion-panel"><div class="inner">'+esc(f[1])+'</div></div></div>';
    }).join('')+'</div>'
  +'</div></section>';
};

function legalParagraphs(text){
  return String(text||'').split(/\n\s*\n/).map(function(p){return p.trim();}).filter(Boolean)
    .map(function(p){return '<p>'+esc(p).replace(/\n/g,'<br>')+'</p>';}).join('');
}
Pages['/privacy'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Legal</span><h1 style="max-width:20ch;">Privacy Policy</h1>'
    +'<p class="lede">Last updated '+esc(S.privacyUpdatedAt||'')+'</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container">'
    +'<div class="card" style="max-width:760px;margin:0 auto;line-height:1.7;">'+legalParagraphs(S.privacyPolicy)+'</div>'
  +'</div></section>';
};
Pages['/terms'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Legal</span><h1 style="max-width:20ch;">Terms &amp; Conditions</h1>'
    +'<p class="lede">Last updated '+esc(S.termsUpdatedAt||'')+'</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container">'
    +'<div class="card" style="max-width:760px;margin:0 auto;line-height:1.7;">'+legalParagraphs(S.termsConditions)+'</div>'
  +'</div></section>';
};
Pages['/404'] = function(){
  return '<section class="hero"><div class="container text-center" style="padding:70px 20px;">'
    +'<span class="eyebrow">404</span>'
    +'<h1 style="max-width:18ch;margin-inline:auto;">We couldn\'t find that page.</h1>'
    +'<p class="lede" style="max-width:44ch;margin:14px auto 26px;">The link may be out of date, or the page may have moved. Here are the places people usually want.</p>'
    +'<div class="flex gap-12" style="justify-content:center;flex-wrap:wrap;">'
      +'<a href="'+BP+'/" class="btn btn-primary">Home '+ico('arrow')+'</a>'
      +'<a href="'+BP+'/services" class="btn btn-ghost">Services</a>'
      +'<a href="'+BP+'/marketplace" class="btn btn-ghost">Marketplace</a>'
      +'<a href="'+BP+'/contact" class="btn btn-ghost">Contact</a>'
    +'</div>'
  +'</div></section>';
};

Pages['/contact'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Contact</span><h1 style="max-width:18ch;">Tell us about your business, and we\'ll explain exactly what it takes to get you online.</h1>'
    +'<p class="lede">Most first calls happen within two business days.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container hero-grid" style="align-items:flex-start;">'
    +'<div class="card" id="contactCardWrap">'
      +'<form id="contactForm" onsubmit="return false;">'
        +'<div class="grid grid-2" style="gap:16px;">'
          +'<div class="field"><label for="cName">Full name</label><input id="cName" required placeholder="Jordan Lee"></div>'
          +'<div class="field"><label for="cEmail">Email</label><input id="cEmail" type="email" required placeholder="jordan@yourbusiness.com"></div>'
        +'</div>'
        +'<div class="field"><label for="cCompany">Business name</label><input id="cCompany" placeholder="Your business or project name"></div>'
        +'<div class="field"><label for="cType">What do you need?</label><select id="cType"><option>A new website</option><option>A new app</option><option>Both a website and an app</option><option>Domain, hosting & email setup</option><option>A ready-made theme, branded for me</option><option>Not sure yet</option></select></div>'
        +'<div class="field"><label for="cMsg">What are you trying to solve?</label><textarea id="cMsg" required placeholder="A sentence or two is plenty to start."></textarea></div>'
        +'<div aria-hidden="true" style="position:absolute;left:-9999px;"><label>Website<input id="cWebsite" type="text" tabindex="-1" autocomplete="off"></label></div>'
        +'<button class="btn btn-primary btn-block magnetic" type="submit">Send message '+ico('arrow')+'</button>'
        +'<p id="contactError" class="badge danger" hidden style="margin-top:14px;"></p>'
      +'</form>'
      +'<div id="contactSuccess" hidden style="text-align:center;padding:20px 0;">'+blobIcon('check','lg')+'<h3>Message sent</h3><p>Thanks — a real human will reply within two business days.</p></div>'
    +'</div>'
    +'<div style="display:flex;flex-direction:column;gap:20px;">'
      +'<div class="card"><div class="card-head">'+blobIcon('mail','sm',true)+'<h3 style="font-size:1.05rem;">Email</h3></div><p style="font-size:.9rem;">'+esc(S.contactEmail||'hello@techbiss.com')+'</p></div>'
      +'<div class="card"><div class="card-head">'+blobIcon('phone','sm',true)+'<h3 style="font-size:1.05rem;">Phone</h3></div><p style="font-size:.9rem;">'+esc(S.contactPhone||'+1 (415) 555-0148')+'</p></div>'
      +(S.whatsappNumber?'<div class="card"><div class="card-head">'+blobIcon('chat','sm',true)+'<h3 style="font-size:1.05rem;">WhatsApp</h3></div><p style="font-size:.9rem;margin-bottom:12px;">Message us any time — usually a same-day reply.</p><a href="https://wa.me/'+esc(S.whatsappNumber.replace(/\D+/g,''))+'" target="_blank" rel="noopener" class="btn btn-primary btn-block">Chat on WhatsApp</a></div>':'')
      +'<div class="card"><div class="card-head">'+blobIcon('globe','sm',true)+'<h3 style="font-size:1.05rem;">Studios</h3></div><p style="font-size:.9rem;">'+esc(S.studiosLocations||'San Francisco · Lisbon · Singapore')+'</p></div>'
    +'</div>'
  +'</div></section>';
};

Pages['/login'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero"><div class="container" style="max-width:480px;">'
    +'<div class="card">'
      +'<h3 style="margin-bottom:18px;">Sign in</h3>'
      +'<div id="authPanels">'
        +'<form id="emailStepForm" onsubmit="return false;">'
          +'<div class="field"><label for="liEmail">Email</label><input id="liEmail" type="email" required placeholder="you@company.com"></div>'
          +'<button class="btn btn-primary btn-block magnetic" type="submit">Send me a code</button>'
        +'</form>'
        +'<form id="codeStepForm" onsubmit="return false;" hidden>'
          +'<p class="lede" id="codeStepLede" style="margin-bottom:14px;"></p>'
          +'<div class="field"><label for="liCode">6-digit code</label><input id="liCode" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div>'
          +'<button class="btn btn-primary btn-block magnetic" type="submit">Verify &amp; sign in</button>'
          +'<button class="btn btn-ghost btn-block" type="button" id="resendCodeBtn" style="margin-top:10px;">Resend code</button>'
        +'</form>'
        +'<p id="loginMsg" class="badge success" style="display:none;margin-top:16px;">'+ico('check')+' Welcome back — redirecting…</p>'
        +'<p id="loginError" class="badge danger" hidden style="margin-top:16px;"></p>'
      +'</div>'
      +'<p style="font-size:.85rem;color:var(--ink-faint);margin-top:20px;">Client accounts are set up by our team once your project starts — <a href="'+BP+'/contact" style="color:var(--accent-1);font-weight:600;">get in touch</a> if you need access.</p>'
    +'</div>'
    +'<div class="card" style="background:var(--grad);color:#fff2ea;border:none;text-align:center;padding:36px;margin-top:20px;">'
      +'<div class="logo-mark" style="width:56px;height:56px;margin:0 auto 16px;"><svg viewBox="0 0 24 24" fill="none"><g><rect x="3" y="3" width="18" height="18" rx="6" fill="#fff2ea"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="url(#logoGrad)"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="url(#logoGrad)"/></g></svg></div>'
      +'<h3 style="color:#fff2ea;">Good to see you again.</h3><p style="color:rgba(255,242,234,.9);margin-bottom:0;">Your dashboard, marketplace orders and site updates are exactly where you left them — sign in to pick up right where you left off.</p>'
    +'</div>'
  +'</div></section>';
};


Pages['/dashboard'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:12px;"><div class="container">'
    +'<span class="eyebrow">Client dashboard</span><h1 style="max-width:20ch;">Welcome back, '+esc((AUTH_USER&&AUTH_USER.name)||'there')+'.</h1>'
    +'<p class="lede">Here\'s where things stand on your project.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container" style="max-width:820px;">'
    +'<div id="dashBody"><p style="color:var(--ink-faint);">Loading your project details…</p></div>'
  +'</div></section>';
};
function dashEmptyState(icon,title,body,cta){
  return '<div class="card" style="text-align:center;padding:44px 24px;">'+blobIcon(icon,'lg')+'<h3 style="margin:14px 0 4px;">'+esc(title)+'</h3><p class="lede" style="margin-bottom:'+(cta?'18px':'0')+';">'+esc(body)+'</p>'+(cta||'')+'</div>';
}
function dashExpiryBadge(label,dateStr){
  /* "2026-01-15" parses as UTC midnight, but new Date().toDateString()
     is local midnight — so in any timezone behind UTC this read one day
     lower than the same date shown in admin (which uses PHP strtotime).
     Compare both ends as plain calendar dates instead. */
  var parts = String(dateStr).slice(0,10).split('-');
  var target = Date.UTC(+parts[0], (+parts[1] || 1) - 1, +parts[2] || 1);
  var now = new Date();
  var today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
  var days = Math.round((target - today) / 86400000);
  var tone = days < 0 ? 'danger' : (days <= 30 ? 'warning' : '');
  return '<span class="badge '+tone+'">'+esc(label)+': '+(days<0?'overdue':days+'d')+'</span>';
}
function dashProjectCard(p){
  var tone = {Planning:'',  'In progress':'warning', Live:'success', 'On hold':'danger'}[p.status] || '';
  var expiries = [['Domain',p.domain_expires_at],['Hosting',p.hosting_expires_at],['SSL',p.ssl_expires_at],['Email',p.email_expires_at]]
    .filter(function(e){ return e[1]; }).map(function(e){ return dashExpiryBadge(e[0],e[1]); }).join(' ');
  return '<div class="card" style="margin-bottom:18px;">'
    +'<div class="flex justify-between items-center" style="margin-bottom:10px;flex-wrap:wrap;gap:8px;">'
      +'<h3 style="margin:0;">'+esc(p.title)+(p.domain?' <span style="color:var(--ink-faint);font-weight:400;font-size:.85rem;">— '+esc(p.domain)+'</span>':'')+'</h3>'
      +'<span class="badge '+tone+'">'+esc(p.status)+'</span>'
    +'</div>'
    +'<div class="progress-track" style="margin-bottom:12px;"><div class="progress-fill" style="width:'+(parseInt(p.progress_pct,10)||0)+'%;"></div></div>'
    +(expiries?'<div class="flex gap-8" style="flex-wrap:wrap;margin-bottom:'+(p.notes?'12px':'0')+';">'+expiries+'</div>':'')
    +(p.notes?'<p style="font-size:.9rem;color:var(--ink-soft);margin:0;">'+esc(p.notes)+'</p>':'')
    +projectTicketSection(p)
  +'</div>';
}
function projectTicketSection(p){
  if(p.open_ticket){
    return '<div class="badge warning" style="margin-top:12px;display:inline-flex;">'+ico('chat')+' Request open: '+esc(p.open_ticket.title)+'</div>';
  }
  return '<div style="margin-top:12px;">'
    +'<button type="button" class="btn btn-ghost btn-sm proj-ticket-toggle" data-pid="'+p.id+'">Need something on this project?</button>'
    +'<form class="proj-ticket-form" data-pid="'+p.id+'" onsubmit="return false;" hidden style="margin-top:12px;">'
      +'<div class="field"><label>What do you need?</label><input class="pt-title" required placeholder="e.g. Update the homepage photos"></div>'
      +'<div class="field"><label>Any details?</label><textarea class="pt-desc" placeholder="Optional"></textarea></div>'
      +'<button class="btn btn-primary btn-sm" type="submit">Send request</button>'
      +'<p class="pt-msg badge success" style="display:none;margin-top:10px;">'+ico('check')+' Sent — we\'ll follow up.</p>'
      +'<p class="pt-error badge danger" hidden style="margin-top:10px;"></p>'
    +'</form>'
  +'</div>';
}
function dashRequestFormHTML(businesses){
  var eligible = (businesses || []).filter(function(b){ return !b.open_request_ticket; });
  var pending = (businesses || []).filter(function(b){ return b.open_request_ticket; });
  if(!eligible.length){
    var names = pending.map(function(b){ return esc(b.name); }).join(', ');
    return '<div class="card" style="margin-top:18px;">'
      +'<div class="card-head">'+blobIcon('chat','sm',true)+'<h3>Request a new project</h3></div>'
      +'<p class="lede" style="margin-bottom:0;">You already have an open request'+(pending.length>1?' for '+names:'')+' — we\'ll be in touch soon.</p>'
    +'</div>';
  }
  var picker = (eligible.length > 1)
    ? '<div class="field"><label for="reqBiz">Which business is this for?</label><select id="reqBiz">'+eligible.map(function(b){ return '<option value="'+b.id+'">'+esc(b.name)+'</option>'; }).join('')+'</select></div>'
    : '<input type="hidden" id="reqBiz" value="'+eligible[0].id+'">';
  return '<div class="card" style="margin-top:18px;">'
    +'<div class="card-head">'+blobIcon('plus','sm',true)+'<h3>Request a new project</h3></div>'
    +'<p class="lede" style="margin-bottom:14px;">Need a new site, app, or something added to what you already have? Tell us here and we\'ll follow up.</p>'
    +'<form id="reqProjectForm" onsubmit="return false;">'
      +picker
      +'<div class="field"><label for="reqTitle">What do you need?</label><input id="reqTitle" required placeholder="e.g. A new online store"></div>'
      +'<div class="field"><label for="reqDesc">Any details?</label><textarea id="reqDesc" placeholder="Optional — anything that helps us scope it."></textarea></div>'
      +'<button class="btn btn-primary" type="submit">Send request</button>'
      +'<p id="reqMsg" class="badge success" style="display:none;margin-top:12px;">'+ico('check')+' Request sent — we\'ll be in touch.</p>'
      +'<p id="reqError" class="badge danger" hidden style="margin-top:12px;"></p>'
    +'</form>'
  +'</div>';
}
function wireRequestForm(){
  var f = $('#reqProjectForm');
  if(!f) return;
  f.addEventListener('submit', function(){
    var errEl = $('#reqError'), msgEl = $('#reqMsg');
    errEl.hidden = true;
    var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    var bizSel = $('#reqBiz');
    var payload = { title: $('#reqTitle').value, description: $('#reqDesc').value };
    if(bizSel) payload.business_id = bizSel.value;
    fetch(BP+'/api/project-request.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
      .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        btn.disabled = false;
        if(!res.ok){ errEl.textContent = res.data.error || 'Something went wrong — please try again.'; errEl.hidden = false; return; }
        f.reset();
        msgEl.style.display = 'inline-flex';
        setTimeout(function(){ msgEl.style.display='none'; }, 4000);
      })
      .catch(function(){
        btn.disabled = false;
        errEl.textContent = 'Could not reach the server — please try again.'; errEl.hidden = false;
      });
  });
}
function dashOrdersHTML(orders){
  if(!orders || !orders.length) return '';
  return '<h3 style="margin:28px 0 12px;">Your marketplace purchases</h3>'
    + orders.map(function(o){
      return '<div class="card" style="margin-bottom:12px;"><div class="flex justify-between items-center" style="flex-wrap:wrap;gap:10px;">'
        +'<div><b>'+esc(o.product_name)+'</b><br><span style="font-size:.82rem;color:var(--ink-faint);">Order '+esc(o.order_ref)+'</span></div>'
        +'<a href="'+esc(o.download_url)+'" class="btn btn-ghost btn-sm">Download again '+ico('arrow')+'</a>'
      +'</div></div>';
    }).join('');
}
function dashMarketplaceCTA(){
  return '<div class="card" style="margin-top:22px;"><div class="card-head">'+blobIcon('cart','sm',true)+'<h3>Looking for something ready-made?</h3></div><p style="font-size:.9rem;">Browse themes, templates and bundles you can buy and download right now.</p><a href="'+BP+'/marketplace" class="btn btn-primary">Browse the marketplace '+ico('arrow')+'</a></div>';
}
function wireDashboard(){
  var body = $('#dashBody');
  fetch(BP+'/api/dashboard.php').then(function(r){ return r.json(); }).then(function(data){
    var businesses = data.businesses || [];
    var orders = data.orders || [];
    if(!businesses.length){
      body.innerHTML = dashEmptyState('users','Your project isn\'t linked yet','Once our team sets up your account, your project status will show up here.','<a href="'+BP+'/contact" class="btn btn-primary">Contact us</a>')
        + dashOrdersHTML(orders) + dashMarketplaceCTA();
      return;
    }
    var anyProjects = businesses.some(function(b){ return b.projects && b.projects.length; });
    var html = '';
    businesses.forEach(function(b){
      if(businesses.length > 1){
        html += '<h3 style="margin:'+(html?'28px':'0')+' 0 12px;">'+esc(b.name)+'</h3>';
      }
      if(b.projects && b.projects.length){
        html += b.projects.map(dashProjectCard).join('');
      } else {
        html += dashEmptyState('rocket','No projects yet','This business\'s project will show up here once it kicks off.');
      }
    });
    if(anyProjects){
      html += '<div class="card" style="background:var(--grad);color:#fff2ea;border:none;"><div class="card-head">'+blobIcon('users','sm',false)+'<h3 style="color:#fff2ea;">Need something changed?</h3></div><p style="color:rgba(255,242,234,.9);">Reach out any time — we\'ll take it from there.</p><a href="'+BP+'/contact" class="btn" style="background:#fff2ea;color:var(--accent-1);">Contact us</a></div>';
    }
    html += dashOrdersHTML(orders) + dashMarketplaceCTA();
    html += dashRequestFormHTML(businesses);
    body.innerHTML = html;
    wireRequestForm();
    wireProjectTicketForms(body);
  }).catch(function(){
    body.innerHTML = '<p class="badge danger">Could not load your project details — please try again.</p>';
  });
}
function wireProjectTicketForms(scope){
  scope.addEventListener('click', function(e){
    var btn = e.target.closest('.proj-ticket-toggle');
    if(!btn) return;
    var form = scope.querySelector('.proj-ticket-form[data-pid="'+btn.dataset.pid+'"]');
    if(form){ form.hidden = !form.hidden; btn.style.display = form.hidden ? '' : 'none'; }
  });
  scope.addEventListener('submit', function(e){
    var form = e.target.closest('.proj-ticket-form');
    if(!form) return;
    e.preventDefault();
    var errEl = form.querySelector('.pt-error'), msgEl = form.querySelector('.pt-msg');
    errEl.hidden = true;
    var btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    var payload = {
      project_id: form.dataset.pid,
      title: form.querySelector('.pt-title').value,
      description: form.querySelector('.pt-desc').value
    };
    fetch(BP+'/api/project-ticket.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
      .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        btn.disabled = false;
        if(!res.ok){ errEl.textContent = res.data.error || 'Something went wrong — please try again.'; errEl.hidden = false; return; }
        msgEl.style.display = 'inline-flex';
        form.querySelector('.pt-title').disabled = true;
        form.querySelector('.pt-desc').disabled = true;
        btn.style.display = 'none';
      })
      .catch(function(){
        btn.disabled = false;
        errEl.textContent = 'Could not reach the server — please try again.'; errEl.hidden = false;
      });
  });
}
Pages['/account'] = function(){
  var u = AUTH_USER || {};
  return '<section class="hero hero-sub" style="padding-bottom:12px;"><div class="container">'
    +'<span class="eyebrow">Your account</span><h1 style="max-width:16ch;">Profile</h1>'
    +'<p class="lede">Manage your sign-in and account details.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container" style="max-width:640px;">'
    +'<div class="card" style="text-align:center;padding:32px 24px;">'
      +'<div class="avatar-blob" style="margin:0 auto 16px;">'+esc((u.name||'U').charAt(0).toUpperCase())+'</div>'
      +'<h3 style="margin-bottom:2px;">'+esc(u.name||'Your account')+'</h3>'
      +'<p style="color:var(--ink-faint);">'+esc(u.email||'')+'</p>'
    +'</div>'
    +'<div class="card" style="margin-top:16px;">'
      +'<a href="'+BP+'/dashboard" class="card-link" style="margin-bottom:14px;">'+ico('chart')+' View dashboard</a>'
      +'<a href="'+BP+'/contact" class="card-link" style="margin-bottom:14px;">'+ico('chat')+' Open a support ticket</a>'
      +'<button id="accountLogoutBtn" class="btn btn-ghost btn-block" type="button">'+ico('logout')+' Log out</button>'
    +'</div>'
    +'<div class="card" style="margin-top:16px;">'
      +'<div class="card-head">'+blobIcon('mail','sm',true)+'<h3>Change email</h3></div>'
      +'<div id="emailChangeStep1">'
        +'<p class="lede" style="margin-bottom:14px;">We\'ll verify your current email, then your new one, before making the change.</p>'
        +'<form id="emailChangeForm" onsubmit="return false;">'
          +'<div class="field"><label for="ecNewEmail">New email address</label><input id="ecNewEmail" type="email" required placeholder="you@newdomain.com"></div>'
          +'<button class="btn btn-primary" type="submit">Start email change</button>'
        +'</form>'
      +'</div>'
      +'<div id="emailChangeStep2" hidden>'
        +'<p class="lede" style="margin-bottom:14px;" id="ecStepLede"></p>'
        +'<form id="emailChangeCodeForm" onsubmit="return false;">'
          +'<div class="field"><label for="ecCode">6-digit code</label><input id="ecCode" inputmode="numeric" maxlength="6" placeholder="000000"></div>'
          +'<button class="btn btn-primary" type="submit">Verify</button>'
          +'<button class="btn btn-ghost" type="button" id="ecCancelBtn" style="margin-left:10px;">Cancel</button>'
        +'</form>'
      +'</div>'
      +'<p id="ecMsg" class="badge success" style="display:none;margin-top:14px;">'+ico('check')+' Email updated.</p>'
      +'<p id="ecError" class="badge danger" hidden style="margin-top:14px;"></p>'
    +'</div>'
  +'</div></section>';
};
/* The real staff admin panel is a separate, session-protected PHP app at /admin/ — not part of this client-side SPA. */

/* ===================================================================
   11. PAGE-SPECIFIC INTERACTION WIRING
=================================================================== */
function wireHome(){}
function wireWork(){
  $all('.case-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var i = btn.dataset.i;
      var panel = $('.case-panel[data-i="'+i+'"]');
      var open = panel.style.maxHeight && panel.style.maxHeight!=='0px';
      panel.style.maxHeight = open ? '0px' : panel.scrollHeight+'px';
      btn.firstChild.textContent = open ? 'Read the full story ' : 'Hide the full story ';
    });
  });
}
function wireMarketplace(){
  var grid = $('#mktGrid');
  function draw(cat, q){
    var items = PRODUCTS.filter(function(p){
      var okCat = (cat==='All'||!cat) || p.cat===cat;
      var okQ = !q || p.name.toLowerCase().indexOf(q.toLowerCase())>-1 || p.tagline.toLowerCase().indexOf(q.toLowerCase())>-1;
      return okCat && okQ;
    });
    grid.innerHTML = items.map(function(p){
      return '<div class="flip-card"><div class="flip-inner">'
        +'<div class="flip-face flip-front">'
          +'<div class="card-head" style="margin-bottom:12px;">'+blobIcon(p.icon,'sm')+'<h3 style="font-size:1.05rem;">'+esc(p.name)+'</h3></div>'
          +'<p style="font-size:.85rem;">'+esc(p.tagline)+'</p>'
          +'<div class="pf-preview">'+(p.image?'<img src="'+BP+'/'+esc(p.image)+'" alt="" style="width:100%;height:100%;object-fit:cover;">':ico(p.icon))+'</div>'
          +'<div class="pf-tags" style="justify-content:space-between;align-items:center;"><div style="display:flex;gap:6px;flex-wrap:wrap;"><span class="badge">'+esc(p.cat)+'</span>'+(p.rating>=4.9?'<span class="badge grad">Popular</span>':'')+'</div><span class="pf-rating">'+ico('star')+' '+esc(p.rating)+'</span></div>'
          +'<div class="pf-foot"><span class="pf-price">'+fmtMoney(p.price)+(p.pricing_type==='fixed'?'':'<span> /mo</span>')+'</span><span class="pf-hint">Flip to preview '+ico('refresh')+'</span></div>'
        +'</div>'
        +'<div class="flip-face flip-back">'
          +'<div><h3 style="color:#fff2ea;font-size:1.05rem;">'+esc(p.name)+'</h3>'
          +'<p style="font-size:.85rem;">'+esc(p.desc)+'</p>'
          +'<div class="fb-specs">'+p.specs.slice(0,3).map(function(s){return '<div class="flex items-center gap-8">'+ico('check')+'<span>'+esc(s)+'</span></div>';}).join('')+'</div></div>'
          +'<a href="'+BP+'/marketplace/detail/'+esc(p.id)+'" class="btn" style="background:#fff2ea;color:var(--accent-1);">View details '+ico('arrow')+'</a>'
        +'</div>'
      +'</div>'
      +'<button class="flip-peek" aria-label="Toggle preview">'+ico('refresh')+'</button>'
      +'</div>';
    }).join('') || '<p>No products match that search yet — try another term.</p>';
    $('#mktCount').textContent = items.length + (items.length===1 ? ' product' : ' products');
    $all('.flip-peek', grid).forEach(function(btn){
      btn.addEventListener('click', function(){ btn.closest('.flip-card').classList.toggle('flipped'); });
    });
  }
  draw('All','');
  $('#mktChips').addEventListener('click', function(e){
    var b = e.target.closest('.chip'); if(!b) return;
    $all('.chip',$('#mktChips')).forEach(function(c){c.classList.remove('active');}); b.classList.add('active');
    draw(b.dataset.cat, $('#mktSearch').value);
  });
  $('#mktSearch').addEventListener('input', function(){
    var active = $('#mktChips .chip.active');
    draw(active?active.dataset.cat:'All', this.value);
  });
}
function wireResources(){
  var chips=$('#resChips'), grid=$('#resGrid');
  chips.addEventListener('click', function(e){
    var b=e.target.closest('.chip'); if(!b) return;
    $all('.chip',chips).forEach(function(c){c.classList.remove('active');}); b.classList.add('active');
    $all('.card',grid).forEach(function(card){
      card.style.display = (b.dataset.cat==='All' || card.dataset.cat===b.dataset.cat) ? '' : 'none';
    });
  });
  var form = $('#newsForm');
  if(form){
    form.addEventListener('submit', function(){
      var err = $('#newsError'); err.hidden = true;
      var email = $('#newsEmail').value;
      var btn = form.querySelector('button[type=submit]'); btn.disabled = true;
      fetch(BP+'/api/newsletter.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email:email, website: ($('#newsWebsite')||{}).value || ''}) })
        .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
        .then(function(res){
          if(!res.ok){ btn.disabled=false; err.textContent = res.data.error || 'Something went wrong — please try again.'; err.hidden = false; return; }
          $('#newsMsg').style.display='inline-flex';
          form.style.opacity=.5;
          form.querySelectorAll('input,button').forEach(function(i){i.disabled=true;});
        })
        .catch(function(){ btn.disabled=false; err.textContent='Could not reach the server — please try again.'; err.hidden=false; });
    });
  }
}
function wirePricing(){
  $all('.accordion-item').forEach(function(item){
    item.querySelector('button').addEventListener('click', function(){
      var wasOpen = item.classList.contains('open');
      $all('.accordion-item').forEach(function(i){ i.classList.remove('open'); i.querySelector('button').setAttribute('aria-expanded','false'); });
      if(!wasOpen){ item.classList.add('open'); item.querySelector('button').setAttribute('aria-expanded','true'); }
    });
  });
}
function wireContact(){
  var form = $('#contactForm');
  form.addEventListener('submit', function(){
    var err = $('#contactError'); err.hidden = true;
    var btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    fetch(BP+'/api/contact.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        name: $('#cName').value, email: $('#cEmail').value,
        company: $('#cCompany').value, need: $('#cType').value, message: $('#cMsg').value,
        website: ($('#cWebsite') || {}).value || ''
      })
    }).then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        btn.disabled = false;
        if(!res.ok){ err.textContent = res.data.error || 'Something went wrong — please try again.'; err.hidden = false; return; }
        form.hidden = true;
        $('#contactSuccess').hidden = false;
        var r2 = $('#contactCardWrap').getBoundingClientRect();
        confettiBurst(r2.left+r2.width/2, r2.top+30);
      })
      .catch(function(){
        btn.disabled = false;
        err.textContent = 'Could not reach the server — please try again.'; err.hidden = false;
      });
  });
}
function wireLogin(){
  var emailForm = $('#emailStepForm'), codeForm = $('#codeStepForm');
  var errEl = $('#loginError'), msgEl = $('#loginMsg');
  var currentEmail = '';

  function showError(text){ errEl.textContent = text; errEl.hidden = false; }

  function completeLogin(user){
    AUTH_USER = user || AUTH_USER;
    msgEl.style.display = 'inline-flex';
    setTimeout(function(){ navigate('/dashboard'); }, 700);
  }

  function requestCode(email, silent){
    return fetch(BP+'/api/otp-request.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email: email }) })
      .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        if(!res.ok){ if(!silent) showError(res.data.error || 'Something went wrong — please try again.'); return false; }
        return true;
      })
      .catch(function(){ if(!silent) showError('Could not reach the server — please try again.'); return false; });
  }

  emailForm.addEventListener('submit', function(){
    errEl.hidden = true;
    var email = $('#liEmail').value.trim();
    if(!email){ showError('Enter your email address.'); return; }
    var btn = emailForm.querySelector('button[type=submit]'); btn.disabled = true;
    requestCode(email).then(function(ok){
      btn.disabled = false;
      if(!ok) return;
      currentEmail = email;
      $('#codeStepLede').textContent = 'We sent a 6-digit code to '+email+'. It\'s good for 10 minutes.';
      emailForm.hidden = true;
      codeForm.hidden = false;
      $('#liCode').focus();
    });
  });

  codeForm.addEventListener('submit', function(){
    errEl.hidden = true;
    var code = $('#liCode').value.trim();
    if(!code){ showError('Enter the code we sent you.'); return; }
    var btn = codeForm.querySelector('button[type=submit]'); btn.disabled = true;
    fetch(BP+'/api/otp-verify.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email: currentEmail, code: code }) })
      .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        btn.disabled = false;
        if(!res.ok){ showError(res.data.error || 'That code is invalid or has expired.'); return; }
        completeLogin(res.data.user);
      })
      .catch(function(){
        btn.disabled = false;
        showError('Could not reach the server — please try again.');
      });
  });

  var resendBtn = $('#resendCodeBtn');
  resendBtn.addEventListener('click', function(){
    errEl.hidden = true;
    resendBtn.disabled = true;
    requestCode(currentEmail).then(function(ok){
      if(ok){ resendBtn.textContent = 'Code sent'; setTimeout(function(){ resendBtn.textContent = 'Resend code'; resendBtn.disabled = false; }, 30000); }
      else { resendBtn.disabled = false; }
    });
  });

  // Magic-link: /login?token=... verifies automatically and signs in.
  var params = new URLSearchParams(location.search);
  var token = params.get('token');
  if(token){
    emailForm.hidden = true;
    $('#codeStepLede').textContent = '';
    codeForm.hidden = true;
    fetch(BP+'/api/otp-verify.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ token: token }) })
      .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
      .then(function(res){
        if(!res.ok){
          emailForm.hidden = false;
          showError(res.data.error || 'That link is invalid or has expired — request a new code below.');
          return;
        }
        completeLogin(res.data.user);
      })
      .catch(function(){ emailForm.hidden = false; showError('Could not reach the server — please try again.'); });
  }
}

function wireProductDetail(id){
  var p = PRODUCTS.filter(function(x){return x.id===id;})[0];
  var tabs = $('#pdTabs'), panels = $('#pdPanels');
  if(!p || !tabs || !panels) return;
  var state = { order:null, downloadUrl:null };
  function renderOverview(){
    return '<div class="grid grid-2"><div><h3>Overview</h3><p>'+esc(p.desc)+'</p>'
      +'<h3 style="margin-top:22px;">What\'s included</h3><ul style="display:flex;flex-direction:column;gap:8px;">'+p.specs.map(function(s){return '<li style="display:flex;gap:8px;font-size:.9rem;">'+ico('check')+'<span>'+esc(s)+'</span></li>';}).join('')+'</ul></div>'
      +(p.image
        ? '<div class="hero-visual" style="aspect-ratio:1/1;overflow:hidden;border-radius:20px;"><img src="'+BP+'/'+esc(p.image)+'" alt="" style="width:100%;height:100%;object-fit:cover;"></div>'
        : '<div class="hero-visual" style="aspect-ratio:1/1;"><svg viewBox="0 0 200 200" style="width:80%;height:80%;"><path fill="url(#pvGrad)" d="M46,-52C58,-42,64,-24,64,-6C64,12,58,28,46,40C34,52,16,60,-3,63C-22,66,-44,64,-56,52C-68,40,-70,18,-67,-3C-64,-24,-56,-46,-40,-58C-24,-70,-2,-72,17,-68C36,-64,34,-62,46,-52Z" transform="translate(100 100)"/><defs><linearGradient id="pvGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" style="stop-color:var(--accent-1)"/><stop offset="100%" style="stop-color:var(--accent-2)"/></linearGradient></defs></svg></div>')
      +'</div><button class="btn btn-primary magnetic" style="margin-top:24px;" data-goto="buy">'
      +((window.SITE_SETTINGS||{}).paymentsEnabled ? 'Buy for '+fmtMoney(p.price)+(p.pricing_type==='fixed'?'':'/mo') : 'Get this for '+fmtMoney(p.price)+(p.pricing_type==='fixed'?'':'/mo'))
      +' '+ico('arrow')+'</button>';
  }
  function renderBuy(){
    var S = window.SITE_SETTINGS || {};
    /* No payment processor is connected, so unless an admin has knowingly
       switched checkout on, send buyers to Contact rather than handing out
       the paid file for free. */
    if(!S.paymentsEnabled){
      return '<div class="text-center" style="padding:20px 0;">'+blobIcon('mail','lg')+'<h3>Buy through us directly</h3><p style="max-width:42ch;margin:0 auto;">Online checkout isn\'t open yet. Tell us which product you want and we\'ll get it to you — usually the same day.</p>'
        +'<a href="'+BP+'/contact" class="btn btn-primary magnetic" style="margin-top:14px;">Contact us '+ico('arrow')+'</a></div>';
    }
    if(!p.hasDownload){
      return '<div class="text-center" style="padding:20px 0;">'+blobIcon('mail','lg')+'<h3>Not available for instant purchase yet</h3><p style="max-width:40ch;margin:0 auto;">This product isn\'t set up for self-checkout right now — reach out and we\'ll sort it out directly.</p>'
        +'<a href="'+BP+'/contact" class="btn btn-primary magnetic" style="margin-top:14px;">Contact us '+ico('arrow')+'</a></div>';
    }
    if(state.order){
      return '<div class="text-center" style="padding:20px 0;">'+blobIcon('check','lg')+'<h3>You\'re all set, thank you.</h3><p>Order <b>'+esc(state.order)+'</b> — we\'ve also emailed your download link'+(AUTH_USER?'':' and set up your account so you can sign back in with this email any time')+'.</p>'
        +'<div class="flex gap-12" style="justify-content:center;flex-wrap:wrap;margin-top:14px;"><a href="'+esc(state.downloadUrl)+'" class="btn btn-primary magnetic">Download now '+ico('arrow')+'</a>'+(AUTH_USER?'<a href="'+BP+'/dashboard" class="btn btn-ghost">Go to dashboard</a>':'')+'</div></div>';
    }
    return '<div class="hero-grid" style="align-items:flex-start;">'
      +'<div><h3>Review your order</h3>'
      +'<div class="table-wrap"><table><tbody>'
        +'<tr><td>'+esc(p.name)+'</td><td>'+fmtMoney(p.price)+(p.pricing_type==='fixed'?'':'/mo')+'</td></tr>'
      +'</tbody></table></div>'
      +'<div class="field" style="margin-top:20px;"><label>Card details (demo only)</label><input placeholder="4242 4242 4242 4242" disabled></div>'
      +(AUTH_USER?'':'<div class="grid grid-2" style="gap:16px;"><div class="field"><label for="pdName">Full name</label><input id="pdName" required placeholder="Jordan Lee"></div><div class="field"><label for="pdEmail">Email</label><input id="pdEmail" type="email" required placeholder="jordan@yourbusiness.com"></div></div>')
      +'</div>'
      +'<div class="card"><h3>Ready when you are</h3><p style="font-size:.85rem;">No payment is actually processed in this concept, but your download and account are real.</p>'
      +'<p id="pdError" class="badge danger" hidden style="margin-bottom:14px;"></p>'
      +'<button class="btn btn-primary btn-block magnetic" id="confirmPurchase">Confirm purchase '+ico('arrow')+'</button></div>'
      +'</div>';
  }
  var renderers = {overview:renderOverview, buy:renderBuy};
  function showTab(tab){
    $all('button',tabs).forEach(function(b){ b.classList.toggle('active', b.dataset.tab===tab); });
    panels.innerHTML = renderers[tab]();
    wirePanel(tab);
    attachTilt(panels);
  }
  function wirePanel(tab){
    $all('[data-goto]', panels).forEach(function(b){ b.addEventListener('click', function(){ showTab(b.dataset.goto); }); });
    if(tab==='buy'){
      var btn = $('#confirmPurchase');
      if(btn) btn.addEventListener('click', function(){
        var errEl = $('#pdError');
        /* The price is read server-side from the product row — sending a
           "total" from here let any caller name their own. */
        var payload = { product_id: p.id };
        if(!AUTH_USER){
          payload.name = $('#pdName').value;
          payload.email = $('#pdEmail').value;
        }
        errEl.hidden = true;
        btn.disabled = true;
        fetch(BP+'/api/purchase.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
          .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
          .then(function(res){
            btn.disabled = false;
            if(!res.ok){
              errEl.textContent = res.data.error || 'Something went wrong — please try again.';
              errEl.hidden = false;
              if(res.data.needs_login){
                errEl.innerHTML = esc(res.data.error) + ' <a href="'+BP+'/login" style="color:inherit;text-decoration:underline;">Sign in</a>';
              }
              return;
            }
            state.order = res.data.order_ref;
            state.downloadUrl = res.data.download_url;
            showTab('buy');
            var r = panels.getBoundingClientRect();
            confettiBurst(r.left+r.width/2, r.top+40);
          })
          .catch(function(){
            btn.disabled = false;
            errEl.textContent = 'Could not reach the server — please try again.'; errEl.hidden = false;
          });
      });
    }
  }
  tabs.addEventListener('click', function(e){ var b=e.target.closest('button'); if(b) showTab(b.dataset.tab); });
  showTab('overview');
}


/* ===================================================================
   12. ROUTER
=================================================================== */
function currentPathname(){
  var p = location.pathname;
  if(BP && p.indexOf(BP)===0){ p = p.slice(BP.length); }
  return p || '/';
}
function currentBasePath(){
  var h = currentPathname();
  if(h.indexOf('/marketplace/detail')===0) return '/marketplace';
  return h;
}
function parseRoute(){
  var h = currentPathname();
  var m = h.match(/^\/marketplace\/detail\/(.+)$/);
  if(m) return {key:'/marketplace/detail', param:m[1], nav:'/marketplace'};
  /* Unknown paths used to silently render the homepage. They now get a
     real not-found page (index.php sends the 404 status to match). */
  return {key: Pages[h] ? h : '/404', param:null, nav:h};
}
function navigate(path){
  if(currentPathname() === path) return;
  history.pushState(null, '', BP+path);
  runTransition();
}
var wipe = $('#routeWipe');
var AUTH_USER = null;
function syncDashboardNavLink(){
  var inDesktop = navLinksEl.querySelector('a[data-path="/dashboard"]');
  var inMobile = mobileNav.querySelector('a[data-path="/dashboard"]');
  if(AUTH_USER){
    if(!inDesktop){
      var a = document.createElement('a');
      a.href = BP+'/dashboard'; a.className='nav-link'; a.textContent='Dashboard'; a.dataset.path='/dashboard';
      navLinksEl.insertBefore(a, navLinksEl.firstChild.nextSibling);
    }
    if(!inMobile){
      var m = document.createElement('a');
      m.href = BP+'/dashboard'; m.dataset.path='/dashboard';
      m.innerHTML = 'Dashboard'+ico('arrow');
      mobileNav.insertBefore(m, mobileNav.firstChild);
    }
  } else {
    if(inDesktop) inDesktop.remove();
    if(inMobile) inMobile.remove();
  }
}
function refreshAuth(){
  return fetch(BP+'/api/me.php').then(function(r){ return r.ok ? r.json() : null; })
    .then(function(data){ AUTH_USER = (data && data.user) || null; return AUTH_USER; })
    .catch(function(){ AUTH_USER = null; return null; });
}
function doRender(){
  var r = parseRoute();
  if((r.key==='/dashboard'||r.key==='/account') && !AUTH_USER){ navigate('/login'); return; }
  var view = $('#view');
  view.innerHTML = Pages[r.key](r.param);
  window.scrollTo(0,0);
  attachTilt(view);
  var afterMap = {
    '/': wireHome, '/work': wireWork, '/marketplace': wireMarketplace,
    '/resources': wireResources, '/pricing': wirePricing, '/contact': wireContact,
    '/login': wireLogin, '/account': wireAccount, '/dashboard': wireDashboard
  };
  if(r.key==='/marketplace/detail'){ wireProductDetail(r.param); }
  else if(afterMap[r.key]){ afterMap[r.key](); }
  moveNavBlob(currentNavLink());
  syncDashboardNavLink();
  var dockHome = $('.dock-item[data-path="/"], .dock-item[data-path="/dashboard"]');
  if(dockHome){
    if(AUTH_USER){
      dockHome.querySelector('svg').outerHTML = ico('monitor');
      dockHome.querySelector('span').textContent = 'Dashboard';
      dockHome.setAttribute('href', BP+'/dashboard');
      dockHome.dataset.path = '/dashboard';
    } else {
      dockHome.querySelector('svg').outerHTML = '<svg viewBox="0 0 24 24" fill="none"><path d="M4 11.5 12 4l8 7.5M6 10v9.5a1 1 0 0 0 1 1h3.5V15a1.5 1.5 0 0 1 1.5-1.5v0A1.5 1.5 0 0 1 13.5 15v5.5H17a1 1 0 0 0 1-1V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      dockHome.querySelector('span').textContent = 'Home';
      dockHome.setAttribute('href', BP+'/');
      dockHome.dataset.path = '/';
    }
  }
  var loginBtn = $('#navLoginBtn');
  var dockLogin = $('.dock-item[data-path="/login"], .dock-item[data-path="/account"]');
  if(AUTH_USER){
    loginBtn.textContent = 'Profile';
    loginBtn.setAttribute('href',BP+'/account');
    loginBtn.style.display = (r.nav==='/account') ? 'none' : '';
    loginBtn.onclick = null;
    if(dockLogin){
      dockLogin.querySelector('span').textContent = 'Profile';
      dockLogin.setAttribute('href',BP+'/account');
      dockLogin.dataset.path = '/account';
      dockLogin.onclick = null;
    }
  } else {
    loginBtn.textContent = 'Log in';
    loginBtn.setAttribute('href',BP+'/login');
    loginBtn.onclick = null;
    if(r.nav==='/dashboard'||r.nav==='/login'){ loginBtn.style.display='none'; } else { loginBtn.style.display=''; }
    if(dockLogin){
      dockLogin.querySelector('span').textContent = 'Log in';
      dockLogin.setAttribute('href',BP+'/login');
      dockLogin.dataset.path = '/login';
      dockLogin.onclick = null;
    }
  }
  $all('.nav-link, #mobileNav a, .dock-item[data-path]').forEach(function(a){ a.classList.toggle('active', a.dataset.path===r.nav); });
}
function doLogout(){
  return fetch(BP+'/api/logout.php',{method:'POST'}).then(function(){ AUTH_USER=null; navigate('/'); });
}
function wireAccount(){
  var btn = $('#accountLogoutBtn');
  if(btn){ btn.onclick = function(e){ e.preventDefault(); doLogout(); }; }

  var step1 = $('#emailChangeStep1'), step2 = $('#emailChangeStep2');
  var errEl = $('#ecError'), msgEl = $('#ecMsg');
  var stage = null; // 'old' | 'new'

  function ecShowError(text){ errEl.textContent = text; errEl.hidden = false; }
  function ecPost(action, payload){
    return fetch(BP+'/api/account-email-change.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    }).then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); });
  }

  var changeForm = $('#emailChangeForm');
  if(changeForm){
    changeForm.addEventListener('submit', function(){
      errEl.hidden = true;
      var email = $('#ecNewEmail').value.trim();
      var btn2 = changeForm.querySelector('button[type=submit]'); btn2.disabled = true;
      ecPost('request', { new_email: email }).then(function(res){
        btn2.disabled = false;
        if(!res.ok){ ecShowError(res.data.error || 'Something went wrong — please try again.'); return; }
        stage = 'old';
        $('#ecStepLede').textContent = 'We sent a code to your current email ('+esc((AUTH_USER&&AUTH_USER.email)||'')+') to confirm it\'s you.';
        step1.hidden = true; step2.hidden = false;
      }).catch(function(){ btn2.disabled = false; ecShowError('Could not reach the server — please try again.'); });
    });
  }

  var codeForm = $('#emailChangeCodeForm');
  if(codeForm){
    codeForm.addEventListener('submit', function(){
      errEl.hidden = true;
      var code = $('#ecCode').value.trim();
      var btn2 = codeForm.querySelector('button[type=submit]'); btn2.disabled = true;
      var action = stage === 'old' ? 'verify_old' : 'verify_new';
      ecPost(action, { code: code }).then(function(res){
        btn2.disabled = false;
        if(!res.ok){ ecShowError(res.data.error || 'That code is invalid or has expired.'); return; }
        if(stage === 'old'){
          stage = 'new';
          $('#ecStepLede').textContent = 'Now enter the code we just sent to your new email address.';
          $('#ecCode').value = '';
        } else {
          if(res.data.email && AUTH_USER){ AUTH_USER.email = res.data.email; }
          step2.hidden = true;
          msgEl.style.display = 'inline-flex';
        }
      }).catch(function(){ btn2.disabled = false; ecShowError('Could not reach the server — please try again.'); });
    });
  }

  var cancelBtn = $('#ecCancelBtn');
  if(cancelBtn){
    cancelBtn.addEventListener('click', function(){
      stage = null;
      errEl.hidden = true;
      step2.hidden = true; step1.hidden = false;
      $('#ecCode').value = '';
    });
  }
}
var transitioning=false;
function resetTransition(){
  transitioning=false;
  wipe.classList.remove('covering');
}
/* doRender() used to run inside a bare `catch(e){}`, so any exception in
   any page renderer produced a blank or frozen page with nothing logged
   anywhere. Report it, and show the visitor something they can act on. */
function safeRender(){
  try{ doRender(); }
  catch(err){
    if(window.console && console.error) console.error('Page render failed:', err);
    var v = $('#view');
    if(v){
      v.innerHTML = '<section class="section"><div class="container text-center" style="padding:60px 20px;">'
        +'<h1 style="max-width:20ch;margin-inline:auto;">Something went wrong on this page.</h1>'
        +'<p class="lede" style="max-width:44ch;margin:12px auto 22px;">Sorry — please try again, or head back to the homepage.</p>'
        +'<a href="'+BP+'/" class="btn btn-primary">Back to home '+ico('arrow')+'</a>'
      +'</div></section>';
    }
  }
}

function runTransition(){
  if(transitioning) return;
  transitioning=true;
  var wipeEnabled = motionOK && !(window.SITE_SETTINGS && window.SITE_SETTINGS.pageTransitionEnabled === false);
  if(!wipeEnabled){ safeRender(); transitioning=false; return; }
  wipe.classList.add('covering');
  var safety = setTimeout(resetTransition, 1500);
  setTimeout(function(){
    safeRender();
    {
      clearTimeout(safety);
      requestAnimationFrame(function(){
        wipe.classList.remove('covering');
        setTimeout(function(){ transitioning=false; }, 280);
      });
    }
  }, 270);
}
window.addEventListener('popstate', runTransition);
window.addEventListener('resize', function(){ moveNavBlob(currentNavLink()); });
window.addEventListener('pageshow', function(e){ if(e.persisted) resetTransition(); });
document.addEventListener('visibilitychange', function(){ if(!document.hidden && transitioning) resetTransition(); });

/* Intercept clicks on internal links (plain "/path" hrefs, e.g. from
   router.forEach-built nav or the '+BP+' links baked into page markup)
   and route them through pushState instead of a full page load. */
document.addEventListener('click', function(e){
  if(e.defaultPrevented || e.button!==0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  var a = e.target.closest('a');
  if(!a) return;
  var href = a.getAttribute('href');
  if(!href || href.charAt(0)!=='/' || a.target==='_blank' || a.hasAttribute('download')) return;
  var path = (BP && href.indexOf(BP)===0) ? href.slice(BP.length) : href;
  if(path==='') path='/';
  e.preventDefault();
  navigate(path);
});

/* One-time migration for old bookmarked/shared hash links (#/services)
   so they land on the clean equivalent (/services) instead. */
(function migrateHashLink(){
  var h = location.hash;
  if(h.indexOf('#/')===0){
    history.replaceState(null, '', BP+h.slice(1));
  }
})();

refreshAuth().then(doRender);

})();
