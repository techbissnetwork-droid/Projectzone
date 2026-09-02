<?php
/**
 * The content the site starts with. Everything here is editable from the
 * admin area afterwards — this only decides what is there on day one.
 */

function seed_all(): void
{
    seed_settings();
    seed_services();
    seed_industries();
    seed_packages();
    seed_addons();
    seed_faqs();
    seed_testimonials();
    seed_portfolio();
    seed_products();
}

/**
 * Every setting the site expects, as [group, key, label, value, type].
 * seed_settings() writes them all on a fresh install; seed_missing_settings()
 * adds only the ones a later version introduced, leaving edits alone.
 */
function setting_definitions(): array
{
    return [
        // group, key, label, value, type
        ['global', 'site.name', 'Company name', 'TECHBISS', 'text'],
        ['global', 'site.tagline', 'Footer description', 'One partner for everything a business needs online — domain, hosting, SSL, website, apps, email, branding, SEO and support.', 'textarea'],
        ['global', 'site.email', 'Public email address', 'hello@techbiss.com', 'text'],
        ['global', 'site.support_email', 'Support email address', 'support@techbiss.com', 'text'],
        ['global', 'site.phone', 'Phone number', '+1 000 000 0000', 'text'],
        ['global', 'site.hours', 'Opening hours', 'Mon–Fri, 9:00–18:00', 'text'],
        ['global', 'site.address', 'Address', '', 'textarea'],
        ['global', 'site.currency', 'Currency symbol', '$', 'text'],
        ['global', 'nav.cta', 'Header button label', 'Talk to us', 'text'],
        ['global', 'social.linkedin', 'LinkedIn URL', '#', 'text'],
        ['global', 'social.instagram', 'Instagram URL', '#', 'text'],
        ['global', 'social.whatsapp', 'WhatsApp URL', '#', 'text'],
        ['global', 'marquee.words', 'Scrolling word strip (one per line)', "Websites\nApps\nDomains\nHosting\nSSL\nEmail\nSEO\nBranding\nAutomation\nSupport", 'textarea'],

        ['home', 'home.meta.title', 'Browser tab title', 'TECHBISS — One partner for everything your business needs online', 'text'],
        ['home', 'home.meta.desc', 'Search description', 'TECHBISS builds websites and apps, and takes offline businesses online — domain, hosting, SSL, business email, SEO and support, all handled by one partner.', 'textarea'],
        ['home', 'home.hero.eyebrow', 'Hero eyebrow', 'Always on — one partner, every system', 'text'],
        ['home', 'home.hero.line1', 'Hero line 1 (chrome)', 'One partner.', 'text'],
        ['home', 'home.hero.line2', 'Hero line 2 (green)', 'Everything live.', 'text'],
        ['home', 'home.hero.lead', 'Hero paragraph', 'We build websites and apps, and take offline businesses online — domain, hosting, SSL, email, SEO and support, set up as one system and kept running. Not a handoff. Not a slideshow.', 'textarea'],
        ['home', 'home.hero.cta1', 'Hero primary button', 'See what we run →', 'text'],
        ['home', 'home.hero.cta2', 'Hero secondary button', 'Talk to us', 'text'],
        ['home', 'home.stack.heading', 'Stack section heading', 'Ten systems, one partner.', 'text'],
        ['home', 'home.stack.sub', 'Stack section subheading', 'Everything TECHBISS keeps live for a business, in four blocks instead of six vendors and six invoices. Keep scrolling — they stack.', 'textarea'],
        ['home', 'home.proof.heading', 'Proof heading', 'Not a mockup. A running system.', 'text'],
        ['home', 'home.stat1.value', 'Stat 1 number', '99.98%', 'text'],
        ['home', 'home.stat1.label', 'Stat 1 label', 'Uptime · 90 days', 'text'],
        ['home', 'home.stat2.value', 'Stat 2 number', '140+', 'text'],
        ['home', 'home.stat2.label', 'Stat 2 label', 'Systems live', 'text'],
        ['home', 'home.stat3.value', 'Stat 3 number', '100%', 'text'],
        ['home', 'home.stat3.label', 'Stat 3 label', 'Certs auto-renewed', 'text'],
        ['home', 'home.stat4.value', 'Stat 4 number', '<4h', 'text'],
        ['home', 'home.stat4.label', 'Stat 4 label', 'Support first reply', 'text'],
        ['home', 'home.process.heading', 'Process heading', 'How a project goes live.', 'text'],
        ['home', 'home.process.sub', 'Process subheading', 'Four steps, the same order every time — nothing skipped to hit a date.', 'textarea'],
        ['home', 'home.quotes.heading', 'Testimonials heading', 'What owners say afterwards.', 'text'],
        ['home', 'home.portfolio.heading', 'Portfolio teaser heading', 'Work we have shipped.', 'text'],
        ['home', 'home.portfolio.sub', 'Portfolio teaser subheading', 'Live systems, not concepts. A few of the businesses we run for.', 'textarea'],
        ['home', 'home.market.heading', 'Marketplace teaser heading', 'Or start from something built.', 'text'],
        ['home', 'home.market.sub', 'Marketplace teaser subheading', 'Premade projects you can buy today, set up on your own domain and hosting within a week.', 'textarea'],
        ['home', 'home.cta.heading', 'Closing heading', 'Ready to go live?', 'text'],
        ['home', 'home.cta.body', 'Closing text', 'One conversation, no script. Describe the stack you have today — or tell us you have none at all.', 'textarea'],

        ['services', 'services.meta.title', 'Browser tab title', 'Services — TECHBISS', 'text'],
        ['services', 'services.meta.desc', 'Search description', 'Websites, apps, domain, hosting, SSL, business email, SEO, branding, automation and support — all handled by one partner.', 'textarea'],
        ['services', 'services.hero.eyebrow', 'Hero eyebrow', 'Ten systems · one partner', 'text'],
        ['services', 'services.hero.line1', 'Hero line 1 (chrome)', 'Everything a business', 'text'],
        ['services', 'services.hero.line2', 'Hero line 2 (green)', 'needs online.', 'text'],
        ['services', 'services.hero.lead', 'Hero paragraph', 'Take one service or take all ten. Either way it is set up under your own accounts, documented, and watched after launch — not handed over as a zip file and a goodbye.', 'textarea'],
        ['services', 'services.list.heading', 'List heading', 'What we run, in detail.', 'text'],
        ['services', 'services.list.sub', 'List subheading', 'Each one can be bought on its own. Most businesses take the middle block — domain, hosting, SSL and email together — because that is the part nobody else joins up.', 'textarea'],
        ['services', 'services.statement', 'Big statement (use {braces} to highlight)', 'Take one service or take all ten. {Either way it is one partner} — and every account is registered in your name, not ours.', 'textarea'],
        ['services', 'services.cta.heading', 'Closing heading', 'Not sure which of these you need?', 'text'],
        ['services', 'services.cta.body', 'Closing text', 'Tell us what the business does and what is frustrating right now. We will tell you what is actually worth doing first.', 'textarea'],

        ['industries', 'industries.meta.title', 'Browser tab title', 'Industries — TECHBISS', 'text'],
        ['industries', 'industries.meta.desc', 'Search description', 'Retail, education, hospitality, legal, healthcare, real estate, trades and community organisations — taken online and kept running by one partner.', 'textarea'],
        ['industries', 'industries.hero.eyebrow', 'Hero eyebrow', 'Same stack · different day-to-day', 'text'],
        ['industries', 'industries.hero.line1', 'Hero line 1 (chrome)', 'Businesses we', 'text'],
        ['industries', 'industries.hero.line2', 'Hero line 2 (green)', 'run for.', 'text'],
        ['industries', 'industries.hero.lead', 'Hero paragraph', 'The setup underneath is the same everywhere — domain, hosting, SSL, email, site, listings, support. What changes is what the website has to do on the day, and that is what we design around.', 'textarea'],
        ['industries', 'industries.list.heading', 'List heading', 'Pick the one closest to yours.', 'text'],
        ['industries', 'industries.list.sub', 'List subheading', 'Not listed? The pattern still applies — tell us what a normal working day looks like and we will map it to a setup.', 'textarea'],
        ['industries', 'industries.statement', 'Big statement (use {braces} to highlight)', 'Whatever the sector, the failure is the same: {six suppliers, nobody responsible.} We take the whole thing.', 'textarea'],
        ['industries', 'industries.cta.heading', 'Closing heading', 'Your industry is not on the list?', 'text'],
        ['industries', 'industries.cta.body', 'Closing text', 'It rarely changes the answer. Describe a normal working day and we will map it to a setup.', 'textarea'],

        ['pricing', 'pricing.meta.title', 'Browser tab title', 'Pricing — TECHBISS', 'text'],
        ['pricing', 'pricing.meta.desc', 'Search description', 'Fixed-price website, app and full web setup packages, plus optional monthly care plans. Third-party costs passed through at cost, in your name.', 'textarea'],
        ['pricing', 'pricing.hero.eyebrow', 'Hero eyebrow', 'Fixed scope · fixed price', 'text'],
        ['pricing', 'pricing.hero.line1', 'Hero line 1 (chrome)', 'Prices you can see', 'text'],
        ['pricing', 'pricing.hero.line2', 'Hero line 2 (green)', 'before you call.', 'text'],
        ['pricing', 'pricing.hero.lead', 'Hero paragraph', 'Every package below is a fixed quote for a written scope. Third-party costs — domain registration, hosting, email licences — are billed to you at cost, in your own name, with nothing marked up.', 'textarea'],
        ['pricing', 'pricing.build.heading', 'Build packages heading', 'Build packages.', 'text'],
        ['pricing', 'pricing.build.sub', 'Build packages subheading', 'One-off cost to design, build and launch. The care plan is optional and separate.', 'textarea'],
        ['pricing', 'pricing.care.heading', 'Care plans heading', 'Care plans, after launch.', 'text'],
        ['pricing', 'pricing.care.sub', 'Care plans subheading', 'Optional, month to month, cancel whenever. This is what keeps things from lapsing.', 'textarea'],
        ['pricing', 'pricing.addons.heading', 'Add-ons heading', 'Add-ons, priced plainly.', 'text'],
        ['pricing', 'pricing.addons.sub', 'Add-ons subheading', 'Bolt any of these onto any package. Quoted before work starts, never after.', 'textarea'],
        ['pricing', 'pricing.statement', 'Big statement (use {braces} to highlight)', 'Third-party costs are passed through {at cost, in your name.} We do not mark up a domain and call it a service.', 'textarea'],
        ['pricing', 'pricing.cta.heading', 'Closing heading', 'Want an exact number?', 'text'],
        ['pricing', 'pricing.cta.body', 'Closing text', 'Describe the business in a few lines. You will get a written scope and a fixed price — no meeting required first.', 'textarea'],

        ['about', 'about.meta.title', 'Browser tab title', 'About — TECHBISS', 'text'],
        ['about', 'about.meta.desc', 'Search description', 'TECHBISS is one partner for everything a business needs online. How we work, and what we will not do.', 'textarea'],
        ['about', 'about.hero.eyebrow', 'Hero eyebrow', 'Who you would be working with', 'text'],
        ['about', 'about.hero.line1', 'Hero line 1 (chrome)', 'One team.', 'text'],
        ['about', 'about.hero.line2', 'Hero line 2 (green)', 'Every system.', 'text'],
        ['about', 'about.hero.lead', 'Hero paragraph', 'Most businesses we meet are not short of suppliers. They have a web designer who has gone quiet, a hosting bill nobody understands, and email that lands in spam. We take the whole thing and run it as one system.', 'textarea'],
        ['about', 'about.story.heading', 'Story heading', 'Why we work this way.', 'text'],
        ['about', 'about.story.body', 'Story text (blank line between paragraphs)', "Every business we take on arrives with the same story. The site was built by somebody who has moved on. The domain is registered to an agency that no longer answers. Hosting renews on a card nobody recognises. Email works, mostly, except when it does not.\n\nNone of that is one big failure. It is six small ones, spread across six suppliers, with nobody responsible for the whole. So we made the whole thing our job: register the domain in your name, provision the hosting, issue the certificate, set the mail records, build the site, list the business, then watch all of it afterwards.\n\nThat is the entire idea. It is not clever. It is just nobody else's job until it is somebody's job.", 'textarea'],
        ['about', 'about.rules.heading', 'Principles heading', 'How we work.', 'text'],
        ['about', 'about.cta.heading', 'Closing heading', 'Start with a conversation.', 'text'],
        ['about', 'about.cta.body', 'Closing text', 'No script and no deck. Tell us what the business does and what is not working.', 'textarea'],

        ['portfolio', 'portfolio.meta.title', 'Browser tab title', 'Work — TECHBISS', 'text'],
        ['portfolio', 'portfolio.meta.desc', 'Search description', 'Websites, apps and full web setups TECHBISS has built and still keeps running.', 'textarea'],
        ['portfolio', 'portfolio.hero.eyebrow', 'Hero eyebrow', 'Completed and live', 'text'],
        ['portfolio', 'portfolio.hero.line1', 'Hero line 1 (chrome)', 'Work we have', 'text'],
        ['portfolio', 'portfolio.hero.line2', 'Hero line 2 (green)', 'shipped.', 'text'],
        ['portfolio', 'portfolio.hero.lead', 'Hero paragraph', 'Every project here is live, and most are still on our monitoring. Where a client asked us not to name them, the work is described without the name.', 'textarea'],
        ['portfolio', 'portfolio.empty', 'Text shown when nothing is published yet', 'The first case studies are being written up. Ask us for examples in the meantime — we will send them over.', 'textarea'],

        ['marketplace', 'market.meta.title', 'Browser tab title', 'Marketplace — TECHBISS', 'text'],
        ['marketplace', 'market.meta.desc', 'Search description', 'Premade websites and apps you can buy today, set up on your own domain and hosting by the people who built them.', 'textarea'],
        ['marketplace', 'market.hero.eyebrow', 'Hero eyebrow', 'Built already · yours this week', 'text'],
        ['marketplace', 'market.hero.line1', 'Hero line 1 (chrome)', 'Premade projects,', 'text'],
        ['marketplace', 'market.hero.line2', 'Hero line 2 (green)', 'ready to run.', 'text'],
        ['marketplace', 'market.hero.lead', 'Hero paragraph', 'Complete, finished projects at a fixed price. Buy one as it is, or have us set it up on your own domain, hosting, SSL and email and hand you the keys.', 'textarea'],
        ['marketplace', 'market.empty', 'Text shown when nothing is listed yet', 'Nothing is listed right now. Tell us what you are after and we will say whether we have something close.', 'textarea'],
        ['marketplace', 'market.setup.price', 'Price of the optional setup service', '450', 'text'],
        ['marketplace', 'market.setup.blurb', 'What the setup service includes', 'We register or point your domain, provision hosting, issue the SSL certificate, set up business email with SPF, DKIM and DMARC, install the project, put your own content in, and hand over the logins.', 'textarea'],

        ['contact', 'contact.meta.title', 'Browser tab title', 'Contact — TECHBISS', 'text'],
        ['contact', 'contact.meta.desc', 'Search description', 'Tell us what the business does and what is not working. We answer within one business day.', 'textarea'],
        ['contact', 'contact.hero.eyebrow', 'Hero eyebrow', 'One conversation · no script', 'text'],
        ['contact', 'contact.hero.line1', 'Hero line 1 (chrome)', 'Tell us what', 'text'],
        ['contact', 'contact.hero.line2', 'Hero line 2 (green)', 'you run.', 'text'],
        ['contact', 'contact.hero.lead', 'Hero paragraph', 'Describe the business and what is frustrating right now. You will get a straight answer about what is worth doing first — and what is not.', 'textarea'],
        ['contact', 'contact.form.heading', 'Form heading', 'Send it over.', 'text'],
        ['contact', 'contact.form.note', 'Small print under the form', 'We reply within one business day. Your details are used to answer you and nothing else.', 'textarea'],
        ['contact', 'contact.thanks', 'Message shown after sending', 'Thanks — that reached us. We answer within one business day.', 'textarea'],
    ];
}

