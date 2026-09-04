-- TECHBISS — database schema + seed data
-- Import this file into a MySQL/MariaDB database via phpMyAdmin
-- (Import tab -> choose file -> Go), then point config.php at that
-- database, username and password.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- staff — internal/admin users. Only staff can sign in at /admin/.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Staff',
  `permissions` TEXT NULL,
  `is_owner` TINYINT(1) NOT NULL DEFAULT 0,
  `initials` VARCHAR(4) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed staff login: admin@techbiss.com / techbiss-admin-2026
-- CHANGE THIS PASSWORD before going live — see README.md.
-- `permissions` NULL = full admin access. `is_owner` accounts always keep
-- full access and can't be deleted — Mara's account is the owner here.
INSERT INTO `staff` (`name`, `email`, `password_hash`, `role`, `permissions`, `is_owner`, `initials`) VALUES
('Mara Aldous', 'mara@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Founder & CEO', NULL, 1, 'MA'),
('Devon Kwan', 'devon@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Head of Engineering', NULL, 0, 'DK'),
('Rhea Solano', 'rhea@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'Head of Design', NULL, 0, 'RS'),
('Jonah Traeger', 'admin@techbiss.com', '$2y$12$KqXcJRwo2G4x/oavDaIVPuv754B8wk0ECXzkK4BWlcq0lzqQDrTha', 'VP Client Success', NULL, 0, 'JT');

-- ---------------------------------------------------------------
-- customers — real accounts created via the public sign-up form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- businesses — client accounts shown on the admin "Business accounts" table.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `businesses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `sector` VARCHAR(80) NOT NULL,
  `plan` VARCHAR(60) NOT NULL,
  `mrr_cents` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('Active','Trial','Past due') NOT NULL DEFAULT 'Active',
  `contact_email` VARCHAR(190) NULL,
  `contact_phone` VARCHAR(40) NULL,
  `customer_id` INT UNSIGNED NULL,
  `last_activity_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `businesses` (`name`, `sector`, `plan`, `mrr_cents`, `status`, `last_activity_at`) VALUES
('Maple & Co. Bakery', 'Bakery', 'Growth', 14900, 'Active', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('Solstice Yoga Studio', 'Fitness', 'Starter', 7900, 'Active', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Corner Hardware & Repair', 'Home services', 'Growth', 14900, 'Active', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('Nomad Coffee Roasters', 'Creator', 'App + Web', 22900, 'Trial', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('Kinship Pet Rescue', 'Nonprofit', 'Starter', 7900, 'Active', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Bloom & Bramble Florist', 'Retail', 'Growth', 14900, 'Past due', DATE_SUB(NOW(), INTERVAL 11 DAY));

-- ---------------------------------------------------------------
-- projects — real work tracked per business (replaces the placeholder
-- demo content that used to be hardcoded on the customer dashboard).
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `status` ENUM('Planning','In progress','Live','On hold') NOT NULL DEFAULT 'Planning',
  `progress_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `domain` VARCHAR(190) NULL,
  `domain_expires_at` DATE NULL,
  `hosting_expires_at` DATE NULL,
  `ssl_expires_at` DATE NULL,
  `email_expires_at` DATE NULL,
  `notes` TEXT NULL,
  `portfolio_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- tickets — cross-client support queue shown in the admin panel.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `priority` ENUM('Low','Normal','High') NOT NULL DEFAULT 'Normal',
  `status` ENUM('Open','In progress','Closed') NOT NULL DEFAULT 'Open',
  `assignee_staff_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assignee_staff_id`) REFERENCES `staff`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tickets` (`business_id`, `title`, `priority`, `status`, `assignee_staff_id`) VALUES
(1, 'Checkout error on mobile Safari', 'High', 'Open', 2),
(6, 'Billing card declined — needs follow-up call', 'High', 'Open', 4),
(1, 'Add a holiday hours banner', 'Normal', 'In progress', 3),
(4, 'App Store review flagged a screenshot', 'Normal', 'Open', 2),
(5, 'Domain renewal reminder', 'Low', 'Open', 4);

-- ---------------------------------------------------------------
-- products — marketplace listings, rendered from the DB on the public site.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` VARCHAR(20) PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `icon` VARCHAR(40) NOT NULL,
  `price` DECIMAL(8,2) NOT NULL,
  `pricing_type` ENUM('monthly','fixed') NOT NULL DEFAULT 'monthly',
  `rating` DECIMAL(2,1) NOT NULL,
  `tagline` TEXT NOT NULL,
  `description` TEXT NOT NULL,
  `specs_json` TEXT NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`id`,`name`,`category`,`icon`,`price`,`rating`,`tagline`,`description`,`specs_json`,`sort_order`) VALUES
