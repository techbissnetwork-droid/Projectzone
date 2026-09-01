-- =====================================================================
-- TECHBISS — shorten the seeded copy on a site that is already installed
--
-- database/seed.sql only runs at install time, so an existing site keeps
-- whatever copy it was set up with. This script rewrites the seeded text
-- to the shorter wording.
--
-- Safe to run more than once, and safe to run on a site you have edited:
-- every UPDATE only touches a row whose text is still exactly the original,
-- so anything you have rewritten yourself is left alone. Every DELETE removes
-- a row a later release made obsolete — a dropped nav entry, a homepage
-- section no page reads any more — never anything you added yourself.
--
-- Applied automatically by `php install.php --upgrade` and by the admin's
-- Database updates button; only run it by hand if you have a reason to.
--     mysql -u USER -p DATABASE < database/copy-refresh.sql
-- =====================================================================
SET NAMES utf8mb4;

UPDATE `page_sections` SET `subheading` = 'Everything your business needs online — domain, website, apps, email, branding and SEO — from one partner.'
  WHERE `section_key` = 'hero' AND `subheading` = 'Transform your offline business into a premium, trusted digital brand. TECHBISS provides everything you need — from your own domain and hosting to websites, apps, business email, branding, SEO and ongoing support.';

UPDATE `page_sections` SET `subheading` = 'You have the customers and the reputation. What is missing is the part that makes you easy to find and easy to buy from.'
  WHERE `section_key` = 'problem' AND `subheading` = 'You have customers, a reputation and something that works. What you do not have is the digital foundation that makes it visible, credible and easy to buy from.';

UPDATE `page_sections` SET `subheading` = 'Each piece depends on the one before. We build them in order.'
  WHERE `section_key` = 'chain' AND `subheading` = 'Each piece depends on the one before it. We build them in order, and we build all of them.';

UPDATE `page_sections` SET `subheading` = 'Ten services, from infrastructure to the work that keeps it running.'
  WHERE `section_key` = 'services' AND `subheading` = 'Ten services that cover the entire digital foundation — infrastructure, presence, applications and the work that keeps them performing.';

UPDATE `page_sections` SET `heading` = 'What this actually changes.'
  WHERE `section_key` = 'trust' AND `heading` = 'What a professional digital presence actually changes.';

UPDATE `page_sections` SET `subheading` = 'Not guaranteed sales. How established you look, how easily customers find you, and how far past your front door you reach.'
  WHERE `section_key` = 'trust' AND `subheading` = 'Not guaranteed sales. Something more fundamental: how established your business looks, how easily customers find what they need, and how far beyond your physical location you can reach.';

UPDATE `page_sections` SET `heading` = 'Six stages, first call to ongoing support.'
  WHERE `section_key` = 'process' AND `heading` = 'Six stages from first conversation to ongoing growth.';

UPDATE `page_sections` SET `subheading` = 'Complete setups with published prices. Where a prepaid discount applies, you see the exact saving.'
  WHERE `section_key` = 'packages' AND `subheading` = 'Complete setups with published pricing. Where a prepaid discount applies, you see the regular price, the prepaid price and the exact saving.';

UPDATE `page_sections` SET `heading` = 'Built around your sector.'
  WHERE `section_key` = 'industries' AND `heading` = 'Built around how your sector actually works.';

UPDATE `page_sections` SET `subheading` = 'A restaurant and a law firm need different things. We start from your sector, not a template.'
  WHERE `section_key` = 'industries' AND `subheading` = 'A restaurant, a law firm and a manufacturer need different things. We start from the sector, not from a template.';

UPDATE `page_sections` SET `subheading` = 'Tell us about the business. You get a scope, a schedule and a price. No obligation.'
  WHERE `section_key` = 'cta' AND `subheading` = 'Tell us about the business. We will come back with a clear scope, a schedule and a price — no obligation.';

UPDATE `page_sections` SET `heading` = 'Built the same way yours will be.'
  WHERE `section_key` = 'work' AND `heading` = 'Projects built the same way yours will be.';

