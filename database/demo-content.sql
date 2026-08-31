-- =====================================================================
-- TECHBISS — OPTIONAL DEMO CONTENT
--
-- ⚠  EVERY COMPANY, PERSON AND QUOTE BELOW IS FICTIONAL.
--
-- This file exists so you can explore a populated CMS before you have real
-- case studies. None of it describes work TECHBISS has actually done, and no
-- testimonial here was given by a real client.
--
-- DO NOT load this on a production site. If you already have, remove it with:
--     mysql -u USER -p DATABASE < database/demo-content-remove.sql
--
-- Load with:
--     mysql -u USER -p DATABASE < database/demo-content.sql
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Idempotent: remove any previously loaded demo rows first.
-- Demo records are identifiable by their slugs and the "(fictional)" marker.
-- ---------------------------------------------------------------------
DELETE FROM `portfolio` WHERE `slug` IN
  ('meridian-bakehouse','northgate-dental','harbour-supply-co','stonebridge-construction','alloy-fabrication','verdant-wellness');
DELETE FROM `testimonials` WHERE `company` LIKE '%(fictional)%';
DELETE FROM `blog_posts` WHERE `slug` IN
  ('who-actually-owns-your-domain','business-email-deliverability','what-a-website-costs');
DELETE FROM `stats` WHERE `label` IN
  ('Services under one partner','Industries we build for','Ownership stays with you');

-- ---------------------------------------------------------------------
-- Portfolio projects — FICTIONAL CLIENTS
-- ---------------------------------------------------------------------
INSERT INTO `portfolio`
  (`slug`,`title`,`category_id`,`industry_id`,`client_name`,`short_description`,`overview`,`challenge`,`solution`,`results`,
   `thumbnail`,`hero_image`,`project_date`,`duration`,`accent`,`is_featured`,`is_published`,`sort_order`,
   `seo_title`,`seo_description`,`created_at`,`updated_at`)
VALUES
('meridian-bakehouse','Meridian Bakehouse',
 (SELECT id FROM portfolio_categories WHERE slug='website'),
 (SELECT id FROM industries WHERE slug='restaurants'),
 'Meridian Bakehouse (fictional)',
 'A twelve-year-old bakery with no website, moved onto its own domain with online ordering for collection.',
 '<p>Meridian Bakehouse had run on word of mouth and a busy counter for over a decade. Everything worked — until customers started asking for opening hours on a platform the owners did not control, and orders arrived as voicemails nobody had time to return.</p>',
 '<p>There was no website, no custom domain and no way to see the product range without walking in. Wholesale enquiries came through a personal email address, which made a serious order feel like a casual one. The busiest two hours of the day were also the two hours nobody could answer the phone.</p>',
 '<p>We registered the domain in the bakery''s name, provisioned managed hosting with SSL, and built a site around the two things customers actually wanted: what is available today, and how to order it for collection.</p><p>A lightweight ordering flow captures collection orders with a time slot, and wholesale enquiries route to a monitored business mailbox rather than a personal one. The team updates the daily range themselves from the admin panel.</p>',
 '<p>Collection orders now arrive in writing with a time attached, instead of as voicemails during service. Wholesale enquiries land in a shared inbox the whole team can see. The bakery owns its domain, its hosting and its customer list.</p>',
 'uploads/media/demo/meridian-thumb.jpg','uploads/media/demo/meridian-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'5 weeks','amber',1,1,1,
 'Meridian Bakehouse — Website & Online Ordering Case Study',
 'How a twelve-year-old bakery moved from voicemail orders to its own domain, website and online collection ordering.',
 NOW(),NOW()),