('p1','Orchard Storefront','Templates','cart',349,4.9,'A ready-made online store you can brand as your own in an afternoon.','A fully responsive storefront theme with cart, wishlist and a component library tuned for small retail. Drop in your logo and colors — no code needed. Ships with light and dark themes.','["42 ready-made sections","No-code branding","WCAG AA out of the box","Free updates for 12 months"]',1),
('p2','Signal Support Agent','AI Agents','chat',129,4.8,'An AI assistant that answers customers and books appointments while you''re busy.','A drop-in AI agent trained on your business info that answers common questions and books appointments, with a graceful hand-off to you when it''s unsure. Works on your website or as a chat widget.','["Trains on your business info","Books appointments automatically","Hands off to you when unsure","Works on any website"]',2),
('p3','Meridian Ops Dashboard','Dashboards','monitor',249,4.9,'One screen for orders, bookings and messages — no more checking five apps.','A simple dashboard that pulls orders, bookings and customer messages from your site and socials into one screen, so you''re not logging into five different apps every day.','["Orders & bookings in one place","Daily & weekly summaries","Works with most booking tools","Mobile-friendly"]',3),
('p4','Domain, Hosting & Email Starter Kit','Bundles','globe',89,4.7,'Domain, fast hosting, SSL and a business email — set up and handed to you.','Everything your website needs to actually go live: a domain name registered in your business''s name, fast and secure hosting with SSL included, and a professional email address ready to use from day one.','["Domain registration included","Hosting + SSL, fully managed","Business email on your domain","Renewal reminders, no surprise lapses"]',4),
('p5','Bramble Field Themes','Themes','spark',59,4.6,'Twelve ready-made themes for any small business website.','A theme pack built for shops, studios and service businesses — organic shapes, accessible contrast, and full light/dark parity. Brand any one of them as your own.','["12 curated themes","Light & dark parity","Fully brandable","Drop-in, no developer needed"]',5),
('p6','App Store Launch Kit','Bundles','compass',199,4.8,'Everything it takes to get your app live on the App Store and Play Store.','We handle the developer accounts, store listings, screenshots and the entire review process, so your app goes from finished to published without you touching App Store Connect or the Play Console.','["iOS & Android submission handled","Store listing & screenshots included","Review process managed for you","Post-launch update support"]',6),
('p7','Willow Client Portal','Templates','users',279,4.9,'A booking and client portal your customers will actually enjoy using.','A branded portal for appointments, files and updates, so "when''s my next appointment" never turns into a text-message thread. Built for salons, studios, clinics and consultants.','["Online booking & reminders","File & document sharing","Branded to your business","Client notification emails"]',7),
('p8','Nectar Ranking Agent','AI Agents','chart',159,4.7,'Tracks your search ranking and tells you, in plain language, what to fix.','A plain-language SEO agent that checks how your site ranks for the searches that matter to your business, and tells you exactly what to fix — no jargon, no dashboard full of numbers to decode.','["Plain-language ranking reports","Local search tracking","Actionable fix-it suggestions","Weekly email summary"]',8);