function seed_settings(): void
{
    $sort = 0;
    foreach (setting_definitions() as [$group, $key, $label, $value, $type]) {
        db_insert('settings', [
            'group_name'  => $group,
            'setting_key' => $key,
            'label'       => $label,
            'value'       => $value,
            'field_type'  => $type,
            'sort'        => $sort++,
        ]);
    }
}

/**
 * Add settings introduced since this site was installed. Existing rows are
 * left exactly as they are — a migration never overwrites edited copy.
 * Returns the keys it added.
 */
function seed_missing_settings(): array
{
    $have = [];
    foreach (db_all('SELECT setting_key FROM settings') as $row) {
        $have[$row['setting_key']] = true;
    }
    $added = [];
    $sort  = (int) db_value('SELECT COALESCE(MAX(sort), 0) FROM settings');
    foreach (setting_definitions() as [$group, $key, $label, $value, $type]) {
        if (isset($have[$key])) {
            continue;
        }
        db_insert('settings', [
            'group_name'  => $group,
            'setting_key' => $key,
            'label'       => $label,
            'value'       => $value,
            'field_type'  => $type,
            'sort'        => ++$sort,
        ]);
        $added[] = $key;
    }
    return $added;
}

function seed_services(): void
{
    $rows = [
        ['Websites', 'Design & build', '◈',
         'Designed and built for the business you actually run, not for a template gallery. Fast on a phone, readable by Google, and yours to edit afterwards without calling us.',
         "Five to fifty pages, structured around what customers actually look for\nA CMS you can use — text, images and prices, no HTML\nWritten with you, so the copy says what the business does\nTested on real phones, not just a desktop browser"],
        ['Web & mobile apps', 'When a page is not enough', '◐',
         'Booking, ordering, member portals, internal dashboards. Built when the work has outgrown a spreadsheet and a phone line.',
         "Booking and scheduling with reminders that actually send\nCustomer portals — accounts, documents, order history\nInternal dashboards for the people running the place\nBuilt on your own hosting, exportable, never locked to us"],
        ['Domains', 'Registered in your name', '◇',
         'The single most common thing we fix: a domain sitting in a previous agency\'s account. Yours goes in your name, on your registrar account, with you as the owner of record.',
         "Registration, transfer and renewal, in your name\nDNS configured and documented so it can be handed on\nTransfer lock and expiry monitoring\nWe can hold the admin work without holding the asset"],
        ['Hosting', 'Provisioned and watched', '▣',
         'Set up, tuned for the site that is actually on it, and monitored afterwards. Backups run nightly whether anyone remembers or not.',
         "Provisioning, migration and tuning\nNightly backups with 30-day retention\nUptime checks every 60 seconds from multiple regions\nStaging site for changes, so nothing is tested in public"],
        ['SSL & security', 'Nothing lapses quietly', '⛨',
         'Certificates issued, renewed automatically, and watched. An expired certificate takes a business offline on a Sunday afternoon; this is the boring work that prevents it.',
         "Certificates issued and auto-renewed before expiry\nExpiry monitoring with alerts to us, not to you\nHardening — headers, admin access, software updates\nMalware scanning and clean-up if something does get through"],
        ['Business email', 'Mail that lands', '✉',
         'you@yourbusiness.com, with SPF, DKIM and DMARC set properly, so your invoices and quotes arrive in inboxes instead of spam folders.',
         "Mailboxes on your own domain\nSPF, DKIM and DMARC configured and verified\nMigration from Gmail, Yahoo or an old host without losing mail\nShared mailboxes and aliases for teams"],
        ['SEO & listings', 'Found when it counts', '◎',
         'The technical groundwork so search engines can read the site, plus the listings work so the business shows up on Maps with the right hours and phone number.',
         "Technical SEO — structure, speed, sitemap, indexing\nGoogle Business Profile set up and verified\nHours, address and phone kept accurate across directories\nMonthly reporting in plain language, not a 40-page PDF"],
        ['Branding', 'The look, everywhere', '✦',
         'Logo, colour, type and the files you need for everything else — signage, social, invoices, vehicle livery, the lot.',
         "Logo in every format you will ever be asked for\nColour and type defined, so things match\nSocial and print templates\nA one-page guide so other suppliers stay on brand"],
        ['Automation', 'Fewer things done by hand', '⟲',
         'The repetitive parts — enquiries, invoices, reminders, reports — handed to software so somebody stops doing them at 9pm.',
         "Enquiry routing straight into your inbox or CRM\nInvoice and receipt generation\nAppointment and payment reminders\nConnecting the tools you already pay for"],
        ['Support', 'One number, same people', '☎',
         'The people who answer are the people who built it. No ticket queue in another timezone and no explaining your setup from scratch every time.',
         "First reply within four business hours\nSame-day fixes for anything that takes the site down\nContent and small changes handled for you on a care plan\nA written record of your setup, so nothing lives in one head"],
    ];
    $i = 0;
    foreach ($rows as [$title, $subtitle, $icon, $body, $bullets]) {
        db_insert('services', [
            'slug'      => slugify($title),
            'title'     => $title,
            'subtitle'  => $subtitle,
            'icon'      => $icon,
            'body'      => $body,
            'bullets'   => $bullets,
            'sort'      => $i++,
            'is_active' => 1,
        ]);
    }
}