('northgate-dental','Northgate Dental Group',
 (SELECT id FROM portfolio_categories WHERE slug='web-app'),
 (SELECT id FROM industries WHERE slug='healthcare'),
 'Northgate Dental Group (fictional)',
 'A four-practice dental group replaced paper appointment books with an online booking and reminder system.',
 '<p>Northgate runs four practices with separate reception teams and, until this project, four separate paper diaries. Patients booked by phone during opening hours only, and no-shows were absorbed as a cost of doing business.</p>',
 '<p>Availability lived in four places that never quite agreed. A patient moving between practices had to be re-explained to each reception. Reminder calls took a receptionist most of an afternoon each week, and were skipped whenever the practice got busy — which was exactly when no-shows hurt most.</p>',
 '<p>We built a booking application with a shared availability model across all four sites, role-based access so each reception sees its own diary plus group-level reporting, and automated reminders at 48 and 4 hours.</p><p>Patient records stay in the practice management system; the application holds only what a booking needs. Data handling was scoped with the practice''s own privacy adviser before build.</p>',
 '<p>Reception teams book across practices without phoning each other, and reminders go out on schedule regardless of how busy the front desk is. Group-level reporting shows utilisation per practice for the first time.</p>',
 'uploads/media/demo/northgate-thumb.jpg','uploads/media/demo/northgate-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 7 MONTH),'11 weeks','violet',1,1,2,
 'Northgate Dental Group — Booking System Case Study',
 'A multi-site dental group moved from four paper diaries to one shared online booking system with automated reminders.',
 NOW(),NOW()),

('harbour-supply-co','Harbour Supply Co.',
 (SELECT id FROM portfolio_categories WHERE slug='ecommerce'),
 (SELECT id FROM industries WHERE slug='retail'),
 'Harbour Supply Co. (fictional)',
 'A marine chandlery took its 4,000-line catalogue online with trade pricing and click-and-collect.',
 '<p>Harbour Supply had served boatyards and private owners from one counter for thirty years. The catalogue lived in a printed folder and in the head of a manager approaching retirement.</p>',
 '<p>Stock existed in an accounting package that nobody outside the office could see. Trade customers had negotiated pricing that only two people knew. Every enquiry — including repeat orders for identical parts — required a phone call and a manual lookup.</p>',
 '<p>We built a store around the way the business actually fulfils: catalogue imported and reconciled against the accounting system, trade accounts with per-customer pricing tiers, and click-and-collect alongside delivery because most customers are already at the marina.</p><p>Stock levels sync nightly, so the site never promises something the shelf cannot deliver.</p>',
 '<p>Trade customers reorder without phoning, and the counter team spends less of the day on lookups. Pricing rules now live in a system rather than in one person''s memory — which was the real risk the business faced.</p>',
 'uploads/media/demo/harbour-thumb.jpg','uploads/media/demo/harbour-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 10 MONTH),'14 weeks','emerald',1,1,3,
 'Harbour Supply Co. — E-commerce Case Study',
 'A marine chandlery moved a 4,000-line catalogue online with trade pricing, stock sync and click-and-collect.',
 NOW(),NOW()),

('stonebridge-construction','Stonebridge Construction',
 (SELECT id FROM portfolio_categories WHERE slug='website'),
 (SELECT id FROM industries WHERE slug='construction'),
 'Stonebridge Construction (fictional)',
 'A regional contractor built a project portfolio and tender-ready capability site.',
 '<p>Stonebridge had completed substantial work for two decades with nothing online to show for it. Procurement teams asking for evidence received a PDF assembled by hand each time.</p>',
 '<p>Every tender required someone to rebuild the same capability statement from scratch. Certifications and completed-project evidence lived in a shared drive with no structure. There was no way for a procurement team to verify the company independently before a first meeting.</p>',
 '<p>We built a project portfolio with structured records — value band, sector, duration, certifications held at the time — and a capability page that a procurement team can read without asking for anything. Project entries are added by the team as work completes, so the evidence stays current.</p>',
 '<p>Tender responses now reference a live portfolio instead of rebuilding a document. Procurement teams can verify capability before the first call, which changes the tone of that call.</p>',
 'uploads/media/demo/stonebridge-thumb.jpg','uploads/media/demo/stonebridge-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 13 MONTH),'6 weeks','rose',0,1,4,
 'Stonebridge Construction — Portfolio Website Case Study',
 'A regional contractor replaced hand-built tender PDFs with a structured, live project portfolio.',
 NOW(),NOW()),

