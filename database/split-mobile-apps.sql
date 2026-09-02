-- TECHBISS — split "Web & Mobile Apps" back into two separate services.
--
-- consolidate-services.sql (and seed.sql before this change) folded native
-- mobile app work into "Web Applications". A web app and a native Android/iOS
-- app are different work with a different process, so this splits them back
-- into "Web Applications" (web only) and a new "Mobile Apps" service.
--
-- Run once, from a database client or `mysql < database/split-mobile-apps.sql`.
-- Safe to run more than once: the services UPDATE only touches a row whose
-- text is still exactly the old combined wording, so anything you have
-- since rewritten yourself is left alone, and the INSERT is guarded so it
-- never creates a duplicate "mobile-apps" row.

UPDATE services SET
  name = 'Web Applications',
  tagline = 'Custom web tools — portals, dashboards and booking systems that run in the browser.',
  short_description = 'Booking systems, portals, dashboards and internal tools, built for the browser with the reporting and access control you need.',
  description = '<p>When a spreadsheet stops scaling, or your team needs one shared place to work, this is that. We map the workflow first, tell you what it takes to build and what can wait for later, then build a web application around it — with the reporting and access control you actually need.</p><p>Tools we build most: booking and ordering systems, customer portals, admin dashboards, and internal tools for a team that needs one shared source of truth.</p>',
  deliverables = 'Workflow mapped before any code is written\nCustom-built web application, not a template\nRole-based access and admin dashboards\nReporting, exports and third-party integrations\nHosted, monitored and kept up to date',
  seo_title = 'Custom Web Application Development',
  seo_description = 'Booking systems, portals and internal tools designed around your existing business workflows.'
WHERE slug = 'web-applications'
  AND name = 'Web & Mobile Apps';

INSERT INTO services
  (slug, name, tagline, short_description, description, icon, accent, deliverables, is_featured, is_published, sort_order, seo_title, seo_description, created_at, updated_at)
SELECT
  'mobile-apps', 'Mobile Apps', 'A native app for Android and iOS, built around what your customers actually need to do.',
  'A customer-facing or internal app for Android and iOS — booking, ordering, loyalty, or field data capture.',
  '<p>When the thing you need only makes sense on a phone, this is that. We map the workflow first, then build a native app shipped to both app stores — with the offline support and notifications a phone-first tool needs.</p><p>Apps we build most: booking and ordering, loyalty programmes, field data capture, and internal tools for a team that works away from a desk.</p>',
  'device', 'cyan',
  'Workflow mapped before any code is written\nNative builds for Android and iOS\nApp store submission and release management\nPush notifications and offline-capable data\nRole-based access where the app needs it',
  1, 1, (SELECT sort_order FROM (SELECT sort_order FROM services WHERE slug = 'web-applications') w) + 1,
  'Custom Mobile App Development — Android & iOS',
  'Native mobile apps for Android and iOS, built around the workflow your customers or team actually use.',
  NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM services WHERE slug = 'mobile-apps');

-- The new row's sort_order may tie with whatever used to come right after
-- "Web Applications" — that only affects display order, and is easiest to
-- fix from the admin panel if it matters, rather than renumbering here.

-- The "ships to both app stores" bullet belongs to the mobile service now.
INSERT INTO service_features (service_id, title, description, icon, sort_order)
SELECT id, 'Ships to both app stores', 'Android and iOS, submitted and managed through release.', '', 1 FROM services
WHERE slug = 'mobile-apps'
  AND NOT EXISTS (SELECT 1 FROM service_features WHERE service_id = services.id AND title = 'Ships to both app stores');

DELETE sf FROM service_features sf JOIN services s ON s.id = sf.service_id
WHERE s.slug = 'web-applications' AND sf.title = 'Ships to both app stores';