function seed_industries(): void
{
    $rows = [
        ['Retail & shops', '◬', 'Stock that changes, opening hours that matter, and customers who search on a phone while standing outside.',
         "Google Maps hours that are always right\nProduct or menu pages that are quick to update\nClick-and-collect or full checkout\nReviews pointed somewhere useful"],
        ['Schools & training', '◫', 'Parents, students and staff all need different things from the same site, usually on the same evening.',
         "Term dates, newsletters and closures\nAdmissions and enquiry forms\nStaff logins and notices\nEmail on the school domain, not personal accounts"],
        ['Hospitality', '◉', 'Bookings, menus and photographs. If the phone rings less after launch, that is the point.',
         "Table or room booking that syncs\nMenus editable in two minutes\nPhoto galleries that stay fast\nListings consistent across every directory"],
        ['Legal & professional', '◆', 'Credibility first. The site has to look like the firm charges what it charges.',
         "Practice areas and people pages\nSecure enquiry intake\nDocuments and client portal\nEmail set up so client mail is never in spam"],
        ['Healthcare & clinics', '✚', 'Appointments, practitioners, and clear information for people who are worried.',
         "Appointment requests and reminders\nPractitioner profiles and hours\nCareful handling of patient enquiries\nMulti-location details kept in step"],
        ['Real estate', '▢', 'Listings that change weekly, and buyers who leave the moment a page is slow.',
         "Property listings with photos and filters\nEnquiries routed to the right agent\nPortal feeds where you use them\nMaps and area pages that rank"],
        ['Trades & services', '⚙', 'You are on a roof, not at a desk. The site has to sell while you work.',
         "Quote request forms straight to your phone\nService areas that show up locally\nBefore-and-after galleries\nReviews and accreditations up front"],
        ['Community & nonprofit', '♡', 'Small budgets, many volunteers, and a need for things not to break when the person who set them up moves on.',
         "Events, notices and newsletters\nDonations or membership\nShared mailboxes for handover\nDocumented setup so nothing lives in one head"],
    ];
    $i = 0;
    foreach ($rows as [$title, $icon, $body, $bullets]) {
        db_insert('industries', [
            'slug'      => slugify($title),
            'title'     => $title,
            'icon'      => $icon,
            'body'      => $body,
            'bullets'   => $bullets,
            'sort'      => $i++,
            'is_active' => 1,
        ]);
    }
}