('alloy-fabrication','Alloy Fabrication',
 (SELECT id FROM portfolio_categories WHERE slug='web-app'),
 (SELECT id FROM industries WHERE slug='manufacturing'),
 'Alloy Fabrication (fictional)',
 'A metal fabricator replaced emailed spreadsheets with a distributor portal and RFQ system.',
 '<p>Alloy supplies components to distributors across three countries. Quoting ran on emailed spreadsheets, with version conflicts that occasionally reached customers.</p>',
 '<p>Distributors requested quotes by email, often against an outdated price list. Sales rebuilt the same quote repeatedly. Nobody could answer “what did we quote them last time” without searching a mailbox.</p>',
 '<p>We built a distributor portal with authenticated access, current specifications and pricing per distributor tier, and a structured RFQ flow that produces a quote record rather than an email thread. Quote history is visible to both sides.</p>',
 '<p>Quotes are issued against current pricing by construction, not by discipline. Sales can answer history questions in seconds, and distributors stopped quoting from stale spreadsheets.</p>',
 'uploads/media/demo/alloy-thumb.jpg','uploads/media/demo/alloy-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 16 MONTH),'12 weeks','blue',0,1,5,
 'Alloy Fabrication — Distributor Portal Case Study',
 'A manufacturer replaced emailed quote spreadsheets with a distributor portal and structured RFQ system.',
 NOW(),NOW()),

('verdant-wellness','Verdant Wellness Studio',
 (SELECT id FROM portfolio_categories WHERE slug='branding'),
 (SELECT id FROM industries WHERE slug='beauty-wellness'),
 'Verdant Wellness Studio (fictional)',
 'A new studio launched with a full brand identity, booking system and memberships.',
 '<p>Verdant opened with a strong offer and no identity beyond a name. Everything — signage, site, class listings, membership paperwork — had to exist before the doors opened.</p>',
 '<p>A launch date fixed by a lease, and nothing designed. Class booking had to work from day one, because a wellness studio with a phone-only booking process loses the customers it most wants.</p>',
 '<p>We built the identity first — mark, palette, typography and the rules for applying them — then the site, class booking and membership flow on top of it. Everything shipped in one sequence rather than as separate projects.</p>',
 '<p>The studio opened with online booking working, memberships purchasable, and a consistent identity across signage, print and web. The founders spent launch week teaching classes rather than answering booking calls.</p>',
 'uploads/media/demo/verdant-thumb.jpg','uploads/media/demo/verdant-hero.jpg',
 DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'8 weeks','cyan',1,1,6,
 'Verdant Wellness Studio — Branding & Booking Case Study',
 'A new wellness studio launched with a complete brand identity, website, class booking and memberships.',
 NOW(),NOW());

-- Gallery images
INSERT INTO `portfolio_images` (`portfolio_id`,`path`,`alt_text`,`caption`,`sort_order`)
SELECT p.id, CONCAT('uploads/media/demo/', g.stub, '-', g.shot, '.jpg'), CONCAT(p.title, ' interface'), g.caption, g.ord
FROM `portfolio` p JOIN (
  SELECT 'meridian' AS stub,'shot1' AS shot,'Daily range, editable by the counter team' AS caption,1 AS ord
  UNION ALL SELECT 'meridian','shot2','Collection ordering with time slots',2
  UNION ALL SELECT 'northgate','shot1','Shared availability across four practices',1
  UNION ALL SELECT 'northgate','shot2','Reception diary view',2
  UNION ALL SELECT 'harbour','shot1','Trade pricing tiers applied at checkout',1
  UNION ALL SELECT 'harbour','shot2','Click-and-collect fulfilment',2
  UNION ALL SELECT 'stonebridge','shot1','Structured project records',1
  UNION ALL SELECT 'alloy','shot1','Distributor RFQ flow',1
  UNION ALL SELECT 'verdant','shot1','Class booking and memberships',1
  UNION ALL SELECT 'verdant','shot2','Brand system applied across touchpoints',2
) g ON p.slug LIKE CONCAT(g.stub, '%');

-- Technologies
INSERT IGNORE INTO `portfolio_technology_map` (`portfolio_id`,`technology_id`)
SELECT p.id, t.id FROM `portfolio` p JOIN `portfolio_technologies` t
  ON t.slug IN ('mysql','javascript','php')
