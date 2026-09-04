/* ==========================================================================
   TECHBISS — Warm Authority — marketplace product catalog
   Shared sample data + rendering helpers used by both marketplace.html (the
   grid/filter/sort catalog) and marketplace-product.html (the detail page,
   which reads ?id= against this same array so every card leads somewhere
   real). Frontend-only: no backend, this is illustrative sample data.
   ========================================================================== */
(function(window){
  "use strict";

  var ICONS = {
    check:'<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5l3.5 3.5L16 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    eye:'<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M1.3 10S4.5 4.3 10 4.3 18.7 10 18.7 10 15.5 15.7 10 15.7 1.3 10 1.3 10Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2.6" stroke="currentColor" stroke-width="1.5"/></svg>',
    arrow:'<svg class="arrow" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 12L12 4M12 4H5M12 4V11" stroke="currentColor" stroke-width="2"/></svg>'
  };

  var PRODUCTS = [
    { id:'ember-table', name:'Ember & Table', industry:'Restaurant', pattern:'menu', paletteIndex:1,
      tagline:'Warm, appetite-first design for restaurants that take orders seriously.',
      price:249, popularity:98, dateAdded:'2026-06-01', isNew:false,
      features:['Digital menu builder','Online ordering & delivery','Table reservations'],
      fullFeatures:['Visual menu builder with categories, photos & dietary tags','Online ordering with delivery, pickup & dine-in modes','Real-time table reservation calendar','Daily specials & seasonal menu scheduling','Order notifications by SMS and email','Built-in tipping & split-bill checkout','Multi-location support for small chains'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Booking & reservation module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Menu',pattern:'menu'},{label:'Ordering',pattern:'list'},{label:'Mobile',pattern:'menu'}],
      description:"Ember & Table was designed around one idea: hungry visitors decide fast. The layout leads with your food, gets a table booked or an order placed in a few taps, and carries the same warmth from a first-time visitor's phone to your host stand's tablet." },

    { id:'harbor-stay', name:'Harbor Stay', industry:'Hotel', pattern:'cal', paletteIndex:5,
      tagline:'A calm, image-forward booking experience for boutique hotels.',
      price:349, popularity:87, dateAdded:'2026-07-14', isNew:false,
      features:['Room booking engine','Rate & availability calendar','Guest messaging'],
      fullFeatures:['Live room booking engine with rate calendar','Room type gallery with amenities & occupancy','Seasonal & promotional rate rules','Pre-arrival guest messaging & check-in details','Multi-room, multi-night reservation flow','Deposit & cancellation policy handling','Concierge-style local guide page'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Booking & availability module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Rooms',pattern:'grid'},{label:'Booking',pattern:'cal'},{label:'Mobile',pattern:'cal'}],
      description:"Harbor Stay keeps the focus on the rooms themselves — big, calm imagery and a booking calendar that never feels like a spreadsheet. Built for boutique properties that compete on feel, not just square footage." },

    { id:'corner-market', name:'Corner Market', industry:'Retail', pattern:'grid', paletteIndex:4,
      tagline:'A neighborhood retail storefront with real inventory management.',
      price:279, popularity:91, dateAdded:'2026-04-22', isNew:false,
      features:['Product catalog','Inventory sync','Local pickup & delivery'],
      fullFeatures:['Full product catalog with categories & variants','Live inventory sync across in-store and online','Local delivery radius & scheduled pickup windows','Loyalty-friendly account & order history','Barcode-ready product structure','Low-stock alerts for your team','Seasonal promotions & bundle pricing'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Inventory sync module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Catalog',pattern:'grid'},{label:'Checkout',pattern:'list'},{label:'Mobile',pattern:'grid'}],
      description:"Corner Market brings the neighborhood shop online without losing the neighborhood feel — a straightforward catalog, honest inventory, and a checkout that gets out of the way." },

    { id:'threadline', name:'Threadline', industry:'E-commerce', pattern:'grid', paletteIndex:6,
      tagline:'A fashion-forward storefront built for a growing product catalog.',
      price:399, popularity:95, dateAdded:'2026-08-02', isNew:true,
      features:['Variant & size management','Cart & checkout','Discount & bundle engine'],
      fullFeatures:['Size, color & material variant management','Fast, distraction-free cart & checkout','Discount codes, bundles & tiered pricing','Wishlist & saved-for-later','Size guide & fit-detail templates','Editorial-style lookbook pages','Return & exchange request flow'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Multi-currency ready'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Catalog',pattern:'grid'},{label:'Product',pattern:'list'},{label:'Mobile',pattern:'grid'}],
      description:"Threadline is built for a catalog that keeps growing — clean grid browsing, fast filtering, and a checkout tuned to convert on mobile, where most of your customers actually are." },

    { id:'riverside-prep', name:'Riverside Prep', industry:'School', pattern:'list', paletteIndex:5,
      tagline:'An admissions-first site for schools managing enrollment online.',
      price:449, popularity:78, dateAdded:'2026-03-11', isNew:false,
      features:['Admissions portal','Fee payments','Parent communication'],
      fullFeatures:['Online admissions portal with document upload','Application status tracking for parents','Online fee & tuition payments','School-wide announcement & calendar system','Parent-teacher communication portal','Staff & department directory','Downloadable forms & policy library'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Parent portal module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Admissions',pattern:'list'},{label:'Payments',pattern:'list'},{label:'Mobile',pattern:'list'}],
      description:"Riverside Prep is built around the two things that matter most on a school's website: getting a family through admissions, and keeping every parent in the loop after that." },

    { id:'wellpoint-clinic', name:'Wellpoint Clinic', industry:'Clinic', pattern:'cal', paletteIndex:4,
      tagline:'A reassuring, appointment-first presence for clinics and practices.',
      price:379, popularity:84, dateAdded:'2026-08-20', isNew:true,
      features:['Appointment booking','Patient intake forms','Provider profiles'],
      fullFeatures:['Appointment booking by provider & specialty','Digital patient intake forms','Provider bios, credentials & schedules','Insurance & payment information pages','Automated appointment reminders','Secure document upload for patients','Multi-location clinic support'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Booking & intake module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Providers',pattern:'list'},{label:'Booking',pattern:'cal'},{label:'Mobile',pattern:'cal'}],
      description:"Wellpoint Clinic is designed to lower the anxiety of finding care — clear provider information, an appointment calendar that just works, and intake forms patients can finish before they even arrive." },

    { id:'fairview-realty', name:'Fairview Realty', industry:'Real Estate', pattern:'list', paletteIndex:2,
      tagline:'Listing-led design built to make every property the hero.',
      price:329, popularity:88, dateAdded:'2026-05-09', isNew:false,
      features:['MLS-style listings grid','Mortgage calculator','Agent profiles'],
      fullFeatures:['Searchable, filterable listings grid','Individual property pages with full detail','Built-in mortgage & affordability calculator','Agent & team profile pages','Saved search & new-listing alerts','Neighborhood & school-zone information','Scheduling for private showings'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Listings search module','CRM-ready lead capture'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Listings',pattern:'list'},{label:'Property',pattern:'grid'},{label:'Mobile',pattern:'list'}],
      description:"Fairview Realty puts the property first on every screen — a fast listings grid, an honest mortgage calculator, and agent pages that build trust before the first showing." },

    { id:'bedrock-build', name:'Bedrock & Build', industry:'Construction', pattern:'hero', paletteIndex:3,
      tagline:'A credibility-first site for contractors and construction firms.',
      price:299, popularity:72, dateAdded:'2026-02-27', isNew:false,
      features:['Project portfolio','Quote request forms','Licensing & insurance display'],
      fullFeatures:['Project portfolio with before/after galleries','Detailed quote & estimate request forms','Licensing, insurance & certification display','Service area & capability breakdown','Client testimonial & review section','Safety record & crew credentials page','Downloadable project spec sheets'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Lead capture forms','Gallery & portfolio module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Projects',pattern:'grid'},{label:'Quote Request',pattern:'list'},{label:'Mobile',pattern:'hero'}],
      description:"Bedrock & Build is built to win trust before the first phone call — real project galleries, licensing up front, and a quote form that gets your team the details it actually needs." },

    { id:'northline-studio', name:'Northline Studio', industry:'Agency', pattern:'hero', paletteIndex:6,
      tagline:'A portfolio-forward site built for agencies that pitch on craft.',
      price:259, popularity:80, dateAdded:'2026-07-30', isNew:false,
      features:['Case study templates','Client testimonials','Proposal request flow'],
      fullFeatures:['Structured case-study templates with results','Client testimonial & logo wall','Guided proposal & new-business request flow','Team & capability pages','Process & methodology walkthrough','Press & recognition section','Careers page for growing teams'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Case study module','Contact & proposal routing'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Work',pattern:'grid'},{label:'Case Study',pattern:'list'},{label:'Mobile',pattern:'hero'}],
      description:"Northline Studio is a portfolio that argues your case for you — case studies structured around outcomes, not just screenshots, and a proposal flow that qualifies leads before they hit your inbox." },

    { id:'solo-desk', name:'Solo Desk', industry:'Freelancer', pattern:'hero', paletteIndex:1,
      tagline:'A lightweight, personal-brand site for independent professionals.',
      price:149, popularity:93, dateAdded:'2026-08-25', isNew:true,
      features:['Services & rate card','Booking & availability','Simple invoicing links'],
      fullFeatures:['Clean services & rate card page','Personal availability & booking calendar','Simple shareable invoice links','Portfolio or work-sample gallery','Client testimonial section','About page built for a single voice','Contact form with project-type routing'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Booking module','Lightweight & fast by default'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Services',pattern:'list'},{label:'Booking',pattern:'cal'},{label:'Mobile',pattern:'hero'}],
      description:"Solo Desk is the smallest site in the catalog on purpose — everything an independent professional needs to look credible and get booked, nothing that needs a team to maintain." },

    { id:'launchpad-01', name:'Launchpad 01', industry:'Startup', pattern:'hero', paletteIndex:5,
      tagline:'A fast, investor-ready launch site for early-stage companies.',
      price:219, popularity:90, dateAdded:'2026-08-29', isNew:true,
      features:['Waitlist capture','Product roadmap teaser','Press & investor kit'],
      fullFeatures:['Waitlist capture with email confirmation flow','Public product roadmap teaser','Press kit & investor one-pager download','Founder & team story section','Changelog / build-in-public log','Social proof & early-user quotes','Simple pricing teaser for launch day'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Waitlist & email capture module','Analytics-ready structure'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Roadmap',pattern:'list'},{label:'Waitlist',pattern:'hero'},{label:'Mobile',pattern:'hero'}],
      description:"Launchpad 01 is built for the weeks before and after you go live — a waitlist that actually captures interest, a roadmap that builds confidence, and a press kit ready the day a journalist asks." },

    { id:'meridian-corp', name:'Meridian & Co.', industry:'Corporate', pattern:'list', paletteIndex:5,
      tagline:'A structured, trust-building site for established corporate teams.',
      price:429, popularity:68, dateAdded:'2026-01-18', isNew:false,
      features:['Leadership & investor pages','Careers board','Global office directory'],
      fullFeatures:['Leadership & governance pages','Investor relations section','Careers board with department filtering','Global office & contact directory','Corporate responsibility & sustainability page','Press releases & media library','Multi-region content structure'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Careers board module','VPS / cloud infrastructure ready'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Leadership',pattern:'list'},{label:'Careers',pattern:'list'},{label:'Mobile',pattern:'list'}],
      description:"Meridian & Co. is built for organizations that need to look as considered as they are — a structured, document-like calm that scales across offices, regions and departments." },

    { id:'farflung-travel', name:'Farflung Travel Co.', industry:'Travel', pattern:'grid', paletteIndex:2,
      tagline:'A destination-led booking experience for travel businesses.',
      price:359, popularity:82, dateAdded:'2026-06-19', isNew:false,
      features:['Trip & package listings','Itinerary builder','Booking & deposits'],
      fullFeatures:['Destination & trip package listings','Day-by-day itinerary builder','Booking flow with deposit & balance payments','Traveler reviews & photo galleries','Group & custom trip request form','Seasonal availability calendar','Travel document & requirement checklist'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Booking & itinerary module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Destinations',pattern:'grid'},{label:'Itinerary',pattern:'list'},{label:'Mobile',pattern:'grid'}],
      description:"Farflung Travel Co. sells the destination first — rich trip listings, a day-by-day itinerary a traveler can actually picture, and a deposit-then-balance booking flow that matches how trips really get paid for." },

    { id:'ledger-finance', name:'Ledger & Finch', industry:'Finance', pattern:'hero', paletteIndex:5,
      tagline:'A precise, confidence-building site for financial service firms.',
      price:389, popularity:75, dateAdded:'2026-04-05', isNew:false,
      features:['Service & advisory pages','Secure document intake','Consultation scheduling'],
      fullFeatures:['Service & advisory offering pages','Secure client document intake','Consultation scheduling with prep questionnaire','Advisor profiles & credentials','Market insight / articles section','Compliance & disclosure page templates','Client portal login placeholder'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Secure form handling','Booking module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Services',pattern:'list'},{label:'Scheduling',pattern:'cal'},{label:'Mobile',pattern:'hero'}],
      description:"Ledger & Finch reads as careful before it reads as anything else — precise typography, secure document intake, and a consultation flow built for a decision that matters." },

    { id:'primehouse-legal', name:'Primehouse Legal', industry:'Service Business', pattern:'hero', paletteIndex:6,
      tagline:'A composed, authoritative site for law and professional services.',
      price:369, popularity:70, dateAdded:'2026-03-30', isNew:false,
      features:['Practice area pages','Case intake form','Attorney directory'],
      fullFeatures:['Practice area pages with outcomes focus','Structured case & consultation intake form','Attorney directory with specialties','Client result summaries (case-by-case)','Resource library & FAQs','Office locations & scheduling','Confidential contact routing'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Secure intake forms','Business email included'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Practice Areas',pattern:'list'},{label:'Intake',pattern:'list'},{label:'Mobile',pattern:'hero'}],
      description:"Primehouse Legal is composed on purpose — a calm, authoritative layout, a case intake form that gathers what your team actually needs, and an attorney directory that reads like a firm, not a listing." },

    { id:'petal-and-pine', name:'Petal & Pine', industry:'Retail', pattern:'grid', paletteIndex:4,
      tagline:'A boutique florist storefront built for same-day local orders.',
      price:229, popularity:86, dateAdded:'2026-08-10', isNew:true,
      features:['Occasion-based catalog','Same-day delivery scheduling','Subscription bouquets'],
      fullFeatures:['Occasion-based catalog (birthday, sympathy, everyday)','Same-day & scheduled delivery windows','Recurring subscription bouquet plans','Custom order & card message notes','Local delivery radius map','Corporate & event order form','Care-instructions page per flower type'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','Online payment integration','Delivery scheduling module'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Catalog',pattern:'grid'},{label:'Delivery',pattern:'cal'},{label:'Mobile',pattern:'grid'}],
      description:"Petal & Pine is built for a business that lives and dies by same-day delivery — an occasion-first catalog, a delivery scheduler that's honest about cutoff times, and subscriptions for repeat customers." },

    { id:'stonebridge-health', name:'Stonebridge Health', industry:'Hospital', pattern:'list', paletteIndex:3,
      tagline:'A department-organized site for hospitals and multi-provider health systems.',
      price:459, popularity:65, dateAdded:'2026-02-14', isNew:false,
      features:['Department directory','Physician finder','Patient portal login'],
      fullFeatures:['Full department & service line directory','Physician finder with specialty filtering','Patient portal login entry point','Visiting hours & location information per campus','Insurance & billing information pages','Emergency & urgent care guidance','Multi-campus navigation structure'],
      techIncluded:['Domain-ready','Free SSL certificate','Premium hosting included (first year)','Mobile-optimized on every screen','VPS / cloud infrastructure ready','Business email included'],
      gallery:[{label:'Home',pattern:'hero'},{label:'Departments',pattern:'list'},{label:'Find a Doctor',pattern:'list'},{label:'Mobile',pattern:'list'}],
      description:"Stonebridge Health is organized the way patients actually search — by department and by doctor — so a multi-campus system can stay easy to navigate instead of turning into a maze." }
  ];

  function previewInner(pattern){
    if (pattern === 'hero') {
      return '<div class="pv-hero-block"></div><div class="pv-line w60"></div><div class="pv-line w40"></div><div class="pv-btn"></div>';
    }
    if (pattern === 'grid') {
      var tiles = '';
      for (var i = 0; i < 6; i++) tiles += '<div class="pv-tile"></div>';
      return '<div class="pv-tiles">' + tiles + '</div>';
    }
    if (pattern === 'list') {
      var rows = '';
      for (var r = 0; r < 4; r++) rows += '<div class="pv-row"><div class="pv-row-thumb"></div><div class="pv-row-lines"><span></span><span></span></div></div>';
      return rows;
    }
    if (pattern === 'cal') {
      var on = [2, 5, 9, 13, 16, 19];
      var cells = '';
      for (var c = 0; c < 21; c++) cells += '<i class="' + (on.indexOf(c) > -1 ? 'on' : '') + '"></i>';
      return '<div class="pv-cal">' + cells + '</div>';
    }
    if (pattern === 'menu') {
      var menu = '';
      for (var m = 0; m < 5; m++) menu += '<div class="pv-menu-row"><span></span><span></span></div>';
      return menu;
    }
    return '';
  }

  function previewHTML(product, size){
    var pattern = product.pattern;
    return '<div class="product-preview' + (size === 'lg' ? ' is-lg' : '') + ' pat-' + pattern + '" data-pv="' + product.paletteIndex + '" aria-hidden="true">' +
      '<div class="pv-chrome"><span></span><span></span><span></span><i></i></div>' +
      '<div class="pv-body">' + previewInner(pattern) + '</div>' +
    '</div>';
  }

  function galleryFrameHTML(product, entry){
    return '<div class="product-preview is-lg pat-' + entry.pattern + '" data-pv="' + product.paletteIndex + '" aria-hidden="true">' +
      '<div class="pv-chrome"><span></span><span></span><span></span><i></i></div>' +
      '<div class="pv-body">' + previewInner(entry.pattern) + '</div>' +
    '</div>';
  }

  function escapeAttr(str){ return String(str).replace(/"/g, '&quot;'); }

  function cardHTML(product){
    var searchBlob = escapeAttr((product.name + ' ' + product.industry + ' ' + product.tagline + ' ' + product.features.join(' ')).toLowerCase());
    return (
      '<article class="product-card" data-product-card data-id="' + product.id + '" data-industry="' + escapeAttr(product.industry) + '" ' +
      'data-price="' + product.price + '" data-popularity="' + product.popularity + '" data-date="' + product.dateAdded + '" data-search="' + searchBlob + '">' +
        '<div style="position:relative;">' +
          (product.isNew ? '<span class="new-flag">New</span>' : '') +
          previewHTML(product) +
        '</div>' +
        '<div class="body">' +
          '<div class="product-top-row"><h3>' + product.name + '</h3><span class="industry-tag">' + product.industry + '</span></div>' +
          '<p class="product-tagline">' + product.tagline + '</p>' +
          '<ul class="product-features">' + product.features.map(function(f){ return '<li>' + ICONS.check + '<span>' + f + '</span></li>'; }).join('') + '</ul>' +
          '<div class="product-foot">' +
            '<div class="product-price">$' + product.price + '<span> one-time</span></div>' +
            '<div class="product-actions">' +
              '<button type="button" class="icon-btn" data-preview-btn data-id="' + product.id + '" aria-label="Preview ' + escapeAttr(product.name) + '">' + ICONS.eye + '</button>' +
              '<a class="btn btn-outline btn-sm" href="marketplace-product.html?id=' + product.id + '">View Details</a>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  function getById(id){
    for (var i = 0; i < PRODUCTS.length; i++) if (PRODUCTS[i].id === id) return PRODUCTS[i];
    return null;
  }

  function relatedProducts(product, count){
    count = count || 4;
    var sameIndustry = PRODUCTS.filter(function(p){ return p.id !== product.id && p.industry === product.industry; });
    var others = PRODUCTS.filter(function(p){ return p.id !== product.id && p.industry !== product.industry; })
      .sort(function(a, b){ return b.popularity - a.popularity; });
    return sameIndustry.concat(others).slice(0, count);
  }

  window.TB = window.TB || {};
  window.TB.products = PRODUCTS;
  window.TB.icons = ICONS;
  window.TB.previewHTML = previewHTML;
  window.TB.galleryFrameHTML = galleryFrameHTML;
  window.TB.cardHTML = cardHTML;
  window.TB.getById = getById;
  window.TB.relatedProducts = relatedProducts;
})(window);