function seed_packages(): void
{
    $build = [
        ['Starter', '1,400', 'One-off', 'A business with nothing online yet, or a site that has aged badly.',
         "Up to 5 pages\nDomain + hosting set up\nSSL & business email\nGoogle Business listing\n30 days aftercare", 0],
        ['Business', '3,900', 'One-off', 'A working business that needs the whole stack set up properly, once.',
         "Up to 15 pages + CMS\nFull web setup, your accounts\nEmail deliverability configured\nSEO groundwork & listings\n90 days aftercare", 1],
        ['Custom', '', 'To scope', 'Anything with an app, a portal, a shop or a migration in it.',
         "Apps, portals, e-commerce\nIntegrations & automation\nMigration from existing vendors\nPriority support line\nOngoing care plan", 0],
    ];
    $care = [
        ['Essential', '90', 'per month', 'Keeps the lights on and nothing lapsing.',
         "Uptime monitoring\nNightly backups\nSSL & domain renewals\nSecurity updates\nEmail support", 0],
        ['Standard', '180', 'per month', 'Everything in Essential, plus the small jobs done for you.',
         "Everything in Essential\n2 hours of changes a month\nMonthly search report\nPriority replies\nStaging site for changes", 1],
        ['Managed', '390', 'per month', 'For businesses where the website is the front door.',
         "Everything in Standard\n6 hours of changes a month\nQuarterly review call\nSame-day response\nDirect line to the team", 0],
    ];
    $i = 0;
    foreach ($build as [$n, $p, $per, $blurb, $f, $hot]) {
        db_insert('packages', ['name' => $n, 'kind' => 'build', 'price' => $p, 'period' => $per,
            'blurb' => $blurb, 'features' => $f, 'is_featured' => $hot, 'sort' => $i++, 'is_active' => 1]);
    }
    $i = 0;
    foreach ($care as [$n, $p, $per, $blurb, $f, $hot]) {
        db_insert('packages', ['name' => $n, 'kind' => 'care', 'price' => $p, 'period' => $per,
            'blurb' => $blurb, 'features' => $f, 'is_featured' => $hot, 'sort' => $i++, 'is_active' => 1]);
    }
}