WHERE p.slug IN ('meridian-bakehouse','northgate-dental','stonebridge-construction');
INSERT IGNORE INTO `portfolio_technology_map` (`portfolio_id`,`technology_id`)
SELECT p.id, t.id FROM `portfolio` p JOIN `portfolio_technologies` t
  ON t.slug IN ('woocommerce','mysql','php','stripe')
WHERE p.slug = 'harbour-supply-co';
INSERT IGNORE INTO `portfolio_technology_map` (`portfolio_id`,`technology_id`)
SELECT p.id, t.id FROM `portfolio` p JOIN `portfolio_technologies` t
  ON t.slug IN ('laravel','mysql','typescript','redis')
WHERE p.slug = 'alloy-fabrication';
INSERT IGNORE INTO `portfolio_technology_map` (`portfolio_id`,`technology_id`)
SELECT p.id, t.id FROM `portfolio` p JOIN `portfolio_technologies` t
  ON t.slug IN ('figma','php','tailwind')
WHERE p.slug = 'verdant-wellness';

-- Services credited on each project
INSERT IGNORE INTO `portfolio_services` (`portfolio_id`,`service_id`)
SELECT p.id, s.id FROM `portfolio` p JOIN `services` s ON s.slug IN ('business-website','domain-hosting','business-email')
WHERE p.slug IN ('meridian-bakehouse','stonebridge-construction');
INSERT IGNORE INTO `portfolio_services` (`portfolio_id`,`service_id`)
SELECT p.id, s.id FROM `portfolio` p JOIN `services` s ON s.slug IN ('web-applications','business-website','maintenance')
WHERE p.slug IN ('northgate-dental','alloy-fabrication');
INSERT IGNORE INTO `portfolio_services` (`portfolio_id`,`service_id`)
SELECT p.id, s.id FROM `portfolio` p JOIN `services` s ON s.slug IN ('ecommerce','seo','automation-ai')
WHERE p.slug = 'harbour-supply-co';
INSERT IGNORE INTO `portfolio_services` (`portfolio_id`,`service_id`)
SELECT p.id, s.id FROM `portfolio` p JOIN `services` s ON s.slug IN ('branding','business-website','web-applications')
WHERE p.slug = 'verdant-wellness';

-- ---------------------------------------------------------------------
-- Testimonials — FICTIONAL. Replace every one of these before going live.
-- ---------------------------------------------------------------------
INSERT INTO `testimonials`
  (`client_name`,`company`,`position`,`rating`,`quote`,`portfolio_id`,`is_featured`,`is_published`,`sort_order`,`created_at`,`updated_at`)
VALUES
('Dolores Okonkwo','Meridian Bakehouse (fictional)','Owner',5,
 'We had run the bakery for twelve years without a website and genuinely did not know where to start. What made the difference was that nobody tried to sell us something clever — they asked how the shop actually works, then built around it. The collection orders arrive in writing now, which sounds small until you have spent a decade returning voicemails.',
 (SELECT id FROM portfolio WHERE slug='meridian-bakehouse'),1,1,1,NOW(),NOW()),
('Rahul Varma','Northgate Dental Group (fictional)','Practice Manager',5,
 'Four practices, four paper diaries, and a receptionist losing an afternoon a week to reminder calls. The booking system took that away without disrupting the practice software we already depend on. They were also straight with us about what they would not touch, which we appreciated more than a longer proposal.',
 (SELECT id FROM portfolio WHERE slug='northgate-dental'),1,1,2,NOW(),NOW()),
('Ingrid Bakke','Harbour Supply Co. (fictional)','Managing Director',5,
 'Our pricing rules lived in one man''s head and he was retiring. Getting them into a system was the real project; the shop front was almost a side effect. Trade customers reorder without phoning now, and the counter is quieter for the right reason.',
 (SELECT id FROM portfolio WHERE slug='harbour-supply-co'),1,1,3,NOW(),NOW()),
('Marcus Oyelaran','Stonebridge Construction (fictional)','Commercial Director',4,
 'Every tender used to start with someone rebuilding the same capability document. Now we point at a live portfolio. It has not won us work on its own, and they never claimed it would — but the first conversation starts in a different place.',
 (SELECT id FROM portfolio WHERE slug='stonebridge-construction'),0,1,4,NOW(),NOW()),