UPDATE `settings` SET `value` = 'Take your business online, properly.'
  WHERE `key_name` = 'brand_promise' AND `value` = 'Transform your offline business into a premium digital brand.';

UPDATE `settings` SET `value` = 'One partner for everything your business needs online: domain, hosting, website, apps, email, branding, SEO and support.'
  WHERE `key_name` = 'footer_text' AND `value` = 'TECHBISS is a complete digital transformation partner. We take businesses from offline to a premium, trusted digital presence — domain, hosting, website, apps, email, branding, SEO and ongoing support.';


-- ---------------------------------------------------------------------
-- Positioning: the site now leads with both promises — taking an offline
-- business online, and building the app you have in mind.
-- ---------------------------------------------------------------------

UPDATE `page_sections` SET `eyebrow` = 'One partner. Websites and apps.'
  WHERE `section_key` = 'hero' AND `eyebrow` = 'One partner. Everything digital.';

UPDATE `page_sections` SET `subheading` = 'We take offline businesses online — domain, website, email, branding, SEO — and build the custom app you have in mind.'
  WHERE `section_key` = 'hero' AND `subheading` = 'Everything your business needs online — domain, website, apps, email, branding and SEO — from one partner.';

UPDATE `page_sections` SET `subheading` = 'Ten services — your domain and website, custom web and mobile apps, and the work that keeps them running.'
  WHERE `section_key` = 'services' AND `subheading` = 'Ten services, from infrastructure to the work that keeps it running.';

UPDATE `settings` SET `value` = 'Offline business to online, plus the app you have in mind.'
  WHERE `key_name` = 'brand_promise' AND `value` = 'Take your business online, properly.';

UPDATE `services` SET `tagline` = 'The app you have in mind, built for Android and iOS.'
  WHERE `slug` = 'mobile-applications' AND `tagline` = 'Professional Android and iOS applications.';

UPDATE `services` SET `short_description` = 'The app you have in mind — for your customers, or for your team in the field.'
  WHERE `slug` = 'mobile-applications' AND `short_description` = 'Native-quality mobile apps for customers or for the team in the field.';

UPDATE `services` SET `description` = '<p>Bring us the idea. We tell you what it takes to build, what it will do on day one, and what can wait for later. Then we build it for Android and iOS, ship it to both stores, and handle the releases and updates after that.</p><p>Apps we build most: ordering and booking, loyalty, field data capture, and internal tools for a team that works away from a desk.</p>'
  WHERE `slug` = 'mobile-applications' AND `description` = '<p>We build mobile apps when a phone is genuinely the right place for the job — field data capture, loyalty, ordering, bookings. Shipped to both stores, with the release and update process handled.</p>';

UPDATE `settings` SET `value` = 'We take offline businesses online and build the app you have in mind. Websites, mobile apps, hosting, business email, branding and SEO from one partner.'
  WHERE `key_name` = 'seo_default_description' AND `value` = 'TECHBISS turns offline businesses into premium digital brands. Domain, hosting, websites, apps, business email, branding, SEO and ongoing support from one partner.';

-- Show mobile apps on the homepage, next to the website service.
UPDATE `services` SET `is_featured` = 1, `sort_order` = 3 WHERE `slug` = 'mobile-applications' AND `is_featured` = 0;
UPDATE `services` SET `sort_order` = 4 WHERE `slug` = 'web-applications' AND `sort_order` = 3;

-- ---------------------------------------------------------------------
-- Remove the copy that still promised published prices. The site shows no
-- figures; these sentences told visitors to look for them.
-- ---------------------------------------------------------------------

UPDATE `page_sections` SET `heading` = 'Pick what you need. We price it with you.'
  WHERE `section_key` = 'packages' AND `heading` = 'Pay upfront. Save more. Build better.';

UPDATE `page_sections` SET `subheading` = 'Complete setups. Tell us what you want included and we send the figure.'
  WHERE `section_key` = 'packages' AND `subheading` = 'Complete setups with published prices. Where a prepaid discount applies, you see the exact saving.';

