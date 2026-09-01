-- =====================================================================
-- TECHBISS — baseline installation data
-- Roles, permissions, settings, navigation, pages, services, industries,
-- packages, taxonomies, process steps and FAQs.
--
-- Intentionally NOT seeded: testimonials, portfolio projects and statistics.
-- Those describe real clients and real numbers, so they start empty and are
-- filled in from the admin panel. See database/demo-content.sql for optional,
-- clearly-fictional sample data to explore the CMS with.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Roles & permissions
-- ---------------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
('Super Admin',      'super-admin',      'Full access to every area of the platform.',                 1, NOW(), NOW()),
('Content Manager',  'content-manager',  'Pages, services, portfolio, blog, testimonials and FAQs.',   1, NOW(), NOW()),
('Sales Manager',    'sales-manager',    'Leads, requests and customers.',          1, NOW(), NOW()),
('Support Manager',  'support-manager',  'Customer records, purchases and support information.',       1, NOW(), NOW());

INSERT INTO `permissions` (`name`, `slug`, `group_name`, `sort_order`) VALUES
('View dashboard',        'dashboard.view',    'Dashboard', 1),
('View content',          'content.view',      'Content',   10),
('Manage content',        'content.manage',    'Content',   11),
('Manage portfolio',      'portfolio.manage',  'Content',   12),
('Manage blog',           'blog.manage',       'Content',   13),
('Manage media library',  'media.manage',      'Content',   14),
('Manage navigation',     'navigation.manage', 'Website',   20),
('Manage SEO',            'seo.manage',        'Website',   21),
('Manage global settings','settings.manage',   'Website',   22),
('Manage premade projects','projects.manage',  'Commerce',  33),
('Manage project enquiries','project_orders.manage','Commerce', 34),
('Manage client portal requests','client_requests.manage','Commerce', 35),
('Manage customers',      'customers.manage',  'Commerce',  32),
('View leads',            'leads.view',        'Leads',     40),
('Manage leads',          'leads.manage',      'Leads',     41),
('Export data',           'export.manage',     'System',    50),
('Download a full database backup','system.backup','System',    54),
('View activity logs',    'logs.view',         'System',    51),
('Manage admin users',    'users.manage',      'System',    52),
('Manage roles',          'roles.manage',      'System',    53);

-- Super Admin → everything
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.slug = 'super-admin';

-- Content Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
  ON p.slug IN ('dashboard.view','content.view','content.manage','portfolio.manage','projects.manage','blog.manage','media.manage','seo.manage')
WHERE r.slug = 'content-manager';

-- Sales Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
  ON p.slug IN ('dashboard.view','leads.view','leads.manage','project_orders.manage','customers.manage','export.manage','content.view')
WHERE r.slug = 'sales-manager';

-- Support Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
  ON p.slug IN ('dashboard.view','leads.view','customers.manage','project_orders.manage','client_requests.manage','content.view')
WHERE r.slug = 'support-manager';

