-- TECHBISS — consolidate the services catalog on an already-installed site.
--
-- A fresh install (seed.sql) already seeds the consolidated six services.
-- This is for a site that was set up before that change and still has the
-- original ten: it merges Domain & Hosting, Business Email and E-commerce
-- into Business Website, and Mobile Applications into Web Applications —
-- run once, from a database client or `mysql < database/consolidate-services.sql`.
--
-- Safe to run more than once: everything is guarded so re-running it after
-- it already applied is a harmless no-op, not an error.

UPDATE services SET
  name = 'Website Setup',
  tagline = 'Domain, hosting, the site itself and business email — set up and launched as one job.',
  short_description = 'Everything to run your business online: domain, hosting, DNS and SSL, a custom-built website, professional email on your own domain, and an online store if you sell products. Set up and launched together, not sold piece by piece.',
  description = '<p>Your domain is the one asset that stays with you, so we register it in your name, point DNS correctly, and provision managed hosting with SSL installed and renewed automatically. The website itself is built around what your business does and what a visitor needs to decide — responsive from the first breakpoint, with a CMS so you can edit it yourself.</p><p>Professional email on your own domain is included, configured so it actually reaches the inbox. If you sell products, we build the store into the same site — catalogue, checkout, payments and the order tooling behind them. One job, one team, one thing to maintain.</p>',
  deliverables = 'Domain registered in your name, DNS configured\nManaged hosting with SSL installed and auto-renewed\nCustom-designed, responsive website with an editable CMS\nProfessional email on your domain (SPF, DKIM, DMARC configured)\nOnline store with catalogue, checkout and payments, if you sell products\nContact and enquiry forms\nDaily backups and uptime monitoring\nAnalytics and Search Console setup'
WHERE slug = 'business-website';

UPDATE services SET
  name = 'Web & Mobile Apps',
  tagline = 'The app you have in mind — for the web, or for Android and iOS.',
  short_description = 'Booking systems, portals, dashboards and internal tools for the web, or a customer-facing app for Android and iOS — whichever fits what you actually need built.',
  description = '<p>When a spreadsheet stops scaling, or the thing you need only makes sense on a phone, this is that. We map the workflow or the idea first, tell you what it takes to build and what can wait for later, then build it — a web application with the reporting and access control you need, or a native app shipped to both app stores.</p><p>Apps we build most: booking and ordering, loyalty, field data capture, and internal tools for a team that works away from a desk.</p>',
  deliverables = 'Workflow or idea mapped before any code is written\nCustom-built web application, or native builds for Android and iOS\nRole-based access and admin dashboards\nApp store submission and release management, for mobile builds\nPush notifications and offline-capable data, for mobile builds\nReporting, exports and third-party integrations'
WHERE slug = 'web-applications';

-- Every portfolio piece and industry page tagged with a service about to be
-- deleted gets re-tagged to the service it merged into first, so nothing
-- that was showing "E-commerce" or "Domain & Hosting" ends up with no tag
-- at all once that row is gone (the foreign keys cascade-delete the tag,
-- not re-point it).
INSERT IGNORE INTO portfolio_services (portfolio_id, service_id)
SELECT ps.portfolio_id, (SELECT id FROM services WHERE slug = 'business-website')
FROM portfolio_services ps JOIN services s ON s.id = ps.service_id
WHERE s.slug IN ('domain-hosting', 'business-email', 'ecommerce');

INSERT IGNORE INTO portfolio_services (portfolio_id, service_id)
SELECT ps.portfolio_id, (SELECT id FROM services WHERE slug = 'web-applications')
FROM portfolio_services ps JOIN services s ON s.id = ps.service_id
WHERE s.slug = 'mobile-applications';

INSERT IGNORE INTO industry_services (industry_id, service_id, sort_order)
SELECT isv.industry_id, (SELECT id FROM services WHERE slug = 'business-website'), isv.sort_order
FROM industry_services isv JOIN services s ON s.id = isv.service_id
WHERE s.slug IN ('domain-hosting', 'business-email', 'ecommerce');

INSERT IGNORE INTO industry_services (industry_id, service_id, sort_order)
SELECT isv.industry_id, (SELECT id FROM services WHERE slug = 'web-applications'), isv.sort_order
FROM industry_services isv JOIN services s ON s.id = isv.service_id
WHERE s.slug = 'mobile-applications';

-- service_features carried over from the merged rows: pick the handful worth
-- keeping rather than every bullet from every service (only inserted if not
-- already present, so re-running this script never duplicates them).
INSERT INTO service_features (service_id, title, description, icon, sort_order)
SELECT id, 'Owned in your name', 'The domain is registered to your business, not to us.', '', 3 FROM services
WHERE slug = 'business-website'
  AND NOT EXISTS (SELECT 1 FROM service_features WHERE service_id = services.id AND title = 'Owned in your name');
INSERT INTO service_features (service_id, title, description, icon, sort_order)
SELECT id, 'Deliverability configured', 'SPF, DKIM and DMARC set correctly from day one.', '', 4 FROM services
WHERE slug = 'business-website'
  AND NOT EXISTS (SELECT 1 FROM service_features WHERE service_id = services.id AND title = 'Deliverability configured');
INSERT INTO service_features (service_id, title, description, icon, sort_order)
SELECT id, 'Ships to both app stores', 'Android and iOS, submitted and managed through release.', '', 3 FROM services
WHERE slug = 'web-applications'
  AND NOT EXISTS (SELECT 1 FROM service_features WHERE service_id = services.id AND title = 'Ships to both app stores');

-- The four services this absorbed. Cascades clean up their own
-- service_features and any remaining portfolio_services/industry_services
-- rows (the ones worth keeping were already re-tagged above).
DELETE FROM services WHERE slug IN ('domain-hosting', 'business-email', 'ecommerce', 'mobile-applications');

UPDATE page_sections SET subheading = 'Everything you need to run your business online, delivered as one setup — domain, hosting, the site, email, and the app you have in mind.'
  WHERE page_key = 'home' AND section_key = 'services'
    AND subheading <> 'Everything you need to run your business online, delivered as one setup — domain, hosting, the site, email, and the app you have in mind.';