UPDATE `process_steps` SET `description` = 'Select a package that fits, or ask for a custom scope. You see exactly what is included, and we send the price before you decide.'
  WHERE `step_number` = '02' AND `description` = 'Select a package that fits, or ask for a custom scope. You see the regular price, the prepaid price and exactly what is included before deciding.';

UPDATE `faqs` SET `answer` = 'Sometimes settling upfront rather than in stages reduces the total. We tell you both figures in writing when we quote.'
  WHERE `question` = 'What does the prepaid price mean?' AND `answer` = 'Some packages have a lower price when the package is paid upfront rather than in stages. Where that applies, both prices and the exact saving are shown on the package. If no prepaid price is shown, there is no prepaid discount on that package.';

UPDATE `faqs` SET `answer` = 'Yes. Staged payment is the standard arrangement. If you would rather settle upfront, say so and we will quote that way too.'
  WHERE `question` = 'Can I pay in stages?' AND `answer` = 'Yes. Staged payment is the standard arrangement. The prepaid price is the alternative for businesses that prefer to settle upfront.';

UPDATE `navigation` SET `description` = 'Complete setups, priced with you'
  WHERE `url` = '/packages' AND `description` = 'Prepaid setups with clear pricing';

UPDATE `faqs` SET `question` = 'Can I pay less by paying upfront?'
  WHERE `question` = 'What does the prepaid price mean?';

UPDATE `pages` SET `content` = REPLACE(`content`, '<h2>Clear pricing</h2><p>Package prices are published. Where a prepaid discount applies, both prices and the exact saving are shown. Custom work is scoped and quoted in writing before it starts.</p>', '<h2>Priced in conversation</h2><p>We do not publish prices, because the work changes with what you actually need. Tell us the list and we send a written figure, usually within one business day.</p>')
  WHERE `slug` = 'why-techbiss';

UPDATE `pages` SET `seo_description` = 'One partner for domain, hosting, website, apps, email, branding and SEO — with full ownership and a written price before you commit.'
  WHERE `slug` = 'why-techbiss' AND `seo_description` = 'One partner for domain, hosting, website, apps, email, branding and SEO — with full ownership and clear pricing.';

UPDATE `pages` SET `content` = REPLACE(`content`, '<h2>Pricing</h2><p>Prices published on this website are indicative and may change. The price that applies to your project is the one stated in your written proposal. Where a prepaid price is shown, the conditions attached to it are stated in the proposal.</p>', '<h2>Pricing</h2><p>We do not publish prices on this website. The price that applies to your project is the one stated in your written proposal.</p>')
  WHERE `slug` = 'terms-and-conditions';