-- ---------------------------------------------------------------
-- contact_messages — every submission of the public contact form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `company` VARCHAR(150) NULL,
  `need` VARCHAR(150) NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- newsletter_subscribers — Resources page "get the next field note" form.
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- settings — site-wide editable copy (Admin > Settings).
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`id`, `value`) VALUES
('site_name', 'TECHBISS'),
('logo_style', 'icon_text'),
('logo_animation', 'on'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', ''),
('hero_headline_main', 'We help offline businesses'),
('hero_headline_accent', 'thrive online.'),
('hero_subheadline', 'TECHBISS builds your website or app, then sets up your domain, hosting, email and app store listing — so you launch with everything working and ready to be found.'),
('site_tagline', 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.'),
('contact_email', 'hello@techbiss.com'),
('contact_phone', '+1 (415) 555-0148'),
('seo_title', 'TECHBISS — Helping offline businesses go online'),
('meta_description', 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.'),
('logo_path', ''),
('favicon_path', 'assets/favicon.ico'),
('social_image_path', 'assets/social-default.png'),
('default_theme', 'auto'),
('color_palette', ''),
('whatsapp_number', ''),
('stat1_value', '1,900+'),
('stat1_label', 'Businesses & apps launched'),
('stat2_value', '38'),
('stat2_label', 'Countries served'),
('stat3_value', '4.9/5'),
('stat3_label', 'Customer rating'),
('stat4_value', '72 hrs'),
('stat4_label', 'To your first draft'),
('stat5_value', '9'),
('stat5_label', 'Years in business'),
('services_json', '[{"icon":"monitor","name":"Website Design & Development","blurb":"A site built around your business, not squeezed into a generic template.","bullets":["Custom design & copy","Fast, mobile-friendly pages","Built to grow as you do"]},{"icon":"code","name":"App Development","blurb":"iOS and Android apps built from your idea, not a boilerplate.","bullets":["iOS & Android, one build","Real designs before we code","Built to pass App Store review"]},{"icon":"globe","name":"Domain, Hosting & Email","blurb":"The unglamorous stuff, set up right the first time and never left to lapse.","bullets":["Domain registration & DNS","Fast hosting with SSL included","Business email on your domain"]},{"icon":"rocket","name":"App Store & Play Store Publishing","blurb":"We handle listings, screenshots and the entire review process.","bullets":["Store listing & screenshots","Submission & review handled","Updates after you launch"]},{"icon":"chart","name":"SEO & Search Ranking","blurb":"So being online actually means being found.","bullets":["On-page & technical SEO","Google Maps & local search","Plain-language ranking reports"]},{"icon":"cart","name":"Ready-Made Themes & Templates","blurb":"Buy a theme, brand it as your own, and launch in days.","bullets":["Fully brandable, no lock-in","Your logo, colors & content","Same support as a custom build"]}]'),
('solutions_json', '[{"icon":"cart","name":"Shops & Local Retail","out":["An online store that matches your storefront","Orders and inventory in one place","Local SEO so nearby customers find you"]},{"icon":"heart","name":"Restaurants & Cafés","out":["Menu, hours & online ordering","Table booking built in","Your Google & Maps listing done right"]},{"icon":"gear","name":"Home & Local Services","out":["Booking & quote requests online","Service-area SEO that actually ranks","Reviews and contact, front and center"]},{"icon":"spark","name":"Creators & Personal Brands","out":["A site or app that looks like you","Portfolio, shop or booking in one place","App store publishing handled"]},{"icon":"flag","name":"Nonprofits & Community Groups","out":["Donation & event pages","Volunteer sign-ups made simple","Discounted plans available"]}]'),
('case_studies_json', '[{"sector":"Bakery","icon":"cart","client":"Maple & Co. Bakery","stat":"+64%","statLabel":"online orders in month one","quote":"We went from a Facebook page to a real website with ordering in under two weeks.","body":"Maple & Co. was taking orders through Facebook comments and DMs. We built them a website with online ordering, connected a custom domain and business email, and got them ranking for \\"bakery near me\\" in their own neighborhood."},{"sector":"Fitness","icon":"heart","client":"Solstice Yoga Studio","stat":"3x","statLabel":"more class bookings","quote":"Our booking calendar used to be a shared spreadsheet. Now people book from their phone.","body":"Solstice had no website at all — just word of mouth. We built them a site with class booking, set up hosting and email, and helped them show up in local search."},{"sector":"Home services","icon":"gear","client":"Corner Hardware & Repair","stat":"+120","statLabel":"quote requests per month","quote":"People find us on Google now instead of just driving past.","body":"Corner Hardware had a storefront but no online presence at all. We built a simple, fast site with a quote-request form and got them ranking on Google Maps for their service area."},{"sector":"Creator","icon":"spark","client":"Nomad Coffee Roasters","stat":"2 wks","statLabel":"from first call to a live app","quote":"We had an idea for a loyalty app on a napkin. Two weeks later it was in the App Store.","body":"Nomad wanted a simple loyalty app for regulars. We designed it, built it, and handled the entire App Store submission — they never had to touch a developer account."},{"sector":"Nonprofit","icon":"flag","client":"Kinship Pet Rescue","stat":"+210","statLabel":"volunteer sign-ups since launch","quote":"Donations and volunteer sign-ups finally happen without ten emails back and forth.","body":"Kinship ran on a free page builder that couldn\'t handle donations or sign-ups. We rebuilt their site, added donation and volunteer forms, and moved them onto their own domain."},{"sector":"Retail","icon":"box","client":"Bloom & Bramble Florist","stat":"+47%","statLabel":"website-driven sales","quote":"Customers can finally order flowers from their phone at 11pm.","body":"Bloom & Bramble took phone orders only. We built an online store with same-day-delivery scheduling and got them showing up first for local flower searches."}]'),
('pricing_json', '[{"n":"Starter","m":39,"y":31,"d":"Hosting, domain renewal and a small monthly update — for once your site is live.","f":["Hosting, SSL & domain included","1 small update per month","Email support","Uptime monitoring"],"cta":"Start with Starter","rec":false},{"n":"Growth","m":99,"y":79,"d":"For businesses adding bookings, an online store, or an app.","f":["Everything in Starter","Priority support","Marketplace theme credit","Monthly SEO check-in","App store update handling"],"cta":"Start with Growth","rec":true},{"n":"Custom Build","m":null,"y":null,"d":"A website or app built from scratch around your business.","f":["Custom design & development","Dedicated project lead","Domain, hosting, SSL & email included","App Store & Play Store publishing","Free ranking check-up"],"cta":"Get a free quote","rec":false}]'),
('pricing_faq_json', '[["Do you build the website too, or is this just hosting?","These plans cover hosting, care and updates after launch. New builds — a website, an app, or both — are quoted upfront based on what you need."],["Can I switch plans later?","Yes — upgrade or downgrade at the start of any billing cycle, and we\'ll prorate the difference."],["What if I already have a website?","We can take over hosting and support for an existing site, or rebuild it if it needs modern love — either way, nothing changes for your visitors during the switch."],["Can I start with a marketplace theme instead of a custom build?","Yes — Growth and Custom Build plans include a marketplace credit toward any ready-made theme, which we\'ll brand and launch for you."],["Do you offer nonprofit or small business discounts?","Yes, reach out through Contact and we\'ll tailor a plan for community and mission-driven organizations."]]'),
('team_json', '[{"i":"MA","n":"Mara Aldous","r":"Founder & CEO"},{"i":"DK","n":"Devon Kwan","r":"Head of Engineering"},{"i":"RS","n":"Rhea Solano","r":"Head of Design"},{"i":"JT","n":"Jonah Traeger","r":"VP Client Success"}]'),
('values_json', '[{"icon":"heart","t":"Plain language, always","d":"No jargon you need a developer to translate. If we can\'t explain it simply, we haven\'t understood it yet."},{"icon":"shield","t":"Nothing rented that should be owned","d":"Your domain, your site, your app — registered and built in your name, not locked to us."},{"icon":"users","t":"A real person replies","d":"Support that\'s an actual person who knows your project, not a ticket number."},{"icon":"spark","t":"Built to be found, not just to exist","d":"A website nobody can find isn\'t really online. SEO is part of the build, not an upsell."}]');

SET FOREIGN_KEY_CHECKS = 1;