-- ---------------------------------------------------------------------
-- Settings
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`, `hint`, `sort_order`, `updated_at`) VALUES
-- General
('general','site_name','TECHBISS','text','Company name','',1,NOW()),
('general','tagline','Your Digital Business Starts Here.','text','Tagline','Shown in the hero and browser title.',2,NOW()),
('general','brand_promise','Offline business to online, plus the app you have in mind.','textarea','Brand promise','',3,NOW()),
('general','logo','','image','Logo','SVG or PNG with transparent background.',4,NOW()),
('general','logo_light','','image','Logo (light backgrounds)','',5,NOW()),
('general','favicon','','image','Favicon','32×32 or 48×48 PNG / ICO.',6,NOW()),
('general','footer_text','One partner for everything your business needs online: domain, hosting, website, apps, email, branding, SEO and support.','textarea','Footer intro',' ',7,NOW()),
('general','copyright','© {year} TECHBISS. All rights reserved.','text','Copyright line','Use {year} for the current year.',8,NOW()),
-- Contact
('contact','contact_email','hello@techbiss.com','text','Public contact email','Shown on the website and used on the contact page. Not your sign-in address.',1,NOW()),
('contact','sales_email','','text','Sales email','For new enquiries. Leave blank to use the public contact email.',2,NOW()),
('contact','support_email','','text','Support email','For existing clients. Shown on the contact page only when it differs from the public one.',3,NOW()),
('contact','contact_phone','','text','Phone number','',4,NOW()),
('contact','whatsapp','','text','WhatsApp number or chat link','A phone number with country code (e.g. 8801711223344), or a full chat link (wa.me/… or a WhatsApp Business link) — either works.',5,NOW()),
('contact','address','','textarea','Address','',6,NOW()),
('contact','country','','text','Country','',7,NOW()),
('contact','business_hours','Monday – Friday, 09:00 – 18:00','text','Business hours','',8,NOW()),
('contact','map_embed','','textarea','Map embed URL','Optional Google Maps embed src.',9,NOW()),
-- Social
('social','social_linkedin','','text','LinkedIn URL','',1,NOW()),
('social','social_x','','text','X / Twitter URL','',2,NOW()),
('social','social_facebook','','text','Facebook URL','',3,NOW()),
('social','social_instagram','','text','Instagram URL','',4,NOW()),
('social','social_youtube','','text','YouTube URL','',5,NOW()),
('social','social_github','','text','GitHub URL','',6,NOW()),
('social','social_dribbble','','text','Dribbble URL','',7,NOW()),
-- SEO
('seo','seo_default_title','TECHBISS — Your Digital Business Starts Here','text','Default SEO title','',1,NOW()),
('seo','seo_default_description','We take offline businesses online and build the app you have in mind. Websites, mobile apps, hosting, business email, branding and SEO from one partner.','textarea','Default meta description','Aim for 150–160 characters.',2,NOW()),
('seo','seo_title_suffix',' | TECHBISS','text','Title suffix','Appended to page titles.',3,NOW()),
('seo','seo_og_image','assets/images/brand/og-image.png','image','Default social share image','1200×630 recommended.',4,NOW()),
('seo','google_analytics_id','','text','Google Analytics ID','e.g. G-XXXXXXXXXX',5,NOW()),
('seo','google_site_verification','','text','Google Search Console token','',6,NOW()),
('seo','bing_site_verification','','text','Bing verification token','',7,NOW()),
('seo','robots_extra','','textarea','Extra robots.txt rules','Appended to the generated robots.txt.',8,NOW()),
-- Appearance
('appearance','theme_mode','dark','select','Default theme','dark or light.',1,NOW()),
('appearance','allow_theme_toggle','1','bool','Show theme toggle','',2,NOW()),
('appearance','enable_loader','1','bool','Branded loading screen','',3,NOW()),
('appearance','enable_cursor','1','bool','Desktop cursor enhancement','Automatically disabled on touch devices.',4,NOW()),
('appearance','enable_transitions','1','bool','Page transitions','',5,NOW()),
-- Commerce
('commerce','public_pricing','0','bool','Show prices on the website','Off by default: work is priced in conversation. Turn on only if you want figures published.',0,NOW()),
('commerce','currency','USD','text','Currency code','',1,NOW()),
('commerce','currency_symbol','$','text','Currency symbol','',2,NOW()),
-- System
('system','maintenance_mode','0','bool','Maintenance mode','',1,NOW()),
('system','maintenance_message','We are performing scheduled maintenance and will be back shortly.','textarea','Maintenance message','',2,NOW()),
('system','items_per_page','9','text','Items per page','Public listings.',3,NOW()),
('system','notify_new_lead','1','bool','Email me about new leads','',4,NOW()),
('system','notification_email','','text','Send notifications to','Where the site emails you when an enquiry arrives. Never shown publicly. Defaults to the public contact email.',5,NOW());

-- ---------------------------------------------------------------------
-- Navigation
-- ---------------------------------------------------------------------
INSERT INTO `navigation` (`menu`,`parent_id`,`label`,`url`,`link_type`,`description`,`target`,`is_active`,`is_button`,`sort_order`,`created_at`,`updated_at`) VALUES
('primary',NULL,'Services','/services','internal','What we build and run for you','_self',1,0,1,NOW(),NOW()),
('primary',NULL,'Ready Projects','/premade-projects','internal','Live builds you can launch fast','_self',1,0,3,NOW(),NOW()),
('primary',NULL,'Work','/portfolio','internal','Projects we have delivered','_self',1,0,4,NOW(),NOW()),
('primary',NULL,'Industries','/industries','internal','Built around your sector','_self',1,0,5,NOW(),NOW()),
('primary',NULL,'Company','','internal','','_self',1,0,6,NOW(),NOW()),
('primary',NULL,'Tell Us What You Need','/request','internal','','_self',1,1,9,NOW(),NOW()),
('footer',NULL,'Services','/services','internal','','_self',1,0,1,NOW(),NOW()),
('footer',NULL,'Tell us what you need','/request','internal','','_self',1,0,2,NOW(),NOW()),
('footer',NULL,'Ready Projects','/premade-projects','internal','','_self',1,0,3,NOW(),NOW()),
('footer',NULL,'Work','/portfolio','internal','','_self',1,0,4,NOW(),NOW()),
('footer',NULL,'Industries','/industries','internal','','_self',1,0,5,NOW(),NOW()),
('footer',NULL,'How It Works','/how-it-works','internal','','_self',1,0,6,NOW(),NOW()),
('footer',NULL,'Why TECHBISS','/why-techbiss','internal','','_self',1,0,7,NOW(),NOW()),
('footer',NULL,'Blog','/blog','internal','','_self',1,0,8,NOW(),NOW()),
('footer',NULL,'About','/about','internal','','_self',1,0,9,NOW(),NOW()),
('footer',NULL,'Testimonials','/testimonials','internal','','_self',1,0,10,NOW(),NOW()),
('footer',NULL,'FAQs','/faqs','internal','','_self',1,0,11,NOW(),NOW()),
('footer',NULL,'Contact','/contact','internal','','_self',1,0,12,NOW(),NOW()),
('legal',NULL,'Privacy Policy','/privacy-policy','internal','','_self',1,0,1,NOW(),NOW()),
('legal',NULL,'Terms & Conditions','/terms-and-conditions','internal','','_self',1,0,2,NOW(),NOW());

-- Company dropdown children
INSERT INTO `navigation` (`menu`,`parent_id`,`label`,`url`,`link_type`,`description`,`target`,`is_active`,`is_button`,`sort_order`,`created_at`,`updated_at`)
SELECT 'primary', n.id, v.label, v.url, 'internal', v.descr, '_self', 1, 0, v.ord, NOW(), NOW()
FROM `navigation` n
JOIN (
  SELECT 'About' AS label, '/about' AS url, 'Who we are and how we work' AS descr, 1 AS ord
  UNION ALL SELECT 'How It Works', '/how-it-works', 'The six-stage transformation', 2
  UNION ALL SELECT 'Why TECHBISS', '/why-techbiss', 'What makes us different', 3
  UNION ALL SELECT 'Blog', '/blog', 'Guides on going digital', 4
  UNION ALL SELECT 'Testimonials', '/testimonials', 'What clients say', 5
  UNION ALL SELECT 'FAQs', '/faqs', 'Answers to common questions', 6
  UNION ALL SELECT 'Contact', '/contact', 'Talk to the team', 7
) v
WHERE n.menu = 'primary' AND n.label = 'Company' AND n.parent_id IS NULL;

-- ---------------------------------------------------------------------
-- Services
-- ---------------------------------------------------------------------
-- Domain, hosting, the site and email are one job, never sold separately, so
-- they are one service rather than four. Same for web and mobile apps.
INSERT INTO `services` (`slug`,`name`,`tagline`,`short_description`,`description`,`icon`,`accent`,`deliverables`,`is_featured`,`is_published`,`sort_order`,`seo_title`,`seo_description`,`created_at`,`updated_at`) VALUES
('business-website','Website Design & Setup','We build the website — any kind, from scratch — then set up everything it needs to run.','A custom website, designed and built around your business, not a template. Then everything it needs to run: domain, hosting, DNS and SSL, professional email on your own domain, and an online store if you sell products. Built and launched together, not sold piece by piece.','<p>Whatever site you need — a marketing site, a booking or ordering site, a full online store — we design and build it around what your business does and what a visitor needs to decide, responsive from the first breakpoint. This is not a template with your logo dropped in.</p><p>Once it is built, we set up everything around it: your domain registered in your name, DNS pointed correctly, managed hosting with SSL installed and renewed automatically, and professional email on your own domain. If you sell products, the store — catalogue, checkout, payments — is built into the same site. One job, one team, one thing to maintain.</p>','window','violet','Custom-designed, responsive website — built for what your business does, not a template\nEditable CMS so you can update content yourself\nDomain registered in your name, DNS configured\nManaged hosting with SSL installed and auto-renewed\nProfessional email on your domain (SPF, DKIM, DMARC configured)\nOnline store with catalogue, checkout and payments, if you sell products\nContact and enquiry forms\nDaily backups and uptime monitoring\nAnalytics and Search Console setup',1,1,1,'Custom Website Design, Domain, Hosting & Email Setup','A custom-built website plus domain registration, managed hosting with SSL and business email — built and launched as one job.',NOW(),NOW()),
('web-applications','Web & Mobile Apps','The app you have in mind — for the web, or for Android and iOS.','Booking systems, portals, dashboards and internal tools for the web, or a customer-facing app for Android and iOS — whichever fits what you actually need built.','<p>When a spreadsheet stops scaling, or the thing you need only makes sense on a phone, this is that. We map the workflow or the idea first, tell you what it takes to build and what can wait for later, then build it — a web application with the reporting and access control you need, or a native app shipped to both app stores.</p><p>Apps we build most: booking and ordering, loyalty, field data capture, and internal tools for a team that works away from a desk.</p>','layers','blue','Workflow or idea mapped before any code is written\nCustom-built web application, or native builds for Android and iOS\nRole-based access and admin dashboards\nApp store submission and release management, for mobile builds\nPush notifications and offline-capable data, for mobile builds\nReporting, exports and third-party integrations',1,1,2,'Custom Web & Mobile App Development','Web applications and native Android/iOS apps built around the workflow or idea you bring us.',NOW(),NOW()),
('branding','Branding','A professional identity and a visual system that holds together.','Logo, colour, type and the rules that keep everything consistent as you grow.','<p>Branding is not a logo file. It is the set of decisions that make every touchpoint recognisably yours — and the documentation that lets anyone apply them correctly.</p>','palette','rose','Logo design and variants\nColour and typography system\nBrand guidelines document\nStationery and social templates\nAsset library',0,1,3,'Business Branding & Identity Design','Logo, colour, typography and brand guidelines that keep your business consistent everywhere.',NOW(),NOW()),
('seo','SEO','Technical and content-focused search optimisation.','Make it possible for customers to find you — with technical fixes and content that answers real queries.','<p>We audit what is holding the site back technically, fix it, then build the content and structure that lets search engines understand what you do and where you do it. Reporting is honest: rankings move over months, not days.</p>','search','violet','Technical SEO audit and fixes\nKeyword and intent research\nOn-page optimisation\nLocal and map listings\nSchema markup\nMonthly performance reporting',1,1,4,'SEO Services','Technical SEO, content structure and local search work that makes your business findable.',NOW(),NOW()),
('automation-ai','Automation & AI','Smarter workflows and less manual work.','Connect the tools you already use and let the repetitive work happen on its own.','<p>Most businesses lose hours a week to copying data between systems. We connect them, add automated follow-ups and reporting, and apply AI where it genuinely helps — support triage, content drafting, document extraction.</p>','spark','emerald','Workflow automation\nCRM and tool integrations\nAutomated notifications and follow-ups\nAI assistants and chat\nDocument processing\nScheduled reporting',0,1,5,'Business Automation & AI Solutions','Workflow automation, integrations and practical AI that remove repetitive work from your team.',NOW(),NOW()),
('maintenance','Maintenance','Ongoing technical support and maintenance.','Updates, backups, monitoring and a real person to contact when something is wrong.','<p>Software that is never updated becomes a liability. We keep the stack patched, take verified backups, monitor uptime and respond when something breaks — with a response time you can plan around.</p>','shield','blue','Security patching and updates\nVerified daily backups\nUptime and error monitoring\nPerformance tuning\nContent updates\nPriority support channel',1,1,6,'Website Maintenance & Support','Patching, backups, monitoring and responsive technical support for your digital systems.',NOW(),NOW());

INSERT INTO `service_features` (`service_id`,`title`,`description`,`sort_order`)
SELECT s.id, f.title, f.descr, f.ord FROM `services` s JOIN (
  SELECT 'business-website' AS sl,'Built around your customers' AS title,'Structure follows what a visitor needs to decide.' AS descr,1 AS ord
  UNION ALL SELECT 'business-website','Editable without a developer','Content, images and SEO managed from the admin panel.',2
  UNION ALL SELECT 'business-website','Owned in your name','The domain is registered to your business, not to us.',3
  UNION ALL SELECT 'business-website','Deliverability configured','SPF, DKIM and DMARC set correctly from day one.',4
  UNION ALL SELECT 'web-applications','Workflow first','We map the process before writing any code.',1
  UNION ALL SELECT 'web-applications','Role-based access','Each team member sees only what they should.',2
  UNION ALL SELECT 'web-applications','Ships to both app stores','Android and iOS, submitted and managed through release.',3
  UNION ALL SELECT 'seo','Technical foundation first','Crawling, indexing and speed before content.',1
  UNION ALL SELECT 'seo','Honest reporting','Real positions and traffic, no vanity metrics.',2
  UNION ALL SELECT 'maintenance','Defined response times','You know how quickly we will reply.',1
) f ON f.sl = s.slug;

-- ---------------------------------------------------------------------
-- Industries
-- ---------------------------------------------------------------------
INSERT INTO `industries` (`slug`,`name`,`tagline`,`short_description`,`description`,`icon`,`is_featured`,`is_published`,`sort_order`,`seo_title`,`seo_description`,`created_at`,`updated_at`) VALUES
('restaurants','Restaurants','Menus, bookings and orders that work on a phone.','Digital menus, online reservations, ordering and the local search presence that fills tables.','<p>Most restaurant discovery now happens on a phone, on a map, minutes before the decision. We make sure the menu loads instantly, the booking takes three taps and the listing that surfaces you is accurate.</p>','utensils',1,1,1,'Digital Solutions for Restaurants','Digital menus, online reservations, ordering systems and local search for restaurants and cafés.',NOW(),NOW()),
('hotels','Hotels & Hospitality','Direct bookings instead of commission.','Booking engines, property sites and the content that convinces a guest to book with you directly.','<p>Every booking through an aggregator costs you margin. A property site that answers the guest''s real questions — rooms, rates, location, cancellation — moves a share of that volume back to you.</p>','bed',1,1,2,'Digital Solutions for Hotels','Booking engines, property websites and direct-booking strategy for hotels and guest houses.',NOW(),NOW()),
('retail','Retail','From shelf to storefront online.','Catalogues, e-commerce, click-and-collect and stock that stays in step.','<p>Retail online is an inventory problem before it is a design problem. We build stores that reflect what is actually on the shelf, with fulfilment that your team can run.</p>','shop',1,1,3,'Digital Solutions for Retail','E-commerce, catalogues and click-and-collect systems for retail businesses going online.',NOW(),NOW()),
('education','Education','Enrolment, learning and communication in one place.','Institution websites, admissions flows, portals and learning platforms.','<p>Parents and students judge an institution by its website before they ever visit. We build sites that answer the admissions questions clearly and portals that reduce administrative load.</p>','graduation',0,1,4,'Digital Solutions for Education','Websites, admissions systems and learning portals for schools, colleges and training providers.',NOW(),NOW()),
('healthcare','Healthcare','Appointments, records and trust.','Clinic websites, appointment booking and patient communication built with privacy in mind.','<p>Healthcare digital work carries a higher bar: accessibility, clarity and careful handling of personal data. We build to that standard.</p>','heartbeat',0,1,5,'Digital Solutions for Healthcare','Clinic websites, appointment booking and patient portals built with privacy and accessibility in mind.',NOW(),NOW()),
('real-estate','Real Estate','Listings that sell before the viewing.','Property portals, listing management, virtual tours and lead capture.','<p>A listing either creates a viewing or it does not. We build property sites where search works, media loads fast and enquiries reach the right agent immediately.</p>','key',1,1,6,'Digital Solutions for Real Estate','Property portals, listing management and lead capture for estate agencies and developers.',NOW(),NOW()),
('construction','Construction','Show the work, win the tender.','Project portfolios, capability statements and enquiry systems for contractors.','<p>Construction sells on evidence. We build sites that present completed projects, certifications and capability in a form a procurement team can act on.</p>','hardhat',0,1,7,'Digital Solutions for Construction','Project portfolios and enquiry systems for contractors, builders and engineering firms.',NOW(),NOW()),
('travel','Travel & Tourism','Itineraries, enquiries and bookings.','Tour operator sites, itinerary builders and enquiry-to-booking flows.','<p>Travel buyers compare relentlessly. Clear itineraries, honest pricing and a fast enquiry path are what convert.</p>','compass',0,1,8,'Digital Solutions for Travel','Tour operator websites, itinerary builders and booking flows for travel businesses.',NOW(),NOW()),
('finance','Finance & Insurance','Credibility, compliance and clarity.','Advisory sites, calculators, secure document exchange and lead qualification.','<p>Financial services live or die on trust. We build sites that are precise about what is offered, compliant in how it is described, and secure in how documents move.</p>','chart',0,1,9,'Digital Solutions for Finance','Websites, calculators and secure client portals for financial and insurance businesses.',NOW(),NOW()),
('professional-services','Professional Services','Expertise, made obvious.','Firm websites, case studies and enquiry systems for consultancies, agencies and legal practices.','<p>When the product is judgement, the website''s job is to make that judgement visible. We structure around proof: work, people and results.</p>','briefcase',1,1,10,'Digital Solutions for Professional Services','Websites and enquiry systems for consultancies, law firms and professional practices.',NOW(),NOW()),
('manufacturing','Manufacturing','Catalogues, specifications and distributor tools.','Product catalogues, spec sheets, distributor portals and RFQ systems.','<p>Industrial buyers need specifications, availability and a way to request a quote. We build catalogues and portals around that path.</p>','factory',0,1,11,'Digital Solutions for Manufacturing','Product catalogues, distributor portals and RFQ systems for manufacturers.',NOW(),NOW()),
('ecommerce-brands','E-commerce Brands','Scale the store, not the workload.','Storefronts, migrations, integrations and the automation that keeps orders moving.','<p>Growing stores break at fulfilment, not at the front end. We build and integrate so the back office keeps pace.</p>','package',0,1,12,'Digital Solutions for E-commerce Brands','Storefronts, platform migrations and operations automation for growing online brands.',NOW(),NOW()),
('beauty-wellness','Beauty & Wellness','Bookings that fill the calendar.','Salon and studio sites, online booking, memberships and client reminders.','<p>An empty slot is revenue that never comes back. Online booking with automatic reminders is the single highest-return change most studios can make.</p>','sparkle',0,1,13,'Digital Solutions for Beauty & Wellness','Booking systems, memberships and websites for salons, spas and wellness studios.',NOW(),NOW()),
('local-businesses','Local Businesses','Be found by the street, not just the search.','Local search presence, service pages, reviews and enquiry handling.','<p>For a local business, the map listing often matters more than the homepage. We get both right, and connect them.</p>','pin',1,1,14,'Digital Solutions for Local Businesses','Local search presence, service pages and enquiry systems for neighbourhood businesses.',NOW(),NOW()),
('startups','Startups','Launch fast, look established.','Landing pages, MVPs, product sites and the brand foundation to raise on.','<p>Early-stage companies need to look credible before they are large. We build the brand and product surface that lets a small team compete on presentation.</p>','rocket',0,1,15,'Digital Solutions for Startups','Landing pages, MVPs and brand foundations for early-stage companies.',NOW(),NOW());

-- Map a sensible default service set onto each industry. Domain & hosting,
-- business email and e-commerce now live inside 'business-website', and
-- mobile folds into 'web-applications' — referenced by their merged slug
-- here, with the now-duplicate entries that produced removed.
INSERT INTO `industry_services` (`industry_id`,`service_id`,`sort_order`)
SELECT i.id, s.id, m.ord FROM `industries` i JOIN (
  SELECT 'restaurants' AS ind,'business-website' AS srv,1 AS ord
  UNION ALL SELECT 'restaurants','seo',2 UNION ALL SELECT 'restaurants','branding',3
  UNION ALL SELECT 'hotels','business-website',1 UNION ALL SELECT 'hotels','web-applications',2 UNION ALL SELECT 'hotels','seo',3
  UNION ALL SELECT 'retail','business-website',1 UNION ALL SELECT 'retail','seo',2 UNION ALL SELECT 'retail','automation-ai',3
  UNION ALL SELECT 'education','business-website',1 UNION ALL SELECT 'education','web-applications',2
  UNION ALL SELECT 'healthcare','business-website',1 UNION ALL SELECT 'healthcare','web-applications',2 UNION ALL SELECT 'healthcare','maintenance',3
  UNION ALL SELECT 'real-estate','business-website',1 UNION ALL SELECT 'real-estate','web-applications',2 UNION ALL SELECT 'real-estate','seo',3
  UNION ALL SELECT 'construction','business-website',1 UNION ALL SELECT 'construction','branding',2
  UNION ALL SELECT 'travel','business-website',1 UNION ALL SELECT 'travel','seo',2
  UNION ALL SELECT 'finance','business-website',1 UNION ALL SELECT 'finance','web-applications',2 UNION ALL SELECT 'finance','maintenance',3
  UNION ALL SELECT 'professional-services','business-website',1 UNION ALL SELECT 'professional-services','branding',2 UNION ALL SELECT 'professional-services','seo',3
  UNION ALL SELECT 'manufacturing','business-website',1 UNION ALL SELECT 'manufacturing','web-applications',2
  UNION ALL SELECT 'ecommerce-brands','business-website',1 UNION ALL SELECT 'ecommerce-brands','automation-ai',2 UNION ALL SELECT 'ecommerce-brands','seo',3
  UNION ALL SELECT 'beauty-wellness','business-website',1 UNION ALL SELECT 'beauty-wellness','web-applications',2 UNION ALL SELECT 'beauty-wellness','branding',3
  UNION ALL SELECT 'local-businesses','business-website',1 UNION ALL SELECT 'local-businesses','seo',2
  UNION ALL SELECT 'startups','branding',1 UNION ALL SELECT 'startups','business-website',2 UNION ALL SELECT 'startups','web-applications',3
) m ON m.ind = i.slug JOIN `services` s ON s.slug = m.srv;

-- ---------------------------------------------------------------------
-- Taxonomies
-- ---------------------------------------------------------------------
INSERT INTO `portfolio_categories` (`slug`,`name`,`description`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('website','Website','Marketing and business websites.',1,1,NOW(),NOW()),
('web-app','Web App','Custom web applications and internal systems.',1,2,NOW(),NOW()),
('mobile-app','Mobile App','Android and iOS applications.',1,3,NOW(),NOW()),
('ecommerce','E-commerce','Online stores and commerce platforms.',1,4,NOW(),NOW()),
('branding','Branding','Identity and visual systems.',1,5,NOW(),NOW()),
('ui-ux','UI/UX','Interface and experience design.',1,6,NOW(),NOW()),
('saas','SaaS','Multi-tenant software products.',1,7,NOW(),NOW()),
('ai','AI','Applied AI and machine learning work.',1,8,NOW(),NOW()),
('automation','Automation','Workflow and process automation.',1,9,NOW(),NOW());

-- Premade project categories. Structural only: no projects are seeded, because
-- inventing products TECHBISS does not actually sell would be a lie on the
-- storefront. Add real ones in Commerce → Premade projects.
INSERT INTO `project_categories` (`slug`,`name`,`description`,`icon`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('business-website','Business Website','Company sites ready to brand and launch.','globe',1,1,NOW(),NOW()),
('online-store','Online Store','Product catalogues, cart and checkout.','cart',1,2,NOW(),NOW()),
('booking','Booking & Appointments','Calendars, slots and reminders.','calendar',1,3,NOW(),NOW()),
('portfolio-site','Portfolio & Personal','Single-person and studio sites.','image',1,4,NOW(),NOW()),
('restaurant','Restaurant & Cafe','Menus, orders and table booking.','utensils',1,5,NOW(),NOW()),
('directory','Directory & Listings','Searchable listings with profiles.','list',1,6,NOW(),NOW()),
('dashboard','Admin & Dashboard','Internal tools and back offices.','dashboard',1,7,NOW(),NOW()),
('landing','Landing Page','One-page sites built to convert.','rocket',1,8,NOW(),NOW());

INSERT INTO `portfolio_technologies` (`slug`,`name`,`sort_order`) VALUES
('php','PHP',1),('mysql','MySQL',2),('javascript','JavaScript',3),('typescript','TypeScript',4),
('react','React',5),('vue','Vue',6),('node','Node.js',7),('laravel','Laravel',8),
('wordpress','WordPress',9),('woocommerce','WooCommerce',10),('shopify','Shopify',11),
('flutter','Flutter',12),('react-native','React Native',13),('swift','Swift',14),('kotlin','Kotlin',15),
('python','Python',16),('postgresql','PostgreSQL',17),('redis','Redis',18),('docker','Docker',19),
('aws','AWS',20),('figma','Figma',21),('tailwind','Tailwind CSS',22),('stripe','Stripe',23),('openai','OpenAI API',24);

INSERT INTO `blog_categories` (`slug`,`name`,`description`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('going-digital','Going Digital','Practical guides for businesses moving online.',1,1,NOW(),NOW()),
('websites','Websites','Design, build and performance.',1,2,NOW(),NOW()),
('seo','SEO','Search visibility and local presence.',1,3,NOW(),NOW()),
('ecommerce','E-commerce','Selling online.',1,4,NOW(),NOW()),
('automation','Automation & AI','Removing manual work.',1,5,NOW(),NOW()),
('business','Business','Strategy, pricing and growth.',1,6,NOW(),NOW());

INSERT INTO `blog_tags` (`slug`,`name`) VALUES
('domain','Domain'),('hosting','Hosting'),('ssl','SSL'),('branding','Branding'),
('local-seo','Local SEO'),('performance','Performance'),('email','Business Email'),
('mobile','Mobile'),('conversion','Conversion'),('security','Security');

-- ---------------------------------------------------------------------
-- Process steps
-- ---------------------------------------------------------------------
INSERT INTO `process_steps` (`step_number`,`title`,`description`,`icon`,`duration`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('01','Tell Us About Your Business','We start by understanding what the business actually does, who it sells to and what is getting in the way today. No jargon, no assumptions.','chat','Day 1',1,1,NOW(),NOW()),
('02','Choose Your Setup','Tell us what you want included, in your own words if it is easier. We come back with the scope and the price in writing.','list','Day 1–3',1,2,NOW(),NOW()),
('03','Build Your Foundation','Domain, hosting, SSL, DNS and business email. The infrastructure your business will own and run on, configured correctly from the start.','server','Week 1',1,3,NOW(),NOW()),
('04','Design Your Digital Presence','Website, branding and the user experience around them — designed for how your customers actually decide, reviewed with you before build.','palette','Week 2–4',1,4,NOW(),NOW()),
('05','Launch','Testing, performance, accessibility and search setup. Then everything goes live properly, with the handover and credentials that make it yours.','rocket','Week 4–6',1,5,NOW(),NOW()),
('06','Grow','SEO, analytics, applications, automation and ongoing support. The digital side keeps developing after launch instead of quietly decaying.','trend','Ongoing',1,6,NOW(),NOW());

-- ---------------------------------------------------------------------
-- FAQs
-- ---------------------------------------------------------------------
INSERT INTO `faqs` (`question`,`answer`,`category`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('Do I own my domain and hosting?','Yes. The domain is registered in your business name and you receive the registrar credentials. Hosting is provisioned for you and you get access. If you ever choose to work with someone else, everything moves with you.','Getting Started',1,1,NOW(),NOW()),
('I have no website at all. Where do we start?','With a conversation about the business, not about technology. We work out what your customers need to see, then build the foundation — domain, hosting, email — and the site on top of it. Starting from nothing is the situation we set up for most often.','Getting Started',1,2,NOW(),NOW()),
('How long does a typical project take?','A first website is usually live in two to three weeks. A larger build with SEO, analytics and automation typically runs four to eight weeks depending on content and scope. Custom applications are scoped individually. We give you a schedule before starting, not after.','Getting Started',1,3,NOW(),NOW()),
('What do I need to provide?','Information about your business, any existing brand assets, and someone who can approve decisions. If you do not have content or photography, we can produce those as part of the project.','Getting Started',1,4,NOW(),NOW()),
('Can I pay less by paying upfront?','Sometimes settling upfront rather than in stages reduces the total. We tell you both figures in writing when we quote.','Pricing',1,10,NOW(),NOW()),
('Are there any recurring costs?','Domain registration and hosting renew annually, and some add-ons are billed monthly or yearly. Renewal costs are stated in writing before you commit — nothing renews at a price you have not seen.','Pricing',1,11,NOW(),NOW()),
('Can I pay in stages?','Yes. Staged payment is the standard arrangement. If you would rather settle upfront, say so and we will quote that way too.','Pricing',1,12,NOW(),NOW()),
('Do you take payment through the website?','No. This website records your request. A member of the team confirms the scope with you and sends a formal invoice with payment instructions. Nothing is charged automatically.','Pricing',1,13,NOW(),NOW()),
('Will my website work on mobile?','Every site we build is designed for mobile first and tested across phones, tablets and desktop. For most businesses the majority of visitors arrive on a phone, so that is where the design decisions start.','Technical',1,20,NOW(),NOW()),
('Can I update the content myself?','Yes. Every site ships with an admin panel where you can edit text, images, pages, blog posts and SEO settings without touching code. We train your team on it at handover.','Technical',1,21,NOW(),NOW()),
('What happens if something breaks?','Where maintenance is part of what we agreed, that covers patching, backups and monitoring, and you have a support channel with a stated response time. Without it, support is available on request.','Technical',1,22,NOW(),NOW()),
('Do you migrate an existing website?','Yes. We migrate content, set up redirects so existing search rankings are preserved, and move email across with minimal downtime.','Technical',1,23,NOW(),NOW()),
('Will SEO get me to the top of Google?','No one can honestly promise a specific ranking. What we can do is fix what is technically holding the site back, structure content around what your customers actually search for, and report the real numbers each month. Positions improve over months, not days.','SEO & Growth',1,30,NOW(),NOW()),
('How do you measure results?','Analytics with defined goals, search performance data and enquiry volume. You see the same dashboard we do.','SEO & Growth',1,31,NOW(),NOW()),
('Do you work with businesses outside your country?','Yes. Projects run remotely with scheduled calls, and we work across time zones regularly.','Working Together',1,40,NOW(),NOW()),
('What if I need something unusual?','Ask. Most of what we build is a combination of the same services; plenty of businesses need something outside them. Custom scoping is a normal part of what we do.','Working Together',1,41,NOW(),NOW()),
('Who owns the work once it is finished?','You do. Code, designs, content and accounts are yours, and we hand over the credentials at launch.','Working Together',1,42,NOW(),NOW());

-- ---------------------------------------------------------------------
-- Pages
-- ---------------------------------------------------------------------
INSERT INTO `pages` (`slug`,`title`,`eyebrow`,`subtitle`,`content`,`template`,`seo_title`,`seo_description`,`is_published`,`is_system`,`sort_order`,`created_at`,`updated_at`) VALUES
('about','About TECHBISS','The company behind the work.','We exist to take businesses that work — real businesses, with real customers — and give them a digital presence that matches.','<h2>Why we started</h2><p>Most businesses do not fail online because they chose the wrong shade of blue. They fail because nobody ever assembled the pieces: the domain sits with a former web designer, the email is a personal address, the site was built once and never touched again, and there is no one to call when something breaks.</p><p>TECHBISS was built to be the partner that handles all of it. One relationship covering domain, hosting, website, applications, email, branding, search and support — so a business owner can go back to running the business.</p><h2>How we work</h2><p>We start with the business, not the technology. What does it sell, who buys it, and what is currently getting in the way? Only then do we decide what to build. That order matters: it is the difference between a website that looks expensive and one that actually earns.</p><h2>What we will not do</h2><p>We do not promise rankings we cannot control, invent statistics, or quote a price that changes once work begins. If something is outside what we do well, we will say so.</p>','default','About TECHBISS','TECHBISS is a complete digital transformation partner for offline businesses — domain, hosting, websites, apps, email, branding, SEO and support.',1,1,1,NOW(),NOW()),
('why-techbiss','Why TECHBISS','From offline business to digital brand.','Most agencies build a website and leave. We build the whole digital foundation and stay to run it.','<h2>One partner, not five vendors</h2><p>The usual path is a registrar for the domain, a host, a designer, a developer, someone for email, someone else for SEO — and no one accountable when they do not fit together. TECHBISS covers the whole stack under one relationship.</p><h2>Business first, technology second</h2><p>Every project starts with how the business earns. The technical decisions follow from that, which is why our work tends to look different from business to business even though the standard is constant.</p><h2>You own everything</h2><p>Domain in your name. Hosting in your account. Credentials handed over. Code and designs yours. There is no lock-in, because a client who stays by choice is worth more than one who cannot leave.</p><h2>Priced in conversation</h2><p>We do not publish prices, because the work changes with what you actually need. Tell us the list and we send a written figure, usually within one business day.</p><h2>Honest about outcomes</h2><p>A professional digital presence helps a business look established, makes information easy to find, extends reach beyond a physical location and creates a foundation to build on. It is not a guarantee of sales, and we will never present it as one.</p>','default','Why TECHBISS','One partner for domain, hosting, website, apps, email, branding and SEO — with full ownership and a written price before you commit.',1,1,2,NOW(),NOW()),
('privacy-policy','Privacy Policy','Legal','How TECHBISS collects, uses and protects the information you share with us.','<p><em>Please review this policy with your legal advisor and adjust it to reflect how your business actually operates and the jurisdictions you serve. It is provided as a starting structure, not as legal advice.</em></p><h2>Information we collect</h2><p>We collect information you provide directly: name, business name, email address, phone number, country and any details you include in an enquiry or request. We also collect limited technical information such as IP address and browser user agent when you submit a form, which we use to prevent abuse.</p><h2>How we use information</h2><p>To respond to your enquiry, prepare quotes, deliver services you have requested, send service-related communications, and — where you have opted in — send occasional updates. We do not sell your information.</p><h2>Analytics</h2><p>If analytics is enabled on this site, aggregate usage data is collected to understand how the site performs. Details of the provider are available on request.</p><h2>Data retention</h2><p>Enquiries and quote requests are retained for as long as needed to serve you and to meet legal and accounting obligations, then deleted.</p><h2>Your rights</h2><p>You may request a copy of the information we hold about you, ask for corrections, or ask us to delete it. Contact us using the details on the contact page.</p><h2>Security</h2><p>Data is transmitted over encrypted connections and stored on access-controlled systems. No system is perfectly secure, but we take reasonable technical and organisational measures to protect your information.</p><h2>Changes</h2><p>We may update this policy. The current version is always published on this page.</p>','legal','Privacy Policy','How TECHBISS collects, uses, retains and protects personal information.',1,1,90,NOW(),NOW()),
('terms-and-conditions','Terms & Conditions','Legal','The terms that apply to using this website and engaging TECHBISS for services.','<p><em>Please review these terms with your legal advisor and adjust them to reflect your actual contracting practice and jurisdiction. They are provided as a starting structure, not as legal advice.</em></p><h2>Using this website</h2><p>You may use this website for legitimate business purposes. You may not attempt to gain unauthorised access to any part of it, interfere with its operation, or submit content that is unlawful or misleading.</p><h2>Enquiries and requests</h2><p>Submitting an enquiry or request does not create a contract. It is a request for us to respond. A project begins only when a written proposal or agreement is signed by both parties.</p><h2>Pricing</h2><p>We do not publish prices on this website. The price that applies to your project is the one stated in your written proposal.</p><h2>Payment</h2><p>No payment is taken through this website. Invoices are issued separately with payment instructions and terms.</p><h2>Ownership</h2><p>On full payment, ownership of the deliverables produced for you transfers to you, excluding third-party components which remain under their own licences. Domains are registered in your name.</p><h2>Third-party services</h2><p>Some deliverables depend on third-party services such as registrars, hosting providers, payment gateways and app stores. Their terms and availability are outside our control.</p><h2>Limitation of liability</h2><p>To the extent permitted by law, our liability arising from a project is limited to the fees paid for that project. We are not liable for indirect or consequential loss.</p><h2>Contact</h2><p>Questions about these terms can be sent using the details on the contact page.</p>','legal','Terms & Conditions','Terms applying to the use of the TECHBISS website and engagement for services.',1,1,91,NOW(),NOW());

-- ---------------------------------------------------------------------
-- Homepage sections
-- ---------------------------------------------------------------------
INSERT INTO `page_sections` (`page_key`,`section_key`,`eyebrow`,`heading`,`subheading`,`body`,`cta_label`,`cta_url`,`is_published`,`sort_order`,`created_at`,`updated_at`) VALUES
('home','hero','One partner. Websites and apps.','Your Digital Business Starts Here.','We take offline businesses online — domain, website, email, branding, SEO — and build the custom app you have in mind.','','Tell Us What You Need','/request',1,1,NOW(),NOW()),
('home','problem','The starting point','Still running your business mostly offline?','You have the customers and the reputation. What is missing is the part that makes you easy to find and easy to buy from.','','See how it works','/how-it-works',1,2,NOW(),NOW()),
('home','services','What we do','Everything your business needs to operate online.','Everything you need to run your business online, delivered as one setup — domain, hosting, the site, email, and the app you have in mind.','','View all services','/services',1,4,NOW(),NOW()),
('home','work','Selected work','Built the same way yours will be.','Case studies covering websites, applications, commerce and brand systems.','','View all work','/portfolio',1,8,NOW(),NOW()),
('home','industries','Industries','Built around your sector.','A restaurant and a law firm need different things. We start from your sector, not a template.','','All industries','/industries',1,9,NOW(),NOW()),
('home','cta','Ready when you are','Let''s build your digital business.','Tell us about the business. You get a scope, a schedule and a price. No obligation.','','Tell Us What You Need','/request',1,10,NOW(),NOW());


