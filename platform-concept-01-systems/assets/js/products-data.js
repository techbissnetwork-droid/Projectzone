/* ==========================================================================
   TECHBISS PLATFORM — shared marketplace product data
   Loaded on marketplace.html and marketplace-product.html, before their page
   scripts. Sample/mock data only — no backend.
   ========================================================================== */
window.TECHBISS_PRODUCTS = [
  { id:'ember-table', name:'Ember & Table', industry:'Restaurant',
    tagline:'Full-service restaurant ordering, reservations & delivery.',
    price:349, deployments:1204, released:'2026-03-14', rating:4.8, pattern:'a', accent:'#c9793f', accentRgb:'201,121,63',
    features:['Digital menu with categories','Table reservations','Online ordering & cart','Delivery zone management'] },

  { id:'marbrook-stays', name:'Marbrook Stays', industry:'Hotel',
    tagline:'Room booking, guest check-in and multi-property management.',
    price:459, deployments:812, released:'2026-02-02', rating:4.7, pattern:'b', accent:'#5b7fbf', accentRgb:'91,127,191',
    features:['Room booking engine','Real-time availability calendar','Guest check-in forms','Multi-property support'] },

  { id:'fresh-aisle-market', name:'Fresh Aisle Market', industry:'Retail',
    tagline:'Grocery and general retail storefront with local delivery.',
    price:299, deployments:1489, released:'2026-05-20', rating:4.6, pattern:'c', accent:'#6f9e78', accentRgb:'111,158,120',
    features:['Product catalog & search','Inventory sync','Local delivery zones','Loyalty points display'] },

  { id:'cartline-commerce', name:'Cartline Commerce', industry:'E-commerce',
    tagline:'A complete online store, from catalog to checkout.',
    price:389, deployments:1673, released:'2026-06-11', rating:4.9, pattern:'d', accent:'#9d6fa0', accentRgb:'157,111,160',
    features:['Full cart & checkout','Multi-currency pricing','Abandoned cart recovery','Discount codes engine'] },

  { id:'greenfield-campus', name:'Greenfield Campus', industry:'School',
    tagline:'Admissions, fees and parent communication in one site.',
    price:419, deployments:654, released:'2026-01-18', rating:4.7, pattern:'e', accent:'#4d9aa6', accentRgb:'77,154,166',
    features:['Admissions portal','Fee payment gateway','Parent-teacher portal','Circulars & alerts'] },

  { id:'meridian-health', name:'Meridian Health', industry:'Hospital',
    tagline:'Hospital and multi-specialty care site with scheduling.',
    price:499, deployments:421, released:'2026-07-02', rating:4.6, pattern:'f', accent:'#a3944f', accentRgb:'163,148,79',
    features:['Appointment scheduling','Doctor directory','Patient records access','Emergency contact banner'] },

  { id:'clearwater-clinic', name:'Clearwater Clinic', industry:'Clinic',
    tagline:'Booking-first site for clinics and small practices.',
    price:359, deployments:588, released:'2026-04-09', rating:4.7, pattern:'a', accent:'#b2606f', accentRgb:'178,96,111',
    features:['Online appointment booking','Service & pricing list','Insurance info pages','SMS reminders (mock)'] },

  { id:'bluepoint-realty', name:'Bluepoint Realty', industry:'Real Estate',
    tagline:'Property listings with map search and lead capture.',
    price:409, deployments:733, released:'2026-03-27', rating:4.5, pattern:'b', accent:'#c2a13f', accentRgb:'194,161,63',
    features:['Property listings grid','Map-based search','Inquiry & lead capture','Agent profile pages'] },

  { id:'ironline-builders', name:'Ironline Builders', industry:'Construction',
    tagline:'Project-led site for contractors and construction firms.',
    price:379, deployments:366, released:'2026-02-14', rating:4.5, pattern:'c', accent:'#c9793f', accentRgb:'201,121,63',
    features:['Project portfolio gallery','Service breakdown pages','Quote request form','Timeline & milestones'] },

  { id:'studio-north', name:'Studio North', industry:'Agency',
    tagline:'Portfolio-forward site for creative and digital agencies.',
    price:329, deployments:902, released:'2026-06-30', rating:4.8, pattern:'d', accent:'#5b7fbf', accentRgb:'91,127,191',
    features:['Portfolio / case studies','Team profiles','Client testimonials','Contact & proposal form'] },

  { id:'portfolio-one', name:'Portfolio One', industry:'Freelancer',
    tagline:'A single-page portfolio built to book more work.',
    price:189, deployments:2041, released:'2026-08-05', rating:4.9, pattern:'e', accent:'#6f9e78', accentRgb:'111,158,120',
    features:['Personal portfolio grid','Resume / CV page','Testimonials carousel','Simple contact form'] },

  { id:'launchbase', name:'Launchbase', industry:'Startup',
    tagline:'Pre-launch landing page with waitlist and investor deck.',
    price:259, deployments:1156, released:'2026-05-02', rating:4.7, pattern:'f', accent:'#9d6fa0', accentRgb:'157,111,160',
    features:['Product landing page','Waitlist signup form','Investor / pitch page','Roadmap timeline'] },

  { id:'vantage-corporate', name:'Vantage Corporate', industry:'Corporate',
    tagline:'Multi-department corporate site with careers and press.',
    price:449, deployments:497, released:'2026-01-25', rating:4.6, pattern:'a', accent:'#4d9aa6', accentRgb:'77,154,166',
    features:['Multi-department pages','Leadership team grid','Careers & job listings','Press & media kit'] },

  { id:'waypoint-travel', name:'Waypoint Travel', industry:'Travel',
    tagline:'Tour packages, itineraries and booking inquiries.',
    price:399, deployments:845, released:'2026-07-19', rating:4.6, pattern:'b', accent:'#a3944f', accentRgb:'163,148,79',
    features:['Tour package listings','Itinerary builder pages','Booking inquiry form','Destination galleries'] },

  { id:'ledgerly-finance', name:'Ledgerly Finance', industry:'Finance',
    tagline:'Compliance-aware site for financial service providers.',
    price:469, deployments:512, released:'2026-08-22', rating:4.5, pattern:'c', accent:'#b2606f', accentRgb:'178,96,111',
    features:['Service & product pages','Secure contact forms','Compliance & disclosure pages','Client login gateway'] },

  { id:'fieldwork-pro', name:'Fieldwork Pro', industry:'Service Business',
    tagline:'Quote-and-book site for field and home service businesses.',
    price:319, deployments:967, released:'2026-04-30', rating:4.7, pattern:'d', accent:'#c2a13f', accentRgb:'194,161,63',
    features:['Service area map','Quote request form','Before / after gallery','Review display'] }
];

/* Shared "abstract browser window" preview mockup, used on marketplace cards, the
   preview modal, and the product detail gallery. Purely decorative divs positioned
   by the .pattern-* CSS classes so one small markup block can render several
   distinct-looking compositions. */
window.TECHBISS_PREVIEW_HTML = function(product, opts){
  opts = opts || {};
  var domain = product.id + '.techbiss.site';
  return (
    '<div class="preview-window pattern-' + product.pattern + (opts.large ? ' preview-window-lg' : '') + '" style="--card-accent:' + product.accent + ';--card-accent-rgb:' + product.accentRgb + ';">' +
      '<div class="pw-chrome"><span class="pw-dot"></span><span class="pw-dot"></span><span class="pw-dot"></span><span class="pw-url mono">' + domain + '</span></div>' +
      '<div class="pw-body"><i class="pb-block"></i><i class="pb-block"></i><i class="pb-block"></i><i class="pb-block"></i><i class="pb-block"></i></div>' +
    '</div>'
  );
};

window.TECHBISS_FORMAT_PRICE = function(n){ return '$' + n.toLocaleString('en-US'); };