function seed_addons(): void
{
    $rows = [
        ['Extra page', '120', 'Designed and written to match the rest of the site.'],
        ['E-commerce', '900', 'Products, cart, checkout and a payment provider connected.'],
        ['Booking system', '750', 'Online booking with confirmations and reminders.'],
        ['Logo & brand kit', '600', 'Logo in every format, colour and type defined, one-page guide.'],
        ['Content writing', '90', 'Per page, written with you rather than guessed at.'],
        ['Migration', '350', 'Moving an existing site, mail and DNS without losing anything.'],
        ['Extra mailbox', '4', 'Per mailbox, per month, on your own domain.'],
        ['Photography day', '550', 'A day on site, edited images delivered ready for the web.'],
    ];
    $i = 0;
    foreach ($rows as [$n, $p, $b]) {
        db_insert('addons', ['name' => $n, 'price' => $p, 'blurb' => $b, 'sort' => $i++]);
    }
}

function seed_faqs(): void
{
    $rows = [
        ['services', 'Do I have to take all ten?', 'No. Plenty of clients come to us for one thing — usually email that keeps landing in spam, or a domain they cannot get control of. We will happily do that one thing and leave the rest alone.'],
        ['services', 'Who owns the domain and hosting?', 'You do. Everything is registered in your business name, on accounts you control, and you get the logins in writing at handover. We do the admin; we never hold the asset. If you leave, nothing has to move.'],
        ['services', 'We already have a website. Is it a rebuild?', 'Often not. If the site works and just needs speed, security, email deliverability or search fixed, we will tell you that and quote for the fix. We would rather do the smaller job well than sell you a rebuild you did not need.'],
        ['services', 'Can we edit the site ourselves?', 'Yes. Text, images, prices, opening hours and pages are editable from an admin area, with no HTML. We show you how it works before handover, and the care plan covers it if you would rather we did it.'],
        ['services', 'How long does it take?', 'A Starter build is usually two to three weeks from the day content is agreed. Business is four to six. The long pole is almost always waiting on photos and copy from your side — the build itself rarely holds things up.'],
        ['pricing', 'Is the price really fixed?', 'Yes, against a written scope. If you ask for something outside that scope we quote it before doing it, never after. The one thing that moves a price is you changing what you want, and you will always see that number first.'],
        ['pricing', 'What are third-party costs?', 'Domain registration, hosting, email licences and any paid plugin. They are billed to you at cost, in your own name, with nothing added. Roughly $80 to $300 a year for a small business, depending on the domain and mailbox count.'],
        ['pricing', 'Do I have to take a care plan?', 'No. It is optional and month to month. Without one, the site is yours to run and we are here when you need us at an hourly rate. With one, the renewals, backups and updates are watched so nothing lapses.'],
        ['pricing', 'How do payments work?', 'Half to start, half on launch, for build packages. Care plans are monthly in advance and you can cancel any time. No long contracts, no cancellation fee.'],
        ['pricing', 'What if I want to leave?', 'You take everything with you. The domain, hosting and email are already in your name, and we hand over the site files and database along with the logins. There is nothing to unlock.'],
    ];
    $i = 0;
    foreach ($rows as [$page, $q, $a]) {
        db_insert('faqs', ['page' => $page, 'question' => $q, 'answer' => $a, 'sort' => $i++]);
    }
}