('Sofia Marchetti','Verdant Wellness Studio (fictional)','Founder',5,
 'We had a lease, a date and a name. They built the identity first and everything else on top of it, in one sequence, which is why it looks like one thing rather than five. Launch week I taught classes instead of answering the phone.',
 (SELECT id FROM portfolio WHERE slug='verdant-wellness'),1,1,5,NOW(),NOW());

-- ---------------------------------------------------------------------
-- Blog posts — general guidance, illustrative of the intended tone.
-- ---------------------------------------------------------------------
INSERT INTO `blog_posts`
  (`slug`,`title`,`excerpt`,`content`,`category_id`,`author_name`,`featured_image`,`reading_minutes`,
   `status`,`published_at`,`is_featured`,`seo_title`,`seo_description`,`created_at`,`updated_at`)
VALUES
('who-actually-owns-your-domain','Who actually owns your domain?',
 'If you cannot log in to your registrar today, you may not own the one digital asset that genuinely matters. Here is how to check, and what to do about it.',
 '<p>Ask a business owner who owns their domain and most will say “we do”. Ask them to log in to the registrar and a smaller number can. The gap between those two answers is where a lot of expensive problems live.</p><h2>Why it matters more than the website</h2><p>A website can be rebuilt in weeks. A domain registered to a former contractor, with contact details pointing at an inbox nobody reads, can take months to recover — and occasionally cannot be recovered at all. Your email runs on it. Your search history is attached to it. Every business card you have ever printed points at it.</p><h2>How to check, in ten minutes</h2><ul><li>Look up your domain in a public WHOIS service. Note the registrar and the registrant organisation.</li><li>Try to log in to that registrar with credentials you hold — not credentials someone can send you, credentials you have.</li><li>Check the administrative email address on the record. If it is a person who has left, that is the first thing to fix.</li><li>Check the expiry date and whether auto-renew is enabled.</li></ul><h2>If it is not in your name</h2><p>Most of the time this is not malice, just how it was set up years ago. Ask for a registrant transfer in writing. A reasonable supplier will do it without argument. A supplier who resists is telling you something useful.</p><h2>What good looks like</h2><p>The domain is registered to your business as the legal entity. An address you control is on the record. Auto-renew is on, with a card that has not expired. At least two people internally can log in. Registry lock is enabled if your registrar offers it.</p><p>None of this is technical work. It is an afternoon of administration that removes the single worst failure mode in a small business''s digital setup.</p>',
 (SELECT id FROM blog_categories WHERE slug='going-digital'),'TECHBISS','uploads/media/demo/alloy-thumb.jpg',4,
 'published',DATE_SUB(NOW(), INTERVAL 9 DAY),1,
 'Who Actually Owns Your Domain?',
 'How to check whether your business really owns its domain name, and what to do if it is registered to someone else.',
 NOW(),NOW()),

('business-email-deliverability','Why your business email lands in spam',
 'Moving to name@yourbusiness.com is the easy part. Three DNS records decide whether anyone receives it.',
 '<p>Switching from a free mailbox to your own domain is one of the cheapest credibility upgrades available to a business. It is also one of the easiest to get wrong, because sending mail and having mail arrive are different problems.</p><h2>The three records that decide it</h2><p><strong>SPF</strong> lists which servers may send mail claiming to be from your domain. Without it, anyone can. With a badly written one — more than ten DNS lookups, or two SPF records where there should be one — receiving servers give up and treat your mail as suspect.</p><p><strong>DKIM</strong> signs each message cryptographically so the receiver can verify it was not altered and did come from you. It is the difference between a claim and a proof.</p><p><strong>DMARC</strong> tells receiving servers what to do when SPF and DKIM disagree and — the part most setups skip — asks them to report back. Those reports are how you discover your invoicing system has been failing authentication for six months.</p><h2>The order that works</h2><ol><li>Publish SPF listing every service that legitimately sends as you: mail provider, CRM, invoicing tool, website contact form.</li><li>Enable DKIM signing at the provider and publish the public key.</li><li>Publish DMARC at <code>p=none</code> with a reporting address, and read the reports for a few weeks.</li><li>Only once the reports are clean, move to <code>p=quarantine</code>, then <code>p=reject</code>.</li></ol><h2>The mistake worth avoiding</h2><p>Setting DMARC to <code>p=reject</code> on day one feels decisive and will silently destroy legitimate mail from a system you forgot about. Start at <code>none</code>, read the data, then tighten. It costs three weeks and saves a very bad month.</p>',
 (SELECT id FROM blog_categories WHERE slug='going-digital'),'TECHBISS','uploads/media/demo/harbour-thumb.jpg',5,
 'published',DATE_SUB(NOW(), INTERVAL 20 DAY),0,
 'Why Your Business Email Lands in Spam',
 'SPF, DKIM and DMARC explained for business owners — the three DNS records that decide whether your email is delivered.',
 NOW(),NOW()),

