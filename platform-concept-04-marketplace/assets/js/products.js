/* ==========================================================================
   TECHBISS Marketplace — shared product catalog + card renderer
   Used by: marketplace.html, index.html (teaser), marketplace-product.html
   (related products), dashboard.html (purchases), installer.html (deploy
   path preview). Keeping data + render logic in one place guarantees the
   same product card everywhere, which is the point of this direction.
   ========================================================================== */
(function(){
  "use strict";

  var INDUSTRIES = [
    'Restaurant','Hotel','Retail','E-commerce','School','Hospital','Clinic',
    'Real Estate','Construction','Agency','Freelancer','Startup','Corporate',
    'Travel','Finance','Service Business'
  ];

  var TINTS = {
    coral:'#c8492a', teal:'#2f6f66', slate:'#3c5a78', olive:'#8a7a2e',
    plum:'#6b3f63', brown:'#7a4a2e', forest:'#3c6b42', charcoal:'#4a453c'
  };

  var PRODUCTS = [
    { id:'ember-table', name:'Ember & Table', mark:'E&T', tint:'coral', layout:1,
      industry:'Restaurant', domain:'emberandtable.com',
      tagline:'Full-service restaurant site with online ordering, table reservations and a photo-led menu.',
      price:249, rating:4.9, reviews:240, badge:'popular', popularity:98, dateAdded:'2026-07-02',
      features:['Menu builder with photo cards','Table reservation calendar','Online ordering + delivery zones','POS-ready checkout','Mobile-first layout'] },
    { id:'plateside-bistro', name:'Plateside Bistro', mark:'PB', tint:'brown', layout:5,
      industry:'Restaurant', domain:'platesidebistro.com',
      tagline:'A lighter, faster restaurant theme built around a QR digital menu and daily specials.',
      price:179, rating:4.6, reviews:150, badge:'new', popularity:74, dateAdded:'2026-08-22',
      features:['Digital menu with QR code','Delivery + pickup toggle','Daily specials banner','Instagram feed embed','Fast mobile load'] },
    { id:'harborline-suites', name:'Harborline Suites', mark:'HS', tint:'slate', layout:5,
      industry:'Hotel', domain:'harborlinesuites.com',
      tagline:'Boutique hotel site with a real booking engine and seasonal rate calendar.',
      price:329, rating:4.8, reviews:165, badge:'popular', popularity:92, dateAdded:'2026-06-14',
      features:['Room booking engine','Rate calendar by season','Guest review showcase','Multi-language ready','Channel-manager ready'] },
    { id:'northfield-market', name:'Northfield Market', mark:'NM', tint:'olive', layout:3,
      industry:'Retail', domain:'northfieldmarket.com',
      tagline:'General retail storefront built for in-store pickup and a simple product catalog.',
      price:199, rating:4.7, reviews:310, badge:null, popularity:81, dateAdded:'2026-05-03',
      features:['Product catalog with variants','In-store pickup toggle','Inventory sync ready','Loyalty points widget','Mobile checkout'] },
    { id:'nova-commerce', name:'Nova Commerce', mark:'NC', tint:'coral', layout:3,
      industry:'E-commerce', domain:'novacommerce.store',
      tagline:'A complete online store theme built for real product volume and recovered carts.',
      price:349, rating:4.9, reviews:480, badge:'popular', popularity:99, dateAdded:'2026-08-01',
      features:['Unlimited product catalog','Multi-step checkout','Discount & coupon engine','Shipping rate rules','Abandoned-cart recovery'] },
    { id:'brightpath-academy', name:'Brightpath Academy', mark:'BA', tint:'slate', layout:4,
      industry:'School', domain:'brightpathacademy.edu',
      tagline:'Admissions-first school website with a parent portal entry point and event calendar.',
      price:279, rating:4.8, reviews:140, badge:null, popularity:77, dateAdded:'2026-04-19',
      features:['Admissions form workflow','Course & faculty directory','Parent portal login screen','Event calendar','Fee payment integration'] },
    { id:'riverside-general', name:'Riverside General', mark:'RG', tint:'teal', layout:2,
      industry:'Hospital', domain:'riversidegeneral.org',
      tagline:'Hospital website with department directory, doctor schedules and a patient portal entry point.',
      price:399, rating:4.9, reviews:96, badge:null, popularity:85, dateAdded:'2026-03-11',
      features:['Department directory','Doctor profiles & schedules','Emergency info banner','Patient portal entry point','HIPAA-ready structure'] },
    { id:'clearview-clinic', name:'Clearview Clinic', mark:'CC', tint:'teal', layout:4,
      industry:'Clinic', domain:'clearviewclinic.com',
      tagline:'Small practice website built around appointment booking and provider bios.',
      price:229, rating:4.8, reviews:175, badge:'new', popularity:79, dateAdded:'2026-08-27',
      features:['Appointment booking','Provider bios','Insurance info pages','Telehealth link block','SMS reminder ready'] },
    { id:'meridian-properties', name:'Meridian Properties', mark:'MP', tint:'brown', layout:3,
      industry:'Real Estate', domain:'meridianproperties.com',
      tagline:'Listings-first real estate theme with filters, a mortgage calculator and lead capture.',
      price:299, rating:4.7, reviews:205, badge:null, popularity:83, dateAdded:'2026-05-28',
      features:['Listing grid with filters','Mortgage calculator widget','Agent profile pages','Virtual tour embed','Lead capture forms'] },
    { id:'ironclad-builders', name:'Ironclad Builders', mark:'IB', tint:'charcoal', layout:1,
      industry:'Construction', domain:'ironcladbuilders.com',
      tagline:'Contractor site built to win quote requests with a project portfolio up front.',
      price:259, rating:4.6, reviews:130, badge:null, popularity:70, dateAdded:'2026-02-17',
      features:['Project portfolio gallery','Quote request form','Service area map','Licensing & insurance badges','Before/after gallery'] },
    { id:'studio-forty', name:'Studio Forty', mark:'SF', tint:'plum', layout:6,
      industry:'Agency', domain:'studioforty.co',
      tagline:'Creative agency theme with case studies, testimonials and a proposal request flow.',
      price:269, rating:4.9, reviews:220, badge:'popular', popularity:94, dateAdded:'2026-07-19',
      features:['Case study templates','Client testimonial wall','Team & capabilities pages','Proposal request flow','Custom cursor-ready layout'] },
    { id:'solo-practice', name:'Solo Practice', mark:'SP', tint:'olive', layout:6,
      industry:'Freelancer', domain:'solopractice.me',
      tagline:'A personal-brand site for independent professionals — portfolio, rates and booking in one.',
      price:129, rating:4.8, reviews:390, badge:null, popularity:88, dateAdded:'2026-06-05',
      features:['Portfolio grid','Services & rate card','Booking / consult calendar','Client testimonials','Invoicing-ready contact form'] },
    { id:'basecamp-labs', name:'Basecamp Labs', mark:'BL', tint:'forest', layout:1,
      industry:'Startup', domain:'basecamplabs.io',
      tagline:'A SaaS-shaped startup landing site with pricing, waitlist capture and a changelog.',
      price:249, rating:4.7, reviews:265, badge:'new', popularity:90, dateAdded:'2026-08-30',
      features:['Product feature sections','Pricing table module','Waitlist capture form','Investor / press kit page','Changelog block'] },
    { id:'vantage-corporate', name:'Vantage Corporate', mark:'VC', tint:'slate', layout:2,
      industry:'Corporate', domain:'vantagecorporate.com',
      tagline:'Enterprise site for governance, investor relations and a global office directory.',
      price:379, rating:4.8, reviews:110, badge:null, popularity:75, dateAdded:'2026-01-22',
      features:['Leadership & governance pages','Investor relations section','Careers & openings board','Global office directory','Compliance document center'] },
    { id:'wanderlux-travel', name:'Wanderlux Travel', mark:'WT', tint:'teal', layout:5,
      industry:'Travel', domain:'wanderluxtravel.com',
      tagline:'Travel agency theme built around tour packages, itineraries and deposit-based booking.',
      price:289, rating:4.9, reviews:190, badge:'popular', popularity:93, dateAdded:'2026-07-27',
      features:['Tour package cards','Itinerary builder view','Booking & deposit flow','Destination gallery','Multi-currency pricing'] },
    { id:'ledgerline-finance', name:'Ledgerline Finance', mark:'LF', tint:'charcoal', layout:2,
      industry:'Finance', domain:'ledgerlinefinance.com',
      tagline:'Financial services site with a secure client-portal entry point and compliance-ready pages.',
      price:359, rating:4.8, reviews:88, badge:null, popularity:72, dateAdded:'2026-03-30',
      features:['Service comparison tables','Secure client portal entry','Appointment scheduling','Compliance disclosures block','Calculator widgets'] },
    { id:'handyhub-services', name:'Handyhub Services', mark:'HH', tint:'brown', layout:4,
      industry:'Service Business', domain:'handyhubservices.com',
      tagline:'Local service-business theme built around booking, quotes and click-to-call.',
      price:189, rating:4.7, reviews:300, badge:'new', popularity:86, dateAdded:'2026-08-12',
      features:['Service area & booking','Quote request form','Before/after gallery','Review aggregator widget','Click-to-call header'] }
  ];

  var FEATURE_GROUPS = {
    Design:['Fully responsive, mobile-first layout','Custom color & type presets','Photo-led content blocks','Dark/light-ready component set'],
    Commerce:['Pricing / catalog display built in','Booking or ordering flow','Lead & inquiry capture forms','Promotions & badges support'],
    Technical:['Domain-ready DNS configuration','Free SSL on deploy','One-click hosting provisioning','Core Web Vitals-tuned markup']
  };
  var TECH_INCLUDED = ['Domain-ready','SSL included','Hosting-ready','Mobile-optimized','Booking module','SEO starter setup'];

  function money(n){ return '$' + n.toLocaleString('en-US'); }

  function starIcon(){
    return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.47 5.53 6.03.6-4.55 4.06 1.33 5.93L10 14.7l-5.28 2.92 1.33-5.93L1.5 7.63l6.03-.6L10 1.5z"/></svg>';
  }

  function badgeMarkup(p){
    if (p.badge === 'popular') return '<span class="badge badge-popular">Popular</span>';
    if (p.badge === 'new') return '<span class="badge badge-new">New</span>';
    return '';
  }

  function previewMarkup(p, layoutOverride){
    var layout = layoutOverride || p.layout;
    return '' +
      '<div class="product-preview" data-layout="' + layout + '" style="--tile:' + TINTS[p.tint] + '">' +
        '<div class="pp-chrome"><span class="pp-dot"></span><span class="pp-dot"></span><span class="pp-dot"></span><span class="pp-url">' + p.domain + '</span></div>' +
        '<div class="pp-body">' +
          '<div class="pp-tile">' + p.mark + '</div>' +
          '<div class="pp-blocks">' +
            '<span class="pp-b pp-b1"></span><span class="pp-b pp-b2"></span><span class="pp-b pp-b3"></span><span class="pp-b pp-b4"></span>' +
          '</div>' +
        '</div>' +
      '</div>';
  }

  /* view: 'grid' (default) | 'list' | 'compact' (mini card for dashboard/installer) */
  function renderCard(p, opts){
    opts = opts || {};
    var view = opts.view || 'grid';
    var featureList = p.features.slice(0, view === 'list' ? 5 : 3);
    var cls = 'product-card product-card--' + view;
    var actions = opts.actions || 'default';

    var actionsHTML;
    if (actions === 'dashboard') {
      actionsHTML = '' +
        '<div class="pc-actions">' +
          '<a class="btn btn-outline btn-sm" href="marketplace-product.html?id=' + p.id + '">Manage</a>' +
          '<a class="btn btn-primary btn-sm" href="installer.html?product=' + p.id + '">Redeploy →</a>' +
        '</div>';
    } else {
      actionsHTML = '' +
        '<div class="pc-actions">' +
          '<a class="btn btn-outline btn-sm" href="marketplace-product.html?id=' + p.id + '#preview" data-preview-link>Preview</a>' +
          '<a class="btn btn-primary btn-sm" href="marketplace-product.html?id=' + p.id + '">View Details</a>' +
        '</div>';
    }

    return '' +
      '<article class="' + cls + '" data-id="' + p.id + '" data-industry="' + p.industry + '" data-price="' + p.price + '" data-popularity="' + p.popularity + '" data-date="' + p.dateAdded + '" data-name="' + p.name.toLowerCase() + '">' +
        '<a class="pc-preview-link" href="marketplace-product.html?id=' + p.id + '" aria-hidden="true" tabindex="-1">' + previewMarkup(p) + '</a>' +
        '<div class="pc-badges">' + badgeMarkup(p) + '</div>' +
        '<div class="pc-body">' +
          '<div class="pc-head">' +
            '<div>' +
              '<a class="pc-name" href="marketplace-product.html?id=' + p.id + '">' + p.name + '</a>' +
              '<span class="tag">' + p.industry + '</span>' +
            '</div>' +
            '<div class="price-chip"><span class="amount">' + money(p.price) + '</span><span class="period">one-time</span></div>' +
          '</div>' +
          '<p class="pc-tagline">' + p.tagline + '</p>' +
          '<div class="rating-chip">' + starIcon() + ' <strong>' + p.rating.toFixed(1) + '</strong> · ' + p.reviews + '+ businesses</div>' +
          '<ul class="pc-features">' + featureList.map(function(f){ return '<li>' + f + '</li>'; }).join('') + '</ul>' +
          actionsHTML +
        '</div>' +
      '</article>';
  }

  function relatedProducts(id, count){
    var current = PRODUCTS.filter(function(p){ return p.id === id; })[0];
    if (!current) return PRODUCTS.slice(0, count || 4);
    var rest = PRODUCTS.filter(function(p){ return p.id !== id; });
    rest.sort(function(a,b){
      var aSame = a.industry === current.industry ? 0 : 1;
      var bSame = b.industry === current.industry ? 0 : 1;
      if (aSame !== bSame) return aSame - bSame;
      return b.popularity - a.popularity;
    });
    return rest.slice(0, count || 4);
  }

  window.TB_PRODUCTS = {
    all: PRODUCTS,
    industries: INDUSTRIES,
    featureGroups: FEATURE_GROUPS,
    techIncluded: TECH_INCLUDED,
    money: money,
    starIcon: starIcon,
    previewMarkup: previewMarkup,
    renderCard: renderCard,
    related: relatedProducts,
    byId: function(id){ return PRODUCTS.filter(function(p){ return p.id === id; })[0] || null; }
  };
})();