function seed_testimonials(): void
{
    $rows = [
        ['It finally feels like one thing instead of five.', 'Adaeze O.', 'Retail, 4 locations'],
        ['Our email stopped landing in spam the week they touched it.', 'Marcus R.', 'Logistics'],
        ['Nobody had told us the domain was in the last agency\'s name.', 'Priya S.', 'Clinic group'],
    ];
    $i = 0;
    foreach ($rows as [$q, $a, $r]) {
        db_insert('testimonials', ['quote' => $q, 'author' => $a, 'role' => $r, 'sort' => $i++, 'is_active' => 1]);
    }
}

function seed_portfolio(): void
{
    $rows = [
        ['Four shops, one set of opening hours', 'Retail group', 'Retail',
         'Hours were wrong on Maps for two of four locations, so customers arrived at closed doors and left reviews about it.',
         "The group had grown to four shops and each one had been added to Google by whoever was managing it at the time. Two were wrong. One still listed a phone number that had been disconnected.\n\nWe rebuilt the site around a single source of hours, pushed those to every listing, and set holiday closures so they can be scheduled months ahead instead of remembered on the day. Reviews now route to the branch they are about.",
         "Websites\nSEO & listings\nHosting\nSupport", "PHP\nMySQL\nGoogle Business Profile", '', 'public', 1],
        ['Appointments off the phone', 'Private clinic', 'Healthcare',
         'Reception spent mornings taking bookings by phone and afternoons chasing people who forgot them.',
         "The clinic had three practitioners, a paper diary and a phone that rang all morning. Roughly one appointment in six was a no-show.\n\nWe built online booking against the practitioners' real availability, added confirmations and a reminder the day before, and put practitioner profiles and hours on the site so patients stopped calling to ask. No-shows fell and the phone stopped being a full-time job.",
         "Web & mobile apps\nWebsites\nBusiness email\nSupport", "PHP\nMySQL\nSMTP", '', 'public', 1],
        ['Quotes while on a roof', 'Roofing contractor', 'Trades',
         'Enquiries went to an address nobody checked until evening, by which point the job had gone elsewhere.',
         "A two-van roofing business with a site built in 2016 and a contact form pointing at a personal Hotmail address.\n\nWe moved them onto their own domain with proper business email, put a short quote form on every page that lands straight on a phone, and built out service-area pages so they show up for the towns they actually cover. Before-and-after galleries do the selling.",
         "Websites\nDomains\nBusiness email\nSEO & listings", "PHP\nMySQL", '', 'public', 0],
    ];
    $i = 0;
    foreach ($rows as [$title, $client, $sector, $summary, $body, $services, $tech, $url, $vis, $feat]) {
        db_insert('portfolio', [
            'slug'          => slugify($title),
            'title'         => $title,
            'client_name'   => $client,
            'sector'        => $sector,
            'summary'       => $summary,
            'body'          => $body,
            'services_used' => $services,
            'tech'          => $tech,
            'live_url'      => $url,
            'cover_image'   => '',
            'completed_on'  => date('Y-m-d', strtotime('-' . (2 + $i * 3) . ' months')),
            'visibility'    => $vis,
            'is_featured'   => $feat,
            'sort'          => $i++,
            'created_at'    => now(),
        ]);
    }
}