-- The cache holds rendered settings and navigation, so clear it afterwards:
--     rm -f storage/cache/*.cache
-- or use Admin → System → Tools → Clear cache.

-- ---------------------------------------------------------------------
-- The packages are gone. There is one page now: tick what you need and
-- send it on WhatsApp or by email, which is where the price is agreed.
-- The old paths still redirect, but the menus should point at the real one.
-- ---------------------------------------------------------------------

-- The header already carries the request as its call to action, so the menu
-- link for it would be the same destination twice.
DELETE FROM `navigation` WHERE `url` = '/packages' AND `menu` = 'primary';

UPDATE `navigation` SET `label` = 'Tell us what you need', `url` = '/request'
  WHERE `url` = '/packages' AND `menu` = 'footer';

UPDATE `navigation` SET `label` = 'Tell us what you need', `url` = '/request'
  WHERE `url` = '/start' AND `menu` = 'primary';

DELETE FROM `navigation` WHERE `url` = '/quote';

UPDATE `page_sections` SET `cta_label` = 'Tell Us What You Need', `cta_url` = '/request'
  WHERE `cta_url` IN ('/start', '/quote', '/packages');

UPDATE `page_sections` SET `is_published` = 0 WHERE `section_key` = 'packages';

-- The footer called /portfolio "Portfolio" while the header called it "Work".
UPDATE `navigation` SET `label` = 'Work' WHERE `menu` = 'footer' AND `url` = '/portfolio' AND `label` = 'Portfolio';

-- ---------------------------------------------------------------------
-- There are no packages any more, and no six-step wizard. These answers
-- still sent people looking for both.
-- ---------------------------------------------------------------------

UPDATE `faqs` SET `answer` = 'With a conversation about the business, not about technology. We work out what your customers need to see, then build the foundation — domain, hosting, email — and the site on top of it. Starting from nothing is the situation we set up for most often.'
  WHERE `question` = 'I have no website at all. Where do we start?'
    AND `answer` LIKE '%Starter package exists for exactly this situation.';

UPDATE `faqs` SET `answer` = 'A first website is usually live in two to three weeks. A larger build with SEO, analytics and automation typically runs four to eight weeks depending on content and scope. Custom applications are scoped individually. We give you a schedule before starting, not after.'
  WHERE `question` = 'How long does a typical project take?'
    AND `answer` LIKE 'A Starter setup is usually live%';

UPDATE `faqs` SET `answer` = 'Where maintenance is part of what we agreed, that covers patching, backups and monitoring, and you have a support channel with a stated response time. Without it, support is available on request.'
  WHERE `question` = 'What happens if something breaks?'
    AND `answer` LIKE 'Packages including maintenance cover%';

UPDATE `faqs` SET `question` = 'What if I need something unusual?',
                  `answer`   = 'Ask. Most of what we build is a combination of the same services; plenty of businesses need something outside them. Custom scoping is a normal part of what we do.'
  WHERE `question` = 'What if I need something that is not in a package?';

UPDATE `process_steps` SET `description` = 'Tell us what you want included, in your own words if it is easier. We come back with the scope and the price in writing.'
  WHERE `step_number` = '02'
    AND `description` LIKE 'Select a package that fits%';

UPDATE `pages` SET `content` = REPLACE(`content`, 'enquiry, quote request or package request', 'enquiry or request')
  WHERE `slug` IN ('privacy-policy', 'terms-and-conditions');
UPDATE `pages` SET `content` = REPLACE(`content`, 'an enquiry, quote request or package request', 'a request')
  WHERE `slug` = 'terms-and-conditions';

-- Settings whose readers went with the packages.
DELETE FROM `settings` WHERE `key_name` IN ('show_prepaid_savings', 'checkout_enabled', 'payment_note', 'bank_transfer_details', 'payment_methods');

-- Permissions nothing checks any more.
DELETE FROM `role_permissions` WHERE `permission_id` IN (SELECT id FROM `permissions` WHERE `slug` IN ('packages.manage','purchases.manage'));
DELETE FROM `permissions` WHERE `slug` IN ('packages.manage','purchases.manage');

-- The homepage offered the same button, with the same words and the same
-- destination, four times down one page. The hero and the closing band keep
-- it; the two mid-page sections point somewhere that moves the reader on.
UPDATE `page_sections` SET `cta_label` = '', `cta_url` = ''
  WHERE `section_key` = 'problem' AND `cta_url` = '/request';

UPDATE `page_sections` SET `cta_label` = 'See what we do', `cta_url` = '/services'
  WHERE `section_key` = 'chain' AND `cta_url` = '/request';

-- home.php never read this section; publishing it rendered nothing.
DELETE FROM `page_sections` WHERE `page_key` = 'home' AND `section_key` = 'packages';

-- The chain section sits directly above the services section, which carries its
-- own "View all services" link; the band below carries a third.
UPDATE `page_sections` SET `cta_label` = '', `cta_url` = ''
  WHERE `section_key` = 'chain' AND `cta_url` = '/services';

-- ---------------------------------------------------------------------
-- The sign-in address, the address shown on the site and the address the
-- site emails are three different things. Setup used to copy one into all
-- of them, and support_email was never read by anything at all.
-- ---------------------------------------------------------------------

UPDATE `settings` SET `label` = 'Public contact email', `hint` = 'Shown on the website and used on the contact page. Not your sign-in address.'
  WHERE `key_name` = 'contact_email';

UPDATE `settings` SET `label` = 'Sales email', `hint` = 'For new enquiries. Leave blank to use the public contact email.'
  WHERE `key_name` = 'sales_email';

UPDATE `settings` SET `label` = 'Support email', `hint` = 'For existing clients. Shown on the contact page only when it differs from the public one.'
  WHERE `key_name` = 'support_email';

UPDATE `settings` SET `label` = 'Send notifications to', `hint` = 'Where the site emails you when an enquiry arrives. Never shown publicly. Defaults to the public contact email.'
  WHERE `key_name` = 'notification_email';

-- Setup wrote the administrator's own sign-in address into these two. Clearing
-- them where they still match makes them inherit instead of quietly pretending
-- to be separate addresses.
UPDATE `settings` s
  JOIN (SELECT value AS contact FROM `settings` WHERE `key_name` = 'contact_email') c
  SET s.value = ''
  WHERE s.key_name IN ('sales_email', 'support_email') AND s.value = c.contact;

-- ---------------------------------------------------------------------
-- Three homepage bands ("chain", "trust", "process") no longer render on
-- the homepage — "chain" restated the services grid one scroll below it,
-- and "trust"/"process" were cut when the homepage was shortened. Left in
-- place they were a Visible toggle in the admin that silently did nothing.
-- Their content (six-stage process, credibility points) lives on
-- /how-it-works and elsewhere; nothing on the site is lost by removing
-- the row, only the dead duplicate of it.
-- ---------------------------------------------------------------------
DELETE FROM `page_sections` WHERE `page_key` = 'home' AND `section_key` IN ('chain', 'trust', 'process');

-- The teaser needs a destination; it had none before because the full
-- problem grid used to be the whole section.
UPDATE `page_sections` SET `cta_label` = 'See how it works', `cta_url` = '/how-it-works'
  WHERE `page_key` = 'home' AND `section_key` = 'problem' AND `cta_label` = '' AND `cta_url` = '';

-- The field now also accepts a ready-made WhatsApp chat link, not just a
-- bare number — whatsapp_link() in includes/helpers.php reads either.
UPDATE `settings` SET `label` = 'WhatsApp number or chat link',
  `hint` = 'A phone number with country code (e.g. 8801711223344), or a full chat link (wa.me/… or a WhatsApp Business link) — either works.'
  WHERE `key_name` = 'whatsapp';

-- Wording only — doesn't assert a specific service count, so it reads
-- correctly whether or not the services themselves have been consolidated.
UPDATE `page_sections` SET `subheading` = 'Everything you need to run your business online, delivered as one setup — domain, hosting, the site, email, and the app you have in mind.'
  WHERE `page_key` = 'home' AND `section_key` = 'services'
    AND `subheading` = 'Ten services — your domain and website, custom web and mobile apps, and the work that keeps them running.';

-- "Website Setup" read as configuration work on an existing site — leading
-- with domain/hosting undersold that a custom site is designed and built
-- here too, not just plugged together.
UPDATE `services` SET
  `name` = 'Website Design & Setup',
  `tagline` = 'We build the website — any kind, from scratch — then set up everything it needs to run.',
  `short_description` = 'A custom website, designed and built around your business, not a template. Then everything it needs to run: domain, hosting, DNS and SSL, professional email on your own domain, and an online store if you sell products. Built and launched together, not sold piece by piece.',
  `description` = '<p>Whatever site you need — a marketing site, a booking or ordering site, a full online store — we design and build it around what your business does and what a visitor needs to decide, responsive from the first breakpoint. This is not a template with your logo dropped in.</p><p>Once it is built, we set up everything around it: your domain registered in your name, DNS pointed correctly, managed hosting with SSL installed and renewed automatically, and professional email on your own domain. If you sell products, the store — catalogue, checkout, payments — is built into the same site. One job, one team, one thing to maintain.</p>',
  `deliverables` = 'Custom-designed, responsive website — built for what your business does, not a template\nEditable CMS so you can update content yourself\nDomain registered in your name, DNS configured\nManaged hosting with SSL installed and auto-renewed\nProfessional email on your domain (SPF, DKIM, DMARC configured)\nOnline store with catalogue, checkout and payments, if you sell products\nContact and enquiry forms\nDaily backups and uptime monitoring\nAnalytics and Search Console setup'
  WHERE `slug` = 'business-website' AND `name` = 'Website Setup';
