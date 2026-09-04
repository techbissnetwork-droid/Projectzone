(function(){
"use strict";

/* ===================================================================
   0. UTIL / MOTION PREFS
=================================================================== */
var reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
var motionOK = !reduceQuery.matches;
reduceQuery.addEventListener && reduceQuery.addEventListener('change', function(e){ motionOK = !e.matches; });
function $(sel,ctx){ return (ctx||document).querySelector(sel); }
function $all(sel,ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
function el(html){ var d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstElementChild; }
function fmtMoney(n){ return '$' + n.toLocaleString('en-US'); }

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
  var seen=false;
  try{ seen = sessionStorage.getItem('bloomIntro')==='1'; }catch(e){}
  var s = $('#splash');
  if(seen){ s.remove(); return; }
  function dismiss(){
    s.classList.add('hide');
    try{ sessionStorage.setItem('bloomIntro','1'); }catch(e){}
    document.removeEventListener('click', dismiss);
    document.removeEventListener('keydown', dismiss);
    setTimeout(function(){ s.remove(); }, 550);
  }
  document.addEventListener('click', dismiss);
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
  a.href = '#'+r.path; a.className='nav-link'; a.textContent=r.label; a.dataset.path=r.path;
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
ROUTES.forEach(function(r){
  var a = document.createElement('a');
  a.href='#'+r.path; a.dataset.path=r.path;
  a.innerHTML = r.label + ico('arrow');
  mobileNav.appendChild(a);
});
/* "Log in" is intentionally left out of this menu — the bottom dock (visible
   at the same widths this menu opens from) already has its own dedicated
   Log in icon, so listing it again here would just duplicate it. */
var burger=$('#navBurger'), sheet=$('#mobileSheet'), backdrop=$('#sheetBackdrop'), dockMenuBtn=$('#dockMenuBtn');
function openSheet(o){
  sheet.classList.toggle('open',o); backdrop.classList.toggle('open',o);
  burger.setAttribute('aria-expanded', String(o));
  dockMenuBtn.setAttribute('aria-expanded', String(o));
  document.body.style.overflow = o ? 'hidden':'';
}
burger.addEventListener('click', function(){ openSheet(!sheet.classList.contains('open')); });
dockMenuBtn.addEventListener('click', function(){ openSheet(!sheet.classList.contains('open')); });
backdrop.addEventListener('click', function(){ openSheet(false); });
mobileNav.addEventListener('click', function(){ openSheet(false); });

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
var SERVICES = [
  {icon:'monitor', name:'Website Design & Development', blurb:'A site built around your business, not squeezed into a generic template.', bullets:['Custom design & copy','Fast, mobile-friendly pages','Built to grow as you do']},
  {icon:'code', name:'App Development', blurb:'iOS and Android apps built from your idea, not a boilerplate.', bullets:['iOS & Android, one build','Real designs before we code','Built to pass App Store review']},
  {icon:'globe', name:'Domain, Hosting & Email', blurb:'The unglamorous stuff, set up right the first time and never left to lapse.', bullets:['Domain registration & DNS','Fast hosting with SSL included','Business email on your domain']},
  {icon:'rocket', name:'App Store & Play Store Publishing', blurb:'We handle listings, screenshots and the entire review process.', bullets:['Store listing & screenshots','Submission & review handled','Updates after you launch']},
  {icon:'chart', name:'SEO & Search Ranking', blurb:'So being online actually means being found.', bullets:['On-page & technical SEO','Google Maps & local search','Plain-language ranking reports']},
  {icon:'cart', name:'Ready-Made Themes & Templates', blurb:'Buy a theme, brand it as your own, and launch in days.', bullets:['Fully brandable, no lock-in','Your logo, colors & content','Same support as a custom build']}
];
var SOLUTIONS = [
  {icon:'cart', name:'Shops & Local Retail', out:['An online store that matches your storefront','Orders and inventory in one place','Local SEO so nearby customers find you']},
  {icon:'heart', name:'Restaurants & Cafés', out:['Menu, hours & online ordering','Table booking built in','Your Google & Maps listing done right']},
  {icon:'gear', name:'Home & Local Services', out:['Booking & quote requests online','Service-area SEO that actually ranks','Reviews and contact, front and center']},
  {icon:'spark', name:'Creators & Personal Brands', out:['A site or app that looks like you','Portfolio, shop or booking in one place','App store publishing handled']},
  {icon:'flag', name:'Nonprofits & Community Groups', out:['Donation & event pages','Volunteer sign-ups made simple','Discounted plans available']}
];
var CASESTUDIES = [
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
  return '<div class="card tilt"><div class="card-head">'+blobIcon(s.icon,'sm')+'<h3>'+s.name+'</h3></div><p>'+s.blurb+'</p><ul style="margin:0 0 18px;display:flex;flex-direction:column;gap:8px;">'+
    s.bullets.map(function(b){ return '<li style="display:flex;gap:8px;align-items:flex-start;color:var(--ink-soft);font-size:.9rem;">'+ico('check','').replace('width:22px','width:16px')+'<span>'+b+'</span></li>'; }).join('')+
    '</ul><a href="#/contact" class="card-link">Talk to us '+ico('arrow')+'</a></div>';
}
function solutionCard(s){
  return '<div class="card tilt"><div class="card-head">'+blobIcon(s.icon,'sm')+'<h3>'+s.name+'</h3></div><ul style="display:flex;flex-direction:column;gap:8px;">'+
    s.out.map(function(o){ return '<li style="display:flex;gap:8px;align-items:flex-start;color:var(--ink-soft);font-size:.9rem;">'+ico('check')+'<span>'+o+'</span></li>'; }).join('')+
    '</ul></div>';
}
function statBlock(num,label){ return '<div class="stat"><div class="num grad-text">'+num+'</div><div class="label">'+label+'</div></div>'; }

/* ===================================================================
   10. PAGE RENDERERS
=================================================================== */
var Pages = {};

Pages['/'] = function(){
  var S = window.SITE_SETTINGS || {};
  return ''
  +'<section class="hero"><div class="container hero-grid">'
    +'<div><span class="eyebrow">Websites & apps, fully handled</span>'
    +'<h1>'+(S.heroHeadlineMain||'Your business, finally')+' <span class="grad-text">'+(S.heroHeadlineAccent||'open online.')+'</span></h1>'
    +'<p class="lede">'+(S.heroSubheadline||'TECHBISS builds your website or app from the ground up, then handles the domain, hosting, SSL, business email and app store publishing — so you launch with everything already working, and people can actually find you.')+'</p>'
    +'<div class="hero-cta"><a href="#/services" class="btn btn-primary magnetic">See what we build '+ico('arrow')+'</a><a href="#/contact" class="btn btn-ghost magnetic">Book a free call</a></div>'
    +'<div class="hero-stats">'+statBlock('1,900+','Businesses & apps launched')+statBlock('38','Countries served')+statBlock('4.9/5','Customer rating')+statBlock('72 hrs','To your first draft')+'</div>'
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
      + ['Maple & Co. Bakery','Solstice Yoga Studio','Corner Hardware & Repair','Bloom & Bramble Florist','Nomad Coffee Roasters','Kinship Pet Rescue','Maple & Co. Bakery','Solstice Yoga Studio','Corner Hardware & Repair','Bloom & Bramble Florist','Nomad Coffee Roasters','Kinship Pet Rescue'].map(function(n){return '<span>'+n+'</span>';}).join('')
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
    +'<div class="text-center" style="margin-top:34px;"><a href="#/process" class="btn btn-soft magnetic">See the full process '+ico('arrow')+'</a></div>'
  +'</div></section>'
  +wave('a','var(--bg)',true)

  +'<section class="section tone-a"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Recent work</span><h2>Real businesses, now online</h2></div>'
    +'<div class="grid grid-3">'+CASESTUDIES.slice(0,3).map(function(c){
      return '<div class="card tilt"><div class="card-head">'+blobIcon(c.icon,'sm')+'<h3>'+c.client+'</h3></div><p>'+c.body+'</p>'
      +'<div class="stat" style="margin-bottom:14px;">'+statBlock(c.stat,c.statLabel)+'</div>'
      +'<div class="flex items-center justify-between"><a href="#/work" class="card-link">Read the story '+ico('arrow')+'</a><span class="badge">'+c.sector+'</span></div></div>';
    }).join('')+'</div>'
  +'</div></section>'

  +wave('b','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="quote-blob"><p>"We went from a Facebook page to a real website with online ordering in under two weeks — and they still pick up the phone when we call."</p><cite>— Priya Anand, Owner, Maple & Co. Bakery</cite></div>'
  +'</div></section>'
  +wave('c','var(--bg-alt-2)',true)

  +'<section class="section tone-c"><div class="container text-center">'
    +'<h2 style="max-width:20ch;margin-inline:auto;">Ready to take your business online?</h2>'
    +'<p class="lede" style="margin:0 auto 28px;">Tell us about your business, we\'ll tell you exactly what it takes to get you live.</p>'
    +'<div class="hero-cta" style="justify-content:center;"><a href="#/contact" class="btn btn-primary magnetic">Book a free call</a><a href="#/pricing" class="btn btn-ghost magnetic">See pricing</a></div>'
  +'</div></section>';
};

Pages['/services'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Services</span><h1 style="max-width:16ch;">Everything it takes to go from offline to online.</h1>'
    +'<p class="lede">Website or app, domain, hosting, email, publishing, SEO — pick what you need, or hand us the whole thing.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-2">'+SERVICES.map(function(s,i){
      return '<div class="card tilt" style="display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:flex-start;">'
        +blobIcon(s.icon,'lg')
        +'<div><h3 style="font-size:1.35rem;">'+s.name+'</h3><p>'+s.blurb+'</p>'
        +'<ul style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">'+s.bullets.map(function(b){return '<li style="display:flex;gap:8px;font-size:.9rem;color:var(--ink-soft);">'+ico('check')+'<span>'+b+'</span></li>';}).join('')+'</ul>'
        +'<a href="#/contact" class="card-link">Start a conversation '+ico('arrow')+'</a></div></div>';
    }).join('')+'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Ways to work with us</span><h2>Build it, buy a theme, or keep it running</h2></div>'
    +'<div class="grid grid-3">'
      +[['One-time build','A website or app, built once and handed over — fully yours, no lock-in.','box'],
        ['Bring your own template','Buy a theme from our marketplace and we\'ll brand and launch it for you.','cart'],
        ['Care plan','Hosting, updates, small changes and support, handled every month.','refresh']]
      .map(function(m){ return '<div class="card tilt"><div class="card-head">'+blobIcon(m[2],'sm',true)+'<h3>'+m[0]+'</h3></div><p>'+m[1]+'</p></div>'; }).join('')
    +'</div>'
  +'</div></section>';
};

Pages['/solutions'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Who we help</span><h1 style="max-width:18ch;">Built for people bringing a business online — not enterprise IT.</h1>'
    +'<p class="lede">Different business, different needs. The care we put into the work doesn\'t change.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-3">'+SOLUTIONS.map(solutionCard).join('')+'</div>'
  +'</div></section>'
  +wave('b','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Pick your path</span><h2>Build, buy a theme, or add an app</h2></div>'
    +'<div class="table-wrap"><table><thead><tr><th>Path</th><th>Best for</th><th>Typical timeline</th><th>Starting from</th></tr></thead><tbody>'
      +'<tr><td><b>Build</b></td><td>A site or app built from scratch around your business</td><td>2–6 weeks</td><td>'+fmtMoney(900)+'</td></tr>'
      +'<tr><td><b>Buy</b></td><td>A ready-made theme, branded and launched as your own</td><td>2–5 days</td><td>'+fmtMoney(59)+'</td></tr>'
      +'<tr><td><b>Publish</b></td><td>Add an app and get it live on the App Store & Play Store</td><td>3–8 weeks</td><td>'+fmtMoney(1500)+'</td></tr>'
    +'</tbody></table></div>'
  +'</div></section>';
};

Pages['/marketplace'] = function(){
  var cats = ['All','Templates','Bundles','AI Agents','Dashboards','Themes'];
  return '<section class="hero hero-sub" style="padding-bottom:10px;"><div class="container">'
    +'<span class="eyebrow">Marketplace</span><h1 style="max-width:16ch;">Ready-made themes you can brand as your own.</h1>'
    +'<p class="lede">Flip a card to preview it. Buy a theme, drop in your logo and colors, and launch — every listing is built and maintained by the TECHBISS studio.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container">'
    +'<div class="search-field">'+ico('search')+'<input type="search" id="mktSearch" placeholder="Search the marketplace" aria-label="Search marketplace"></div>'
    +'<div class="chip-row" id="mktChips">'+cats.map(function(c,i){return '<button class="chip'+(i===0?' active':'')+'" data-cat="'+c+'">'+c+'</button>';}).join('')+'</div>'
    +'<p class="mkt-count" id="mktCount"></p>'
    +'<div class="grid grid-4" id="mktGrid"></div>'
  +'</div></section>';
};

Pages['/marketplace/detail'] = function(id){
  var p = PRODUCTS.filter(function(x){return x.id===id;})[0] || PRODUCTS[0];
  return '<section class="hero" style="padding-bottom:0;"><div class="container">'
    +'<a href="#/marketplace" class="card-link" style="margin-bottom:18px;">'+ico('arrow').replace('12h14','14h-14').replace('M13 6l6 6-6 6','M11 6l-6 6 6 6')+' Back to marketplace</a>'
    +'<div class="hero-grid" style="align-items:flex-start;">'
      +'<div>'
        +'<span class="badge grad">'+p.cat+'</span>'
        +'<h1 style="margin-top:14px;">'+p.name+'</h1>'
        +'<p class="lede">'+p.tagline+'</p>'
        +'<div class="flex items-center gap-12" style="margin:18px 0 28px;"><span class="stat"><span class="num" style="font-size:1.4rem;">'+fmtMoney(p.price)+'</span></span><span class="badge">★ '+p.rating+' rating</span></div>'
      +'</div>'
      +'<div class="hero-visual" style="aspect-ratio:4/3;"><svg viewBox="0 0 200 150" style="width:100%;height:100%;"><path fill="url(#pdGrad)" d="M40,-40C56,-30,68,-10,66,10C64,30,48,46,28,54C8,62,-16,62,-34,50C-52,38,-64,14,-62,-8C-60,-30,-44,-50,-24,-58C-4,-66,20,-50,40,-40Z" transform="translate(100 76)"/><defs><linearGradient id="pdGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" style="stop-color:var(--accent-1)"/><stop offset="100%" style="stop-color:var(--accent-2)"/></linearGradient></defs></svg><div style="position:absolute;color:#fff;">'+ico(p.icon).replace('width:22px;height:22px','width:56px;height:56px')+'</div></div>'
    +'</div></div></section>'
  +'<section class="section tone-a" style="padding-top:26px;"><div class="container">'
    +'<div class="tabbar" id="pdTabs" role="tablist">'
      +['Preview','Customize','Purchase','Deploy'].map(function(t,i){return '<button role="tab" class="'+(i===0?'active':'')+'" data-tab="'+t.toLowerCase()+'">'+(i+1)+'. '+t+'</button>';}).join('')
    +'</div>'
    +'<div id="pdPanels" data-pid="'+p.id+'"></div>'
  +'</div></section>';
};

Pages['/work'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Work</span><h1 style="max-width:18ch;">Real businesses, now online.</h1>'
    +'<p class="lede">A few of the shops, studios and teams behind the stats on our homepage.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-2" id="caseGrid">'+CASESTUDIES.map(function(c,i){
      return '<div class="card tilt case-card" data-i="'+i+'"><div class="flex items-center gap-16" style="margin-bottom:14px;">'+blobIcon(c.icon,'sm')+'<div><span class="badge">'+c.sector+'</span><h3 style="margin:6px 0 0;">'+c.client+'</h3></div></div>'
      +'<p style="font-style:italic;">"'+c.quote+'"</p>'
      +'<div class="stat" style="margin:14px 0;">'+statBlock(c.stat,c.statLabel)+'</div>'
      +'<button class="card-link case-toggle" data-i="'+i+'" style="background:none;border:none;padding:0;">Read the full story '+ico('arrow')+'</button>'
      +'<div class="accordion-panel case-panel" data-i="'+i+'"><div class="inner"><p>'+c.body+'</p></div></div>'
      +'</div>';
    }).join('')+'</div>'
  +'</div></section>';
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
    +'<span class="eyebrow">Process</span><h1 style="max-width:16ch;">Five stages. No surprises in between.</h1>'
    +'<p class="lede">Every project — big or small — moves through the same five stages, so you always know what\'s next.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div style="display:flex;flex-direction:column;gap:0;">'
    +steps.map(function(s,i){
      return '<div class="flex" style="gap:28px;padding:30px 0;border-bottom:'+(i<steps.length-1?'1px solid var(--border-soft)':'none')+';align-items:flex-start;">'
        +'<div style="display:flex;flex-direction:column;align-items:center;gap:8px;flex:none;">'+blobIcon(s.icon,'lg')+'<span style="font-family:var(--font-display);font-weight:700;color:var(--ink-faint);">0'+(i+1)+'</span></div>'
        +'<div style="flex:1;"><h3>'+s.t+'</h3><p class="lede">'+s.d+'</p>'
        +'<div class="flex gap-8" style="flex-wrap:wrap;">'+s.out.map(function(o){return '<span class="badge">'+o+'</span>';}).join('')+'</div></div>'
      +'</div>';
    }).join('')
    +'</div>'
  +'</div></section>';
};

Pages['/about'] = function(){
  var values = [
    {icon:'heart', t:'Plain language, always', d:'No jargon you need a developer to translate. If we can\'t explain it simply, we haven\'t understood it yet.'},
    {icon:'shield', t:'Nothing rented that should be owned', d:'Your domain, your site, your app — registered and built in your name, not locked to us.'},
    {icon:'users', t:'A real person replies', d:'Support that\'s an actual person who knows your project, not a ticket number.'},
    {icon:'spark', t:'Built to be found, not just to exist', d:'A website nobody can find isn\'t really online. SEO is part of the build, not an upsell.'}
  ];
  var team = [
    {i:'MA', n:'Mara Aldous', r:'Founder & CEO'},
    {i:'DK', n:'Devon Kwan', r:'Head of Engineering'},
    {i:'RS', n:'Rhea Solano', r:'Head of Design'},
    {i:'JT', n:'Jonah Traeger', r:'VP Client Success'}
  ];
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">About</span><h1 style="max-width:16ch;">Started because getting online shouldn\'t be this hard.</h1>'
    +'<p class="lede">TECHBISS began by building one shop owner a website over a weekend. Nine years later, the same conviction runs the platform: we build every project like it\'s the most important one — because to the person running the business, it is.</p>'
  +'</div></section>'
  +'<section class="section tone-a"><div class="container">'
    +'<div class="grid grid-4">'+[['9','Years in business'],['1,900+','Businesses & apps launched'],['38','Countries served'],['4.9/5','Customer rating']].map(function(s){return '<div class="card tilt text-center">'+statBlock(s[0],s[1])+'</div>';}).join('')+'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">What we hold onto</span><h2>Values that show up in the work</h2></div>'
    +'<div class="grid grid-4">'+values.map(function(v){return '<div class="card tilt"><div class="card-head">'+blobIcon(v.icon,'sm',true)+'<h3 style="font-size:1.05rem;">'+v.t+'</h3></div><p style="font-size:.88rem;">'+v.d+'</p></div>';}).join('')+'</div>'
  +'</div></section>'
  +wave('b','var(--bg)',true)
  +'<section class="section tone-a"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Leadership</span><h2>The people steering the studio</h2></div>'
    +'<div class="grid grid-4">'+team.map(function(t){return '<div class="card tilt text-center"><div class="avatar-blob" style="margin:0 auto 14px;">'+t.i+'</div><h3 style="font-size:1.05rem;">'+t.n+'</h3><p style="font-size:.85rem;">'+t.r+'</p></div>';}).join('')+'</div>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container text-center">'
    +'<div class="quote-blob"><p>"We\'re always looking for people who\'d rather ship a small business\'s first website than polish a slide deck."</p><cite>— Careers at TECHBISS</cite></div>'
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
    +'<span class="eyebrow">Resources</span><h1 style="max-width:16ch;">Field notes for getting online.</h1>'
    +'<p class="lede">Guides, recordings and release notes from the team actually building this.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container">'
    +'<div class="chip-row" id="resChips">'+types.map(function(t,i){return '<button class="chip'+(i===0?' active':'')+'" data-cat="'+t+'">'+t+'</button>';}).join('')+'</div>'
    +'<div class="grid grid-3" id="resGrid">'+res.map(function(r){
      return '<div class="card tilt" data-cat="'+r.k+'"><div class="card-head">'+blobIcon(r.icon,'sm',true)+'<h3 style="font-size:1.08rem;">'+r.t+'</h3></div><p style="font-size:.85rem;">'+r.min+'</p><div class="flex items-center justify-between"><span class="card-link">Read '+ico('arrow')+'</span><span class="badge">'+r.k+'</span></div></div>';
    }).join('')+'</div>'
  +'</div></section>'
  +wave('c','var(--bg-alt-2)')
  +'<section class="section tone-c"><div class="container text-center">'
    +'<h2 style="max-width:20ch;margin-inline:auto;">Get the next field note before anyone else</h2>'
    +'<form id="newsForm" class="flex" style="max-width:420px;margin:24px auto 0;gap:10px;" onsubmit="return false;">'
      +'<input type="email" id="newsEmail" required placeholder="you@company.com" aria-label="Email address" style="flex:1;padding:14px 18px;border-radius:var(--r-full);border:1.5px solid var(--border);background:var(--surface);outline:none;">'
      +'<button class="btn btn-primary" type="submit">Subscribe</button>'
    +'</form><p id="newsMsg" class="badge success" style="display:none;margin-top:14px;">'+ico('check')+' You\'re on the list — welcome!</p>'
    +'<p id="newsError" class="badge danger" hidden style="margin-top:14px;"></p>'
  +'</div></section>';
};

Pages['/pricing'] = function(){
  var tiers=[
    {n:'Starter', m:39, y:31, d:'Hosting, domain renewal and a small monthly update — for once your site is live.', f:['Hosting, SSL & domain included','1 small update per month','Email support','Uptime monitoring'], cta:'Start with Starter'},
    {n:'Growth', m:99, y:79, d:'For businesses adding bookings, an online store, or an app.', f:['Everything in Starter','Priority support','Marketplace theme credit','Monthly SEO check-in','App store update handling'], cta:'Start with Growth', rec:true},
    {n:'Custom Build', m:null, y:null, d:'A website or app built from scratch around your business.', f:['Custom design & development','Dedicated project lead','Domain, hosting, SSL & email included','App Store & Play Store publishing','Free ranking check-up'], cta:'Get a free quote'}
  ];
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container text-center">'
    +'<span class="eyebrow">Pricing</span><h1 style="max-width:20ch;margin-inline:auto;">Straightforward care plans. Custom builds, quoted upfront.</h1>'
    +'<div class="toggle-pill" id="priceToggle" style="margin-top:20px;"><button class="active" data-p="m">Monthly</button><button data-p="y">Annual — save 20%</button></div>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:0;"><div class="container">'
    +'<div class="grid grid-3" id="priceGrid">'+tiers.map(function(t,i){
      return '<div class="card tilt" style="'+(t.rec?'border-color:var(--accent-1);box-shadow:var(--shadow-lg);position:relative;':'')+'">'
        +(t.rec?'<span class="badge grad" style="position:absolute;top:-14px;left:32px;">Most popular</span>':'')
        +'<h3>'+t.n+'</h3><p style="font-size:.9rem;">'+t.d+'</p>'
        +'<div style="margin:14px 0 20px;"><span class="price-amt" data-m="'+t.m+'" data-y="'+t.y+'" style="font-family:var(--font-display);font-weight:800;font-size:2.2rem;">'+(t.m?fmtMoney(t.m):'Custom')+'</span>'+(t.m?'<span style="color:var(--ink-faint);"> /mo</span>':'')+'</div>'
        +'<ul style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px;">'+t.f.map(function(f){return '<li style="display:flex;gap:8px;font-size:.9rem;">'+ico('check')+'<span>'+f+'</span></li>';}).join('')+'</ul>'
        +'<a href="#/contact" class="btn '+(t.rec?'btn-primary':'btn-ghost')+' btn-block">'+t.cta+'</a>'
      +'</div>';
    }).join('')+'</div>'
  +'</div></section>'
  +wave('a','var(--bg-alt)')
  +'<section class="section tone-b"><div class="container">'
    +'<div class="section-head center"><span class="eyebrow">Questions</span><h2>Pricing FAQ</h2></div>'
    +'<div id="priceFaq">'+[
      ['Do you build the website too, or is this just hosting?','These plans cover hosting, care and updates after launch. New builds — a website, an app, or both — are quoted upfront based on what you need.'],
      ['Can I switch plans later?','Yes — upgrade or downgrade at the start of any billing cycle, and we\'ll prorate the difference.'],
      ['What if I already have a website?','We can take over hosting and support for an existing site, or rebuild it if it needs modern love — either way, nothing changes for your visitors during the switch.'],
      ['Can I start with a marketplace theme instead of a custom build?','Yes — Growth and Custom Build plans include a marketplace credit toward any ready-made theme, which we\'ll brand and launch for you.'],
      ['Do you offer nonprofit or small business discounts?','Yes, reach out through Contact and we\'ll tailor a plan for community and mission-driven organizations.']
    ].map(function(f,i){
      return '<div class="accordion-item'+(i===0?' open':'')+'"><button aria-expanded="'+(i===0)+'">'+f[0]+ico('arrow').replace('M13 6l6 6-6 6','m6 9 6 6 6-6').replace('M5 12h14','')+'</button><div class="accordion-panel"><div class="inner">'+f[1]+'</div></div></div>';
    }).join('')+'</div>'
  +'</div></section>';
};

Pages['/contact'] = function(){
  var S = window.SITE_SETTINGS || {};
  return '<section class="hero hero-sub" style="padding-bottom:20px;"><div class="container">'
    +'<span class="eyebrow">Contact</span><h1 style="max-width:18ch;">Tell us about your business. We\'ll tell you what it takes to get online.</h1>'
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
        +'<button class="btn btn-primary btn-block magnetic" type="submit">Send message '+ico('arrow')+'</button>'
        +'<p id="contactError" class="badge danger" hidden style="margin-top:14px;"></p>'
      +'</form>'
      +'<div id="contactSuccess" hidden style="text-align:center;padding:20px 0;">'+blobIcon('check','lg')+'<h3>Message sent</h3><p>Thanks — a real human will reply within two business days.</p></div>'
    +'</div>'
    +'<div style="display:flex;flex-direction:column;gap:20px;">'
      +'<div class="card"><div class="card-head">'+blobIcon('mail','sm',true)+'<h3 style="font-size:1.05rem;">Email</h3></div><p style="font-size:.9rem;">'+(S.contactEmail||'hello@techbiss.com')+'</p></div>'
      +'<div class="card"><div class="card-head">'+blobIcon('phone','sm',true)+'<h3 style="font-size:1.05rem;">Phone</h3></div><p style="font-size:.9rem;">'+(S.contactPhone||'+1 (415) 555-0148')+'</p></div>'
      +'<div class="card"><div class="card-head">'+blobIcon('globe','sm',true)+'<h3 style="font-size:1.05rem;">Studios</h3></div><p style="font-size:.9rem;">San Francisco · Lisbon · Singapore</p></div>'
    +'</div>'
  +'</div></section>';
};

Pages['/login'] = function(){
  return '<section class="hero"><div class="container hero-grid" style="align-items:stretch;">'
    +'<div class="card" style="max-width:440px;">'
      +'<div class="tabbar" id="authTabs"><button class="active" data-t="signin">Sign in</button><button data-t="signup">Create account</button></div>'
      +'<div id="authPanels">'
        +'<form id="signinForm" onsubmit="return false;">'
          +'<div class="field"><label for="liEmail">Email</label><input id="liEmail" type="email" required placeholder="you@company.com"></div>'
          +'<div class="field"><label for="liPass">Password</label><input id="liPass" type="password" required placeholder="••••••••"></div>'
          +'<div class="flex justify-between items-center" style="margin-bottom:20px;"><label class="flex items-center gap-8" style="font-size:.85rem;"><input type="checkbox"> Remember me</label><a href="#/contact" style="font-size:.85rem;color:var(--accent-1);font-weight:600;">Forgot password?</a></div>'
          +'<button class="btn btn-primary btn-block magnetic" type="submit">Sign in</button>'
        +'</form>'
        +'<p id="loginMsg" class="badge success" style="display:none;margin-top:16px;">'+ico('check')+' Welcome back — redirecting…</p>'
        +'<p id="loginError" class="badge danger" hidden style="margin-top:16px;"></p>'
      +'</div>'
    +'</div>'
    +'<div class="hero-visual" style="aspect-ratio:auto;">'
      +'<div class="card" style="background:var(--grad);color:#fff2ea;border:none;text-align:center;padding:44px;">'
      +'<div class="logo-mark" style="width:64px;height:64px;margin:0 auto 18px;"><svg viewBox="0 0 24 24" fill="none"><g><rect x="3" y="3" width="18" height="18" rx="6" fill="#fff2ea"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="url(#logoGrad)"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="url(#logoGrad)"/></g></svg></div>'
      +'<h2 style="color:#fff2ea;">Good to see you again.</h2><p style="color:rgba(255,242,234,.9);">Your dashboard, marketplace orders and site updates are exactly where you left them — sign in to pick up right where you left off.</p>'
      +'</div>'
    +'</div>'
  +'</div></section>';
};


Pages['/dashboard'] = function(){
  return '<section class="hero hero-sub" style="padding-bottom:12px;"><div class="container">'
    +'<span class="eyebrow">Client dashboard preview</span><h1 style="max-width:20ch;">Welcome back, '+((AUTH_USER&&AUTH_USER.name)||'there')+'.</h1>'
    +'<p class="lede">A look at the dashboard you get once your site or app is live.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container">'
    +'<div class="grid grid-4" style="margin-bottom:28px;">'
      +statCardHTML('99.98%','Uptime this month','shield')
      +statCardHTML('1,482','Website visits today','chart')
      +statCardHTML('2','Open support tickets','chat')
      +statCardHTML('Sep 18','Next check-in','calendar')
    +'</div>'
    +'<div class="hero-grid" style="align-items:flex-start;gap:26px;">'
      +'<div style="display:flex;flex-direction:column;gap:22px;">'
        +'<div class="card"><h3>Active projects</h3>'
          +projectRow('Website redesign','On track','78','success')
          +projectRow('Order-ahead app','In review','54','warning')
          +projectRow('Local SEO push','On track','23','success')
        +'</div>'
        +'<div class="card"><h3>Recent activity</h3><ul style="display:flex;flex-direction:column;gap:14px;">'
          +activityRow('check','Website update published','2 hours ago')
          +activityRow('chat','New comment from Devon on the order-ahead app','Yesterday')
          +activityRow('box','Marketplace order: Bramble Field Themes','2 days ago')
          +'</ul></div>'
      +'</div>'
      +'<div style="display:flex;flex-direction:column;gap:22px;">'
        +'<div class="card"><h3>Support tickets</h3>'
          +ticketRow('#1042','Checkout error on mobile Safari','warning','Open')
          +ticketRow('#1039','Add a holiday hours banner','success','In progress')
        +'<a href="#/contact" class="card-link" style="margin-top:12px;">Open a new ticket '+ico('arrow')+'</a></div>'
        +'<div class="card" style="background:var(--grad);color:#fff2ea;border:none;"><div class="card-head">'+blobIcon('users','sm',false)+'<h3 style="color:#fff2ea;">Book a check-in call</h3></div><p style="color:rgba(255,242,234,.9);">A monthly look at your site\'s performance and what to do next.</p><a href="#/contact" class="btn" style="background:#fff2ea;color:var(--accent-1);">Schedule now</a></div>'
      +'</div>'
    +'</div>'
  +'</div></section>';
};
Pages['/account'] = function(){
  var u = AUTH_USER || {};
  return '<section class="hero hero-sub" style="padding-bottom:12px;"><div class="container">'
    +'<span class="eyebrow">Your account</span><h1 style="max-width:16ch;">Profile</h1>'
    +'<p class="lede">Manage your sign-in and account details.</p>'
  +'</div></section>'
  +'<section class="section tone-a" style="padding-top:10px;"><div class="container" style="max-width:640px;">'
    +'<div class="card" style="text-align:center;padding:32px 24px;">'
      +'<div class="avatar-blob" style="margin:0 auto 16px;">'+((u.name||'U').charAt(0).toUpperCase())+'</div>'
      +'<h3 style="margin-bottom:2px;">'+(u.name||'Your account')+'</h3>'
      +'<p style="color:var(--ink-faint);">'+(u.email||'')+'</p>'
    +'</div>'
    +'<div class="card" style="margin-top:16px;">'
      +'<a href="#/dashboard" class="card-link" style="margin-bottom:14px;">'+ico('chart')+' View dashboard</a>'
      +'<a href="#/contact" class="card-link" style="margin-bottom:14px;">'+ico('chat')+' Open a support ticket</a>'
      +'<button id="accountLogoutBtn" class="btn btn-ghost btn-block" type="button">'+ico('logout')+' Log out</button>'
    +'</div>'
  +'</div></section>';
};
function statCardHTML(num,label,icon){
  return '<div class="card tilt">'+blobIcon(icon,'sm',true)+'<div class="stat" style="margin-top:12px;">'+statBlock(num,label)+'</div></div>';
}
function projectRow(name,status,pct,tone){
  return '<div style="margin:18px 0;"><div class="flex justify-between items-center" style="margin-bottom:8px;"><b style="font-family:var(--font-display);">'+name+'</b><span class="badge '+tone+'">'+status+'</span></div>'
  +'<div class="progress-track"><div class="progress-fill" style="width:'+pct+'%;"></div></div></div>';
}
function activityRow(icon,text,when){
  return '<li class="flex gap-12 items-center"><span class="blob-icon sm soft">'+ico(icon)+'</span><span style="flex:1;font-size:.9rem;">'+text+'<br><span style="color:var(--ink-faint);font-size:.78rem;">'+when+'</span></span></li>';
}
function ticketRow(id,title,tone,status){
  return '<div class="flex justify-between items-center" style="padding:12px 0;border-bottom:1px solid var(--border-soft);"><div><span style="font-family:var(--font-display);font-weight:600;font-size:.85rem;color:var(--ink-faint);">'+id+'</span><p style="margin:2px 0 0;font-size:.9rem;">'+title+'</p></div><span class="badge '+tone+'">'+status+'</span></div>';
}

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
          +'<div class="card-head" style="margin-bottom:12px;">'+blobIcon(p.icon,'sm')+'<h3 style="font-size:1.05rem;">'+p.name+'</h3></div>'
          +'<p style="font-size:.85rem;">'+p.tagline+'</p>'
          +'<div class="pf-preview">'+ico(p.icon)+'</div>'
          +'<div class="pf-tags" style="justify-content:space-between;align-items:center;"><div style="display:flex;gap:6px;flex-wrap:wrap;"><span class="badge">'+p.cat+'</span>'+(p.rating>=4.9?'<span class="badge grad">Popular</span>':'')+'</div><span class="pf-rating">'+ico('star')+' '+p.rating+'</span></div>'
          +'<div class="pf-foot"><span class="pf-price">'+fmtMoney(p.price)+(p.pricing_type==='fixed'?'':'<span> /mo</span>')+'</span><span class="pf-hint">Flip to preview '+ico('refresh')+'</span></div>'
        +'</div>'
        +'<div class="flip-face flip-back">'
          +'<div><h3 style="color:#fff2ea;font-size:1.05rem;">'+p.name+'</h3>'
          +'<p style="font-size:.85rem;">'+p.desc+'</p>'
          +'<div class="fb-specs">'+p.specs.slice(0,3).map(function(s){return '<div class="flex items-center gap-8">'+ico('check')+'<span>'+s+'</span></div>';}).join('')+'</div></div>'
          +'<a href="#/marketplace/detail/'+p.id+'" class="btn" style="background:#fff2ea;color:var(--accent-1);">View details '+ico('arrow')+'</a>'
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
      fetch('api/newsletter.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email:email}) })
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
  var toggle = $('#priceToggle');
  toggle.addEventListener('click', function(e){
    var b=e.target.closest('button'); if(!b) return;
    $all('button',toggle).forEach(function(x){x.classList.remove('active');}); b.classList.add('active');
    $all('.price-amt').forEach(function(el){
      var v = b.dataset.p==='y' ? el.dataset.y : el.dataset.m;
      el.textContent = v==='null' ? 'Custom' : fmtMoney(+v);
    });
  });
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
    fetch('api/contact.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        name: $('#cName').value, email: $('#cEmail').value,
        company: $('#cCompany').value, need: $('#cType').value, message: $('#cMsg').value
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
  var tabs = $('#authTabs'), panels = $('#authPanels');
  var signup = '<form id="signupForm" onsubmit="return false;">'
    +'<div class="field"><label for="suName">Full name</label><input id="suName" required></div>'
    +'<div class="field"><label for="suEmail">Work email</label><input id="suEmail" type="email" required></div>'
    +'<div class="field"><label for="suPass">Create a password</label><input id="suPass" type="password" minlength="8" required></div>'
    +'<button class="btn btn-primary btn-block magnetic" type="submit">Create account</button>'
    +'<p id="signupMsg" class="badge success" style="display:none;margin-top:16px;">'+ico('check')+' Account created — redirecting…</p>'
    +'<p id="signupError" class="badge danger" hidden style="margin-top:16px;"></p>'
  +'</form>';
  var signin = panels.querySelector('#signinForm').outerHTML;
  tabs.addEventListener('click', function(e){
    var b=e.target.closest('button'); if(!b) return;
    $all('button',tabs).forEach(function(x){x.classList.remove('active');}); b.classList.add('active');
    if(b.dataset.t==='signup'){ panels.innerHTML = signup; bindForm('signupForm','signupMsg','signupError','api/signup.php'); }
    else { panels.innerHTML = signin + '<p id="loginMsg" class="badge success" style="display:none;margin-top:16px;">'+ico('check')+' Welcome back — redirecting…</p><p id="loginError" class="badge danger" hidden style="margin-top:16px;"></p>'; bindForm('signinForm','loginMsg','loginError','api/login.php'); attachTilt(panels); }
  });
  function bindForm(fid,mid,eid,endpoint){
    var f = $('#'+fid);
    f.addEventListener('submit', function(){
      var errEl = $('#'+eid); errEl.hidden = true;
      var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
      var isSignup = fid==='signupForm';
      var payload = isSignup
        ? { name: $('#suName').value, email: $('#suEmail').value, password: $('#suPass').value }
        : { email: $('#liEmail').value, password: $('#liPass').value };
      fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
        .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
        .then(function(res){
          btn.disabled = false;
          if(!res.ok){ errEl.textContent = res.data.error || 'Something went wrong — please try again.'; errEl.hidden = false; return; }
          AUTH_USER = res.data.user || AUTH_USER;
          $('#'+mid).style.display='inline-flex';
          setTimeout(function(){ location.hash = '#/dashboard'; }, 700);
        })
        .catch(function(){
          btn.disabled = false;
          errEl.textContent = 'Could not reach the server — please try again.'; errEl.hidden = false;
        });
    });
  }
  bindForm('signinForm','loginMsg','loginError','api/login.php');
}

function wireProductDetail(id){
  var p = PRODUCTS.filter(function(x){return x.id===id;})[0] || PRODUCTS[0];
  var tabs = $('#pdTabs'), panels = $('#pdPanels');
  var state = { tier:'growth', addons:{} , order:null };
  var TIERS = [
    {k:'starter', n:'Starter', price:p.price, d:'The product as-is, standard support.'},
    {k:'growth', n:'Growth', price:Math.round(p.price*1.8), d:'Priority support & a monthly check-in.'},
    {k:'scale', n:'Scale', price:Math.round(p.price*3.1), d:'A dedicated person & priority support around the clock.'}
  ];
  var ADDONS = [
    {k:'onboarding', n:'Guided setup call', price:99},
    {k:'branding', n:'Custom branding pass', price:149},
    {k:'sla', n:'24/7 priority support', price:219}
  ];
  function renderPreview(){
    return '<div class="grid grid-2"><div><h3>Overview</h3><p>'+p.desc+'</p>'
      +'<h3 style="margin-top:22px;">What\'s included</h3><ul style="display:flex;flex-direction:column;gap:8px;">'+p.specs.map(function(s){return '<li style="display:flex;gap:8px;font-size:.9rem;">'+ico('check')+'<span>'+s+'</span></li>';}).join('')+'</ul></div>'
      +'<div class="hero-visual" style="aspect-ratio:1/1;"><svg viewBox="0 0 200 200" style="width:80%;height:80%;"><path fill="url(#pvGrad)" d="M46,-52C58,-42,64,-24,64,-6C64,12,58,28,46,40C34,52,16,60,-3,63C-22,66,-44,64,-56,52C-68,40,-70,18,-67,-3C-64,-24,-56,-46,-40,-58C-24,-70,-2,-72,17,-68C36,-64,34,-62,46,-52Z" transform="translate(100 100)"/><defs><linearGradient id="pvGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" style="stop-color:var(--accent-1)"/><stop offset="100%" style="stop-color:var(--accent-2)"/></linearGradient></defs></svg></div>'
      +'</div><button class="btn btn-primary magnetic" style="margin-top:24px;" data-goto="customize">Customize this product '+ico('arrow')+'</button>';
  }
  function total(){
    var tier = TIERS.filter(function(t){return t.k===state.tier;})[0];
    var sum = tier.price;
    ADDONS.forEach(function(a){ if(state.addons[a.k]) sum += a.price; });
    return sum;
  }
  function renderCustomize(){
    return '<div class="hero-grid" style="align-items:flex-start;">'
      +'<div><h3>Choose a tier</h3><div class="grid grid-3" style="margin-bottom:26px;">'
        +TIERS.map(function(t){
          return '<label class="option-card'+(state.tier===t.k?' selected':'')+'" data-tier="'+t.k+'"><input type="radio" name="tier"><b style="font-family:var(--font-display);">'+t.n+'</b><p style="font-size:.82rem;margin:6px 0;">'+t.d+'</p><span class="price">'+fmtMoney(t.price)+'</span></label>';
        }).join('')
      +'</div>'
      +'<h3>Add-ons</h3><div style="display:flex;flex-direction:column;gap:10px;">'
        +ADDONS.map(function(a){
          return '<label class="option-card'+(state.addons[a.k]?' selected':'')+'" data-addon="'+a.k+'" style="display:flex;justify-content:space-between;align-items:center;"><input type="checkbox"><span>'+a.n+'</span><span class="price">+'+fmtMoney(a.price)+'</span></label>';
        }).join('')
      +'</div></div>'
      +'<div class="card" style="background:var(--grad-soft);position:sticky;top:110px;">'
        +'<h3>Your summary</h3><p style="font-size:.85rem;">'+p.name+' — '+TIERS.filter(function(t){return t.k===state.tier;})[0].n+' plan</p>'
        +'<div style="font-family:var(--font-display);font-weight:800;font-size:2rem;margin:14px 0;" id="pdTotal">'+fmtMoney(total())+(p.pricing_type==='fixed'?'':'<span style="font-size:.9rem;color:var(--ink-faint);font-weight:600;"> /mo</span>')+'</div>'
        +'<button class="btn btn-primary btn-block magnetic" data-goto="purchase">Continue to purchase '+ico('arrow')+'</button>'
      +'</div></div>';
  }
  function renderPurchase(){
    if(state.order){
      return '<div class="text-center" style="padding:20px 0;">'+blobIcon('check','lg')+'<h3>You\'re all set, thank you.</h3><p>Confirmation <b>'+state.order+'</b> — a receipt is on its way to your inbox. This is a design preview, so no card was actually charged.</p>'
        +'<button class="btn btn-primary magnetic" data-goto="deploy">Continue to deploy '+ico('arrow')+'</button></div>';
    }
    var tier = TIERS.filter(function(t){return t.k===state.tier;})[0];
    return '<div class="hero-grid" style="align-items:flex-start;">'
      +'<div><h3>Review your order</h3>'
      +'<div class="table-wrap"><table><tbody>'
        +'<tr><td>'+p.name+' — '+tier.n+'</td><td>'+fmtMoney(tier.price)+'</td></tr>'
        +ADDONS.filter(function(a){return state.addons[a.k];}).map(function(a){return '<tr><td>'+a.n+'</td><td>'+fmtMoney(a.price)+'</td></tr>';}).join('')
        +'<tr><td><b>Total due today</b></td><td><b>'+fmtMoney(total())+'</b></td></tr>'
      +'</tbody></table></div>'
      +'<div class="field" style="margin-top:20px;"><label>Card details (demo only)</label><input placeholder="4242 4242 4242 4242" disabled></div>'
      +'</div>'
      +'<div class="card"><h3>Ready when you are</h3><p style="font-size:.85rem;">No payment is actually processed in this concept — confirming just simulates the flow.</p>'
      +'<button class="btn btn-primary btn-block magnetic" id="confirmPurchase">Confirm purchase '+ico('arrow')+'</button></div>'
      +'</div>';
  }
  var deployed=false;
  function renderDeploy(){
    var rows=['Provisioning environment','Configuring services','Running migrations','Finalizing & health checks'];
    return '<div class="text-center" style="max-width:520px;margin:0 auto;">'
      +'<div class="blob-icon lg" id="deployBlob" style="margin:0 auto 18px;">'+ico('rocket')+'</div>'
      +'<h3 id="deployTitle">'+(deployed?'You\'re live.':'Deploying '+p.name+'…')+'</h3>'
      +'<div class="progress-track" style="margin:18px 0;"><div class="progress-fill" id="deployFill" style="width:'+(deployed?100:0)+'%;"></div></div>'
      +'<div class="deploy-log" id="deployLog">'+rows.map(function(r,i){return '<div class="row'+(deployed?' on':'')+'" data-i="'+i+'"><span class="dot">'+(deployed?ico('check').replace('width:22px;height:22px','width:11px;height:11px'):'')+'</span><span>'+r+'</span></div>';}).join('')+'</div>'
      +(deployed?'<a href="#/dashboard" class="btn btn-primary magnetic">Launch workspace '+ico('arrow')+'</a>':'<button class="btn btn-ghost" id="startDeploy">Start deployment</button>')
    +'</div>';
  }
  var renderers = {preview:renderPreview, customize:renderCustomize, purchase:renderPurchase, deploy:renderDeploy};
  function showTab(tab){
    $all('button',tabs).forEach(function(b){ b.classList.toggle('active', b.dataset.tab===tab); });
    panels.innerHTML = renderers[tab]();
    wirePanel(tab);
    attachTilt(panels);
  }
  function wirePanel(tab){
    $all('[data-goto]', panels).forEach(function(b){ b.addEventListener('click', function(){ showTab(b.dataset.goto); }); });
    if(tab==='customize'){
      $all('[data-tier]', panels).forEach(function(o){
        o.addEventListener('click', function(){ state.tier=o.dataset.tier; showTab('customize'); });
      });
      $all('[data-addon]', panels).forEach(function(o){
        o.addEventListener('click', function(){ state.addons[o.dataset.addon]=!state.addons[o.dataset.addon]; showTab('customize'); });
      });
    }
    if(tab==='purchase'){
      var btn = $('#confirmPurchase');
      if(btn) btn.addEventListener('click', function(){
        state.order = 'TB-'+Math.floor(100000+Math.random()*899999);
        showTab('purchase');
        var r = panels.getBoundingClientRect();
        confettiBurst(r.left+r.width/2, r.top+40);
      });
    }
    if(tab==='deploy'){
      var start = $('#startDeploy');
      if(start) start.addEventListener('click', function(){ runDeploy(); });
    }
  }
  function runDeploy(){
    var fill=$('#deployFill'), log=$('#deployLog'), title=$('#deployTitle');
    var rows = $all('.row', log);
    var i=0;
    function step(){
      if(i>=rows.length){
        deployed=true;
        title.textContent='You\'re live.';
        var panel = $('#pdPanels');
        showTab('deploy');
        var r = panel.getBoundingClientRect();
        confettiBurst(r.left+r.width/2, r.top+60);
        return;
      }
      rows[i].classList.add('on','active');
      rows[i].querySelector('.dot').innerHTML = ico('check').replace('width:22px;height:22px','width:11px;height:11px');
      fill.style.width = Math.round(((i+1)/rows.length)*100)+'%';
      i++;
      setTimeout(step, motionOK?700:80);
    }
    setTimeout(step, motionOK?400:50);
  }
  tabs.addEventListener('click', function(e){ var b=e.target.closest('button'); if(b) showTab(b.dataset.tab); });
  showTab('preview');
}


/* ===================================================================
   12. ROUTER
=================================================================== */
function currentBasePath(){
  var h = location.hash.replace(/^#/,'') || '/';
  if(h.indexOf('/marketplace/detail')===0) return '/marketplace';
  return h;
}
function parseRoute(){
  var h = location.hash.replace(/^#/,'') || '/';
  var m = h.match(/^\/marketplace\/detail\/(.+)$/);
  if(m) return {key:'/marketplace/detail', param:m[1], nav:'/marketplace'};
  return {key: Pages[h] ? h : '/', param:null, nav:h};
}
var wipe = $('#routeWipe');
var AUTH_USER = null;
function refreshAuth(){
  return fetch('api/me.php').then(function(r){ return r.ok ? r.json() : null; })
    .then(function(data){ AUTH_USER = (data && data.user) || null; return AUTH_USER; })
    .catch(function(){ AUTH_USER = null; return null; });
}
function doRender(){
  var r = parseRoute();
  if((r.key==='/dashboard'||r.key==='/account') && !AUTH_USER){ location.hash = '#/login'; return; }
  var view = $('#view');
  view.innerHTML = Pages[r.key](r.param);
  window.scrollTo(0,0);
  attachTilt(view);
  var afterMap = {
    '/': wireHome, '/work': wireWork, '/marketplace': wireMarketplace,
    '/resources': wireResources, '/pricing': wirePricing, '/contact': wireContact,
    '/login': wireLogin, '/account': wireAccount
  };
  if(r.key==='/marketplace/detail'){ wireProductDetail(r.param); }
  else if(afterMap[r.key]){ afterMap[r.key](); }
  moveNavBlob(currentNavLink());
  var loginBtn = $('#navLoginBtn');
  var dockLogin = $('.dock-item[data-path="/login"], .dock-item[data-path="/account"]');
  if(AUTH_USER){
    loginBtn.textContent = 'Profile';
    loginBtn.setAttribute('href','#/account');
    loginBtn.style.display = (r.nav==='/account') ? 'none' : '';
    loginBtn.onclick = null;
    if(dockLogin){
      dockLogin.querySelector('span').textContent = 'Profile';
      dockLogin.setAttribute('href','#/account');
      dockLogin.dataset.path = '/account';
      dockLogin.onclick = null;
    }
  } else {
    loginBtn.textContent = 'Log in';
    loginBtn.setAttribute('href','#/login');
    loginBtn.onclick = null;
    if(r.nav==='/dashboard'||r.nav==='/login'){ loginBtn.style.display='none'; } else { loginBtn.style.display=''; }
    if(dockLogin){
      dockLogin.querySelector('span').textContent = 'Log in';
      dockLogin.setAttribute('href','#/login');
      dockLogin.dataset.path = '/login';
      dockLogin.onclick = null;
    }
  }
  $all('.nav-link, #mobileNav a, .dock-item[data-path]').forEach(function(a){ a.classList.toggle('active', a.dataset.path===r.nav); });
}
function doLogout(){
  return fetch('api/logout.php',{method:'POST'}).then(function(){ AUTH_USER=null; location.hash='#/'; });
}
function wireAccount(){
  var btn = $('#accountLogoutBtn');
  if(btn){ btn.onclick = function(e){ e.preventDefault(); doLogout(); }; }
}
var transitioning=false;
function resetTransition(){
  transitioning=false;
  wipe.classList.remove('covering');
}
window.addEventListener('hashchange', function(){
  if(transitioning) return;
  transitioning=true;
  if(!motionOK){ try{ doRender(); }catch(e){} transitioning=false; return; }
  wipe.classList.add('covering');
  var safety = setTimeout(resetTransition, 1500);
  setTimeout(function(){
    try{ doRender(); }
    catch(e){}
    finally{
      clearTimeout(safety);
      requestAnimationFrame(function(){
        wipe.classList.remove('covering');
        setTimeout(function(){ transitioning=false; }, 280);
      });
    }
  }, 270);
});
window.addEventListener('resize', function(){ moveNavBlob(currentNavLink()); });
window.addEventListener('pageshow', function(e){ if(e.persisted) resetTransition(); });
document.addEventListener('visibilitychange', function(){ if(!document.hidden && transitioning) resetTransition(); });
refreshAuth().then(doRender);

})();