function seed_products(): void
{
    $rows = [
        ['Restaurant & bookings', 'Hospitality', 1290, 990,
         'A complete restaurant site with menus you can edit, table booking, and a gallery that stays fast.',
         "Built for a single restaurant or a small group. Menus are editable from the admin area in about two minutes, and bookings arrive by email and sit in a diary you can see.\n\nEverything is plain PHP and MySQL, so it runs on ordinary cPanel hosting with no monthly platform fee.",
         "Editable menus with sections and prices\nTable booking with confirmation emails\nGallery with automatic image resizing\nOpening hours and holiday closures\nGoogle Maps and directions\nAdmin area, no HTML needed",
         "PHP 8\nMySQL\nNo framework", 9],
        ['Clinic & appointments', 'Healthcare', 1690, null,
         'Practitioner profiles, online appointment requests, reminders, and a patient-friendly information site.',
         "Designed for clinics with two to ten practitioners. Patients request an appointment against real availability; the clinic confirms with one click and reminders go out automatically the day before.\n\nEnquiries are stored before mail is attempted, so nothing is lost if the mail server has a bad day.",
         "Practitioner profiles and hours\nAppointment requests with confirmation\nAutomatic day-before reminders\nMulti-location support\nSecure enquiry storage\nAdmin area with export",
         "PHP 8\nMySQL\nNo framework", 12],
        ['Trades & quote capture', 'Trades', 890, 690,
         'A fast, phone-first site for a trade business, built around getting quote requests in while you work.',
         "The whole site is arranged around one job: turning a visitor into a quote request before they leave. Forms are on every page, they are short, and they land on a phone.\n\nService-area pages are generated from a list you edit, so you can cover twenty towns without writing twenty pages.",
         "Quote form on every page\nService-area pages from a list\nBefore-and-after galleries\nReviews and accreditations\nClick-to-call throughout\nAdmin area, no HTML needed",
         "PHP 8\nMySQL\nNo framework", 8],
        ['Shop & checkout', 'Retail', 2400, null,
         'A straightforward online shop — products, cart, checkout and orders — without a monthly platform fee.',
         "For businesses selling tens of products rather than thousands. Products, variants, stock and orders are all in the admin area, and checkout connects to a payment provider of your choice.\n\nIt is deliberately simple. If you need marketplaces, subscriptions and warehouses, this is not it, and we will tell you so.",
         "Products with variants and stock\nCart and checkout\nOrder management and status emails\nDiscount codes\nDelivery zones and rates\nAdmin area with sales reporting",
         "PHP 8\nMySQL\nNo framework", 14],
    ];
    $i = 0;
    foreach ($rows as [$title, $cat, $price, $sale, $summary, $body, $features, $tech, $pages]) {
        db_insert('products', [
            'slug'           => slugify($title),
            'title'          => $title,
            'category'       => $cat,
            'summary'        => $summary,
            'body'           => $body,
            'features'       => $features,
            'tech'           => $tech,
            'price'          => $price,
            'sale_price'     => $sale,
            'demo_url'       => '',
            'cover_image'    => '',
            'pages'          => $pages,
            'includes_setup' => 1,
            'is_active'      => 1,
            'is_featured'    => $i === 0 ? 1 : 0,
            'sort'           => $i++,
            'created_at'     => now(),
        ]);
    }
}