('what-a-website-costs','What a business website actually costs',
 'Not a price list. An honest breakdown of where the money goes, and which line items are genuinely optional.',
 '<p>Quotes for the same brief routinely differ by a factor of ten, which tells a buyer nothing except that the market is confusing. It is less confusing once you know what is being priced.</p><h2>The parts that always cost something</h2><p><strong>Infrastructure</strong> — domain, hosting, SSL, backups. Small, annual, unavoidable. Anyone offering this free is recovering it elsewhere.</p><p><strong>Content structure</strong> — deciding what pages exist and what each must accomplish. Skipped on cheap projects, which is why cheap projects often produce a beautiful site that does not sell anything.</p><p><strong>Design and build</strong> — the largest line. A template configured costs a fraction of a custom design, and for many businesses a well-configured template is genuinely the right answer.</p><p><strong>Content itself</strong> — words and images. Frequently assumed free because the client will provide it. Frequently the reason a project stalls for three months.</p><h2>The parts that are optional</h2><p>Animation. Custom illustration. A bespoke CMS when a standard one fits. A second language before you have customers in one. These are real work and real value in the right context — but they are choices, and a supplier should tell you they are choices.</p><h2>The line item nobody quotes</h2><p>Maintenance. A site is software; unpatched software becomes a liability within about eighteen months. Budget for it from the start, or budget for a rebuild sooner than you expected.</p><h2>A reasonable way to buy</h2><p>Ask for the scope in writing, ask which items are optional, and ask what the annual running cost will be in year two. Any supplier who cannot answer the third question has not thought about your business past launch.</p>',
 (SELECT id FROM blog_categories WHERE slug='business'),'TECHBISS','uploads/media/demo/verdant-thumb.jpg',6,
 'published',DATE_SUB(NOW(), INTERVAL 34 DAY),0,
 'What a Business Website Actually Costs',
 'An honest breakdown of website pricing: what always costs money, what is optional, and the line item nobody quotes.',
 NOW(),NOW());

INSERT IGNORE INTO `blog_post_tags` (`post_id`,`tag_id`)
SELECT p.id, t.id FROM `blog_posts` p JOIN `blog_tags` t ON t.slug IN ('domain','hosting','security')
WHERE p.slug = 'who-actually-owns-your-domain';
INSERT IGNORE INTO `blog_post_tags` (`post_id`,`tag_id`)
SELECT p.id, t.id FROM `blog_posts` p JOIN `blog_tags` t ON t.slug IN ('email','security')
WHERE p.slug = 'business-email-deliverability';

-- ---------------------------------------------------------------------
-- Statistics — counts derived from your own catalogue, plus one factual claim.
-- Review these and replace anything you cannot stand behind.
-- ---------------------------------------------------------------------
INSERT INTO `stats` (`label`,`value`,`prefix`,`suffix`,`description`,`is_published`,`sort_order`,`created_at`,`updated_at`)
VALUES
('Services under one partner',CAST((SELECT COUNT(*) FROM services WHERE is_published=1) AS CHAR),'','',
 'Domain through to ongoing support',1,1,NOW(),NOW()),
('Industries we build for',CAST((SELECT COUNT(*) FROM industries WHERE is_published=1) AS CHAR),'','',
 'Each approached on its own terms',1,2,NOW(),NOW()),
('Ownership stays with you','100','','%','Domain, code, designs and accounts',1,3,NOW(),NOW());
