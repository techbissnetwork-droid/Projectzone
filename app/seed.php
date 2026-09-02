<?php
/**
 * Initial content. Everything here is the copy currently on the site, moved
 * into the database so it can be edited from the admin area instead of by
 * hand in HTML. Run once by the installer.
 */

function seed_all(): void
{
    seed_content();
    seed_services();
    seed_industries();
    seed_steps();
    seed_tiers();
    seed_addons();
    seed_compare();
    seed_faqs();
    seed_testimonials();
    seed_stats();
    seed_rules();
    seed_team();
}

function seed_row(string $table, array $data): void
{
    db_insert($table, $data);
}

function seed_content(): void
{
    $items = [
        // group, key, label, value, type
        ['global', 'site.name', 'Company name', 'TECHBISS', 'text'],
        ['global', 'site.mark', 'Logo letter', 'T', 'text'],
        ['global', 'site.tagline', 'Footer description', 'One partner for everything a business needs online — domain, hosting, SSL, website, apps, email, branding, SEO and support.', 'textarea'],
        ['global', 'site.email', 'Public email address', 'hello@techbiss.com', 'text'],
        ['global', 'site.support_email', 'Support email address', 'support@techbiss.com', 'text'],
        ['global', 'site.phone', 'Phone number', '+1 000 000 0000', 'text'],
        ['global', 'site.phone_link', 'Phone number (link format)', '+10000000000', 'text'],
        ['global', 'site.hours', 'Opening hours', 'Mon–Fri, 9:00–18:00.', 'text'],
        ['global', 'site.footer_note', 'Footer right-hand text', 'ALWAYS ON', 'text'],
        ['global', 'social.x', 'X / Twitter URL', '#', 'text'],
        ['global', 'social.linkedin', 'LinkedIn URL', '#', 'text'],
        ['global', 'social.instagram', 'Instagram URL', '#', 'text'],
        ['global', 'social.whatsapp', 'WhatsApp URL', '#', 'text'],
        ['global', 'nav.cta', 'Header button label', 'Talk to us', 'text'],

        ['home', 'home.meta.title', 'Browser tab title', 'TECHBISS — One partner for everything your business needs online', 'text'],
        ['home', 'home.meta.desc', 'Search description', 'TECHBISS builds websites and apps, and takes offline businesses online — domain, hosting, SSL, business email, SEO and support, all handled by one partner.', 'textarea'],
        ['home', 'home.hero.eyebrow', 'Hero eyebrow', 'Always on — one partner, every system', 'text'],
        ['home', 'home.hero.line1', 'Hero headline, first line', 'One partner.', 'text'],
        ['home', 'home.hero.line2', 'Hero headline, second line (gradient)', 'Everything, live.', 'text'],
        ['home', 'home.hero.lead', 'Hero paragraph', 'We build websites and apps, and take offline businesses online — domain, hosting, SSL, email, SEO and support, set up as one system and kept running. Not a handoff. Not a slideshow.', 'textarea'],
        ['home', 'home.hero.cta1', 'Hero primary button', "See what we run →", 'text'],
        ['home', 'home.hero.cta2', 'Hero secondary button', 'Talk to us', 'text'],
        ['home', 'home.hero.chips', 'Floating status chips (one per line)', "Website — kept online\nSSL & domain — auto-renewed\nBackups — done nightly", 'textarea'],
        ['home', 'home.marquee', 'Scrolling word strip (one per line)', "Websites\nApps\nDomains\nHosting\nSSL\nBusiness email\nSEO\nBranding\nAutomation\nSupport", 'textarea'],
        ['home', 'home.services.heading', 'Services section heading', 'Ten systems, running together.', 'text'],
        ['home', 'home.services.sub', 'Services section subheading', 'Everything TECHBISS keeps live for a business, in one grid instead of six vendors and six invoices.', 'textarea'],
        ['home', 'home.offline.heading', 'Offline→online section heading', 'Take the business online. Properly.', 'text'],
        ['home', 'home.offline.sub', 'Offline→online subheading', "Most businesses we meet already work — they're just invisible to anyone searching.", 'textarea'],
        ['home', 'home.offline.service', 'Which service to feature here (anchor)', 'offline', 'text'],
        ['home', 'home.process.heading', 'Process section heading', 'How a project goes live.', 'text'],
        ['home', 'home.process.sub', 'Process section subheading', 'Four steps, the same order every time — nothing skipped to hit a date.', 'textarea'],
        ['home', 'home.industries.heading', 'Industries section heading', 'Businesses TECHBISS runs for.', 'text'],
        ['home', 'home.industries.sub', 'Industries section subheading', 'Different day-to-day, same one-partner setup underneath.', 'textarea'],
        ['home', 'home.statement', 'Big statement line', "No sales script. {Tell us what you run} and what's missing — we'll show you the live system, not a mockup.", 'textarea'],
        ['home', 'home.quotes.heading', 'Testimonials heading', 'What owners say afterwards.', 'text'],
        ['home', 'home.quotes.sub', 'Testimonials subheading', 'The same note comes back: it finally feels like one thing instead of five.', 'textarea'],
        ['home', 'home.cta.heading', 'Closing call-to-action heading', 'Ready to go live?', 'text'],
        ['home', 'home.cta.body', 'Closing call-to-action text', 'One conversation, no script. Describe the stack you have today — or tell us you have none at all.', 'textarea'],
        ['home', 'home.cta.button', 'Closing call-to-action button', 'Start now →', 'text'],

        ['services', 'services.meta.title', 'Browser tab title', 'Services — TECHBISS', 'text'],
        ['services', 'services.meta.desc', 'Search description', 'Websites, apps, domain, hosting, SSL, business email, SEO, branding, automation, e-commerce and support — all handled by one partner.', 'textarea'],
        ['services', 'services.hero.eyebrow', 'Hero eyebrow', 'Ten systems · one partner', 'text'],
        ['services', 'services.hero.heading', 'Hero heading', 'Everything a business needs online, built and kept running.', 'text'],
        ['services', 'services.hero.lead', 'Hero paragraph', "You can take one piece or the whole stack. Either way it's set up under your own accounts, documented, and watched after launch — not handed over as a zip file and a goodbye.", 'textarea'],
        ['services', 'services.statement', 'Big statement line', 'Take one service or take all ten. {Either way it\'s one partner} — and every account is registered in your name, not ours.', 'textarea'],
        ['services', 'services.faq.heading', 'FAQ heading', 'Questions we get asked first.', 'text'],
        ['services', 'services.faq.sub', 'FAQ subheading', "If yours isn't here, ask it directly — we answer within a business day.", 'textarea'],
        ['services', 'services.cta.heading', 'Closing heading', 'Not sure which of these you need?', 'text'],
        ['services', 'services.cta.body', 'Closing text', "Tell us what the business does and what's frustrating right now. We'll tell you what's actually worth doing first.", 'textarea'],
        ['services', 'services.cta.button', 'Closing button', 'Get a straight answer →', 'text'],

        ['industries', 'industries.meta.title', 'Browser tab title', 'Industries — TECHBISS', 'text'],
        ['industries', 'industries.meta.desc', 'Search description', 'Retail, education, hospitality, legal, healthcare, real estate, trades and community organisations — taken online and kept running by one partner.', 'textarea'],
        ['industries', 'industries.hero.eyebrow', 'Hero eyebrow', 'Same stack · different day-to-day', 'text'],
        ['industries', 'industries.hero.heading', 'Hero heading', 'Businesses TECHBISS runs for.', 'text'],
        ['industries', 'industries.hero.lead', 'Hero paragraph', "The underlying setup is the same everywhere — domain, hosting, SSL, email, site, listings, support. What changes is what the website has to do on the day, and that's what we design around.", 'textarea'],
        ['industries', 'industries.strip.heading', 'Card strip heading', 'Pick the one closest to yours.', 'text'],
        ['industries', 'industries.strip.sub', 'Card strip subheading', 'Not listed? The pattern still applies — tell us what your day looks like.', 'textarea'],
        ['industries', 'industries.detail.heading', 'Detail grid heading', 'What each one actually needs.', 'text'],
        ['industries', 'industries.detail.sub', 'Detail grid subheading', "The stack is the same. What sits on top of it isn't.", 'textarea'],
        ['industries', 'industries.statement', 'Big statement line', 'Whatever the sector, the failure is the same: {six suppliers, nobody responsible.} We take the whole thing.', 'textarea'],
        ['industries', 'industries.cta.heading', 'Closing heading', "Your industry isn't on the list?", 'text'],
        ['industries', 'industries.cta.body', 'Closing text', "It rarely changes the answer. Describe a normal working day and we'll map it to a setup.", 'textarea'],
        ['industries', 'industries.cta.button', 'Closing button', 'Talk to us →', 'text'],

        ['pricing', 'pricing.meta.title', 'Browser tab title', 'Pricing — TECHBISS', 'text'],
        ['pricing', 'pricing.meta.desc', 'Search description', 'Fixed-price website, app and full web setup packages, plus optional monthly care plans. Third-party costs passed through at cost, in your name.', 'textarea'],
        ['pricing', 'pricing.hero.eyebrow', 'Hero eyebrow', 'Fixed scope · fixed price', 'text'],
        ['pricing', 'pricing.hero.heading', 'Hero heading', 'Prices you can see before you call.', 'text'],
        ['pricing', 'pricing.hero.lead', 'Hero paragraph', 'Every package below is a fixed quote for a written scope. Third-party costs — domain registration, hosting, email licences — are billed to you at cost, in your own name, with nothing marked up.', 'textarea'],
        ['pricing', 'pricing.build.heading', 'Build packages heading', 'Build packages.', 'text'],
        ['pricing', 'pricing.build.sub', 'Build packages subheading', 'One-off cost to design, build and launch. Care plan is optional and separate.', 'textarea'],
        ['pricing', 'pricing.care.heading', 'Care plans heading', 'Care plans, after launch.', 'text'],
        ['pricing', 'pricing.care.sub', 'Care plans subheading', 'Optional, month to month, cancel whenever. This is what keeps things from lapsing.', 'textarea'],
        ['pricing', 'pricing.compare.heading', 'Comparison table heading', "What's in each package.", 'text'],
        ['pricing', 'pricing.compare.sub', 'Comparison table subheading', 'Side by side, so nothing is hiding in a footnote.', 'textarea'],
        ['pricing', 'pricing.compare.col1', 'Comparison column 1 name', 'Starter', 'text'],
        ['pricing', 'pricing.compare.col2', 'Comparison column 2 name', 'Business', 'text'],
        ['pricing', 'pricing.compare.col3', 'Comparison column 3 name', 'Custom', 'text'],
        ['pricing', 'pricing.addons.heading', 'Add-ons heading', 'Add-ons, priced plainly.', 'text'],
        ['pricing', 'pricing.addons.sub', 'Add-ons subheading', 'Bolt any of these onto any package. Quoted before work starts, never after.', 'textarea'],
        ['pricing', 'pricing.statement', 'Big statement line', "Third-party costs are passed through {at cost, in your name.} We don't mark up a domain and call it a service.", 'textarea'],
        ['pricing', 'pricing.faq.heading', 'FAQ heading', 'Pricing questions.', 'text'],
        ['pricing', 'pricing.faq.sub', 'FAQ subheading', 'The awkward ones, answered up front.', 'textarea'],
        ['pricing', 'pricing.cta.heading', 'Closing heading', 'Want an exact number?', 'text'],
        ['pricing', 'pricing.cta.body', 'Closing text', 'Describe the business in a few lines. You\'ll get a written scope and a fixed price — no meeting required first.', 'textarea'],
        ['pricing', 'pricing.cta.button', 'Closing button', 'Request a quote →', 'text'],

        ['about', 'about.meta.title', 'Browser tab title', 'About — TECHBISS', 'text'],
        ['about', 'about.meta.desc', 'Search description', 'Why TECHBISS owns the whole stack: how we work, who does the work, and the rules we hold to on every project.', 'textarea'],
        ['about', 'about.hero.eyebrow', 'Hero eyebrow', 'One partner · no handoffs', 'text'],
        ['about', 'about.hero.heading', 'Hero heading', 'We took over the jobs nobody wanted to own.', 'text'],
        ['about', 'about.hero.lead', 'Hero paragraph', 'A pattern we kept seeing: a business with a designer, a developer, a hosting reseller and an IT friend — and nobody responsible when the site went down on a Sunday.', 'textarea'],
        ['about', 'about.why.kicker', 'Story kicker', 'Why we exist', 'text'],
        ['about', 'about.why.heading', 'Story heading', 'Six suppliers, one broken website, no answers.', 'text'],
        ['about', 'about.why.body', 'Story text (blank line between paragraphs)', "Our first rescue had a domain registered in a former employee's personal email, hosting paid by an agency that had shut down, and an SSL certificate that expired on a Friday night. Nobody had done anything wrong — the responsibility had just been split until it disappeared.\n\nSo we do the whole stack — domain, hosting, SSL, email, site, apps, listings, support. One partner, one contact, one place where the buck stops. Less glamorous than \"creative agency\", and the one that actually keeps small businesses online.", 'textarea'],
        ['about', 'about.why.panel_title', 'Story panel title', 'WHAT WE OWN', 'text'],
        ['about', 'about.why.panel', 'Story panel rows ("label | value" per line)', "design | us\nbuild | us\ndomain & dns | us\nhosting & ssl | us\nemail | us\nsupport | us\nthe accounts | yours", 'textarea'],
        ['about', 'about.rules.heading', 'Working rules heading', 'How we work, in six rules.', 'text'],
        ['about', 'about.rules.sub', 'Working rules subheading', "These aren't values on a wall — they're the things we'd want from a supplier.", 'textarea'],
        ['about', 'about.team.heading', 'Team heading', 'The people who do the work.', 'text'],
        ['about', 'about.team.sub', 'Team subheading', 'A small team on purpose — you talk to whoever is building the thing.', 'textarea'],
        ['about', 'about.statement', 'Big statement line', "We'd rather be the boring partner that {never lets your domain expire} than the exciting one you can't reach.", 'textarea'],
        ['about', 'about.cta.heading', 'Closing heading', "Want to know if we're a fit?", 'text'],
        ['about', 'about.cta.body', 'Closing text', "One conversation. If we're the wrong people for the job, we'll say so and point you somewhere better.", 'textarea'],
        ['about', 'about.cta.button', 'Closing button', 'Talk to us →', 'text'],

        ['contact', 'contact.meta.title', 'Browser tab title', 'Contact — TECHBISS', 'text'],
        ['contact', 'contact.meta.desc', 'Search description', "Tell us what your business runs on today and what's missing. Written scope and a fixed price, answered within one business day.", 'textarea'],
        ['contact', 'contact.hero.eyebrow', 'Hero eyebrow', 'One business day · always a human', 'text'],
        ['contact', 'contact.hero.heading', 'Hero heading', "Tell us what you run, and what's missing.", 'text'],
        ['contact', 'contact.hero.lead', 'Hero paragraph', "No sales script and no discovery-call ritual. Describe the business in a few lines — you'll get a straight answer about what's worth doing, and a fixed price if you want one.", 'textarea'],
        ['contact', 'contact.form.services', 'Enquiry form: service options (one per line)', "Not sure yet — advise me\nA website, from scratch\nTake my offline business online\nDomain, hosting, SSL & email setup\nA web or mobile app\nAn online store\nSEO & Google Business Profile\nBranding & logo\nAutomation & AI\nRescue an existing site / domain\nMaintenance & support only", 'textarea'],
        ['contact', 'contact.form.budgets', 'Enquiry form: budget options (one per line)', "Not decided\nUnder \$700\n\$700 – \$2,000\n\$2,000 – \$5,000\n\$5,000 – \$15,000\nOver \$15,000", 'textarea'],
        ['contact', 'contact.form.success', 'Message shown after sending', "Thanks — that's with us. We reply within one business day.", 'textarea'],
        ['contact', 'contact.next', 'What happens next (one step per line)', "We read it and reply with questions or an answer.\nIf it's a fit, you get a written scope and fixed price.\nYou approve it before anyone starts building.", 'textarea'],
        ['contact', 'contact.bring', 'What to bring', 'Existing domain name, old hosting logins, current website address, logo files, and the email address your domain renewal notices go to.', 'textarea'],
        ['contact', 'contact.statement', 'Big statement line', "You don't need to know what you need. {Describe a normal working day} — we'll work out the rest.", 'textarea'],
        ['contact', 'contact.faq.heading', 'FAQ heading', 'Before you write.', 'text'],
        ['contact', 'contact.faq.sub', 'FAQ subheading', 'The five things people ask in the first email.', 'textarea'],
    ];

    $sort = 0;
    foreach ($items as [$group, $key, $label, $value, $type]) {
        seed_row('content', [
            'ckey' => $key, 'cvalue' => $value, 'clabel' => $label,
            'cgroup' => $group, 'ctype' => $type, 'sort' => $sort++,
        ]);
    }
}

function seed_services(): void
{
    $s = [
        ['01', 'websites', 'Website Design & Development', 'Designed, built, hosted and kept online — not handed off and forgotten. Fast, mobile-first, and yours to own.', 'a',
         '01 — Website design & development', 'A website that loads fast, works on a phone, and earns its keep.',
         "Designed around what a visitor actually needs to do — call, book, order, or walk in. Hand-built and fast, not a template with fourteen plugins bolted on. The code stays yours.",
         "Custom design from your brand, or a brand we build for you\nMobile-first, tested on real devices, not just a desktop preview\nContent management you can edit without calling us\nSpeed, accessibility and on-page SEO handled at build time",
         'BUILD OUTPUT', "performance | 98 / 100\naccessibility | 100 / 100\nseo | 100 / 100\nfirst paint | 0.8s\nmobile | pass\nstatus | deployed"],

        ['02', 'apps', 'Web & Mobile Apps', 'Booking, ordering, portals, dashboards.', 'c',
         '02 — Web & mobile apps', 'The thing your team currently does in a spreadsheet, as an app.',
         "Booking, ordering, portals, dashboards, inventory, delivery tracking. We build the smallest version that removes the real pain first, then grow it from what your staff tell us.",
         "Booking, ordering and appointment systems\nCustomer portals and member logins\nInternal dashboards, stock and job tracking\nInstallable web apps that work on any phone — no app store wait",
         'BOOKINGS · TODAY', "09:30 — walk-in | confirmed\n11:00 — consult | confirmed\n13:15 — repeat | paid\n15:45 — new | reminder sent\nno-shows | down 41%"],

        ['03', 'offline', 'Offline → Online', 'A shop with no web presence, fully online.', 'c',
         '03 — Offline → online', "You've run the business for years. It just isn't findable.",
         "Where most of our clients start. You have customers, a location and a reputation — and nothing online but a Facebook page someone made in 2016. We take the whole thing online in one go, in the right order.",
         "Domain name chosen, registered and in your name\nHosting set up, tuned and monitored\nSSL installed so the padlock shows from day one\nBusiness email on your own domain, on every phone\nGoogle Business Profile claimed and verified\nWebsite live, indexed and ready for customers",
         'GO-LIVE CHECKLIST', "domain | registered\ndns | pointed\nhosting | provisioned\nssl | issued · auto-renew\nemail | mx · spf · dkim ok\nwebsite | deployed\nbackups | nightly\nstatus | live"],

        ['04', 'infrastructure', 'Domain, Hosting & SSL', 'Registered, pointed, secured and renewed on time — DNS, certificates and uptime handled end to end.', 'b',
         '04 — Domain, hosting & SSL', 'The plumbing. Set up once, correctly, then watched.',
         "Nobody notices this layer until it fails at the worst moment. We register, configure, secure and monitor all of it — with every account in your business's name.",
         "Domain search, registration and transfer (.com, .co, country domains)\nDNS configured properly — A, CNAME, MX, SPF, DKIM, DMARC\nManaged hosting sized to your traffic, with a CDN in front\nSSL certificates installed and set to renew automatically\nNightly backups, kept off-server, tested by restoring them",
         'INFRASTRUCTURE · LIVE', "domain expiry | 312 days · auto\nssl expiry | 67 days · auto\nuptime (90d) | 99.98%\nlast backup | 02:00 · verified\ncdn | enabled\nalerts | none"],

        ['05', 'email', 'Business Email', 'you@yourbusiness.com, on every device.', 'c',
         '05 — Business email', 'you@yourbusiness.com — and it lands in the inbox, not spam.',
         "A free webmail address on a quote costs you deals you never hear about. We set up email on your own domain, configure the records that keep it out of spam, and migrate the old mail across.",
         "Google Workspace or Microsoft 365 — set up and configured\nMailboxes, aliases, shared inboxes and groups for departments\nSPF, DKIM and DMARC configured so mail is trusted\nOld mail and contacts migrated across, nothing dropped",
         'MAIL DELIVERABILITY', "mx records | ok\nspf | pass\ndkim | pass\ndmarc | p=quarantine\nblacklists | clear\ninbox placement | 99%"],

        ['06', 'seo', 'SEO & Google Business', 'Found by people already searching.', 'c',
         '06 — SEO & Google Business', 'Found by the people already searching for what you sell.',
         "No tricks, no vague retainer for \"optimisation\". We fix the technical foundations, complete your local listings, and write pages that answer what people actually type — then report the numbers.",
         "Technical SEO — speed, structure, sitemaps, schema markup\nKeyword research based on real local search demand\nGoogle Business Profile claimed, verified and kept current\nPage content and metadata written for humans first\nMonthly report: rankings, traffic, calls and enquiries",
         'SEARCH · 90 DAYS', "impressions | +218%\nclicks | +164%\nmap views | +302%\ncalls from listing | +87\ndirection requests | +143"],

        ['07', 'branding', 'Branding & Identity', 'Logo, colours, type and templates — one identity that reads the same on a sign, a receipt and a screen.', 'b',
         '07 — Branding & identity', 'One identity that reads the same on a sign, a receipt and a screen.',
         "If your logo looks different on the shopfront, the invoice and the website, customers notice — quietly, and not in your favour. We build a small, usable identity system you can apply yourself.",
         "Logo design, with every file format you'll ever be asked for\nColour palette and typography that work in print and on screen\nBusiness cards, letterheads, invoices and signage artwork\nSocial profile art and post templates your team can edit",
         'BRAND KIT · DELIVERED', "logo | svg · png · pdf · eps\npalette | hex · rgb · cmyk\ntype | licensed · webfont\nstationery | print-ready\ntemplates | editable"],

        ['08', 'automation', 'Automation & AI', 'Enquiries routed, invoices sent, replies drafted — the repetitive parts handled quietly in the background.', 'b',
         '08 — Automation & AI', 'The repetitive parts, handled quietly in the background.',
         "Every business has jobs someone does by hand daily because \"that's how it's done\" — enquiries retyped into a spreadsheet, the same five replies, invoices chased manually. We automate those, with a human still in control.",
         "Enquiries routed automatically to the right person\nAppointment and payment reminders sent on schedule\nAI assistants that answer common questions from your own content\nYour tools connected — website, email, sheets, CRM, accounting",
         'AUTOMATIONS · THIS MONTH', "enquiries routed | 412\nreminders sent | 1,180\ninvoices issued | 96\nreplies drafted | 640\nstaff hours saved | ~58"],

        ['09', 'ecommerce', 'E-commerce & Payments', 'Catalogue, checkout, payouts.', 'c',
         '09 — E-commerce & payments', 'Sell online without a checkout that loses people at the last step.',
         "Catalogue, cart, checkout, delivery and payouts — set up so you add a product in two minutes and get paid without chasing anybody, using the payment methods your customers actually use.",
         "Product catalogue, variants, stock levels and categories\nCheckout tuned to reduce drop-off at the payment step\nCard, bank transfer, mobile money and pay-on-delivery options\nDelivery zones, rates and pickup handled properly",
         'STORE · LAST 30 DAYS', "orders | 318\ncheckout completion | 81%\nfailed payments | 0.4%\navg. order | +12%\npayouts | on schedule"],

        ['10', 'maintenance', 'Maintenance & Support', 'Renewals watched before they lapse.', 'c',
         '10 — Maintenance & support', "After launch is when most agencies go quiet. That's where we start.",
         "A site that isn't updated gets hacked, expires, or slowly stops matching the business. The care plan keeps everything patched, backed up and renewed — with one person to message.",
         "Software, plugin and server updates applied and tested\nNightly off-site backups, with restores actually tested\nDomain, hosting, SSL and licence renewals tracked and actioned\nContent edits and small changes included each month\nOne contact, one response window, no ticket maze",
         'CARE PLAN · STATUS', "updates | current\nbackups | 7/7 verified\nmalware scan | clean\nrenewals due | none < 60d\nopen tickets | 0\nuptime | 99.99%"],
    ];

    $sort = 0;
    foreach ($s as [$code, $anchor, $title, $summary, $size, $kicker, $heading, $body, $bullets, $panelTitle, $panel]) {
        seed_row('services', [
            'sort' => $sort++, 'code' => $code, 'anchor' => $anchor, 'title' => $title,
            'summary' => $summary, 'size' => $size, 'kicker' => $kicker, 'heading' => $heading,
            'body' => $body, 'bullets' => $bullets, 'panel_title' => $panelTitle, 'panel' => $panel,
            'is_active' => 1,
        ]);
    }
}

function seed_industries(): void
{
    $i = [
        ['01', 'retail', 'Retail & Shops', 'Catalogue, online orders, local search.', 1,
         '01 — Retail & shops', 'People search for what you stock before they leave the house.',
         "The shop is fine — the problem is that someone two streets away can't tell you exist, what you carry, or whether you're open.",
         "Catalogue with photos, prices and stock status\nOnline ordering, delivery zones and in-store pickup\nGoogle Business Profile for local searches\nWhatsApp ordering wired into the site"],
        ['02', 'education', 'Education', 'Admissions, portals, notices.', 2,
         '02 — Schools & education', 'Parents decide from the website before they ever visit.',
         "Admissions, fees, results, calendars and notices — the things currently living in a printed sheet or a WhatsApp broadcast.",
         "Admissions enquiries and online application forms\nParent and student portals with logins\nCalendar, notices and newsletters in one click\nFee information and payment links"],
        ['03', 'hospitality', 'Hospitality', 'Menus, tables, direct bookings.', 3,
         '03 — Restaurants & hospitality', 'Own your bookings instead of renting them from a platform.',
         "Aggregators take a cut of every table and own the customer. We build the direct channel so platforms become a supplement.",
         "Menus you update yourself, no PDF uploads\nDirect reservations with confirmations and reminders\nCommission-free ordering for pickup and delivery\nReview prompts that lift your local ranking honestly"],
        ['04', 'legal', 'Legal & Finance', 'Credible site, secure email.', 4,
         '04 — Legal & professional', 'Credibility, in the first five seconds.',
         "Clients compare three websites and pick the one that looks like it takes itself seriously — plus proper email on your own domain.",
         "A restrained, professional site that loads instantly\nPractice areas, team profiles and clear enquiry paths\nEmail with SPF, DKIM and DMARC configured\nConsultation booking with calendar sync"],
        ['05', 'healthcare', 'Healthcare', 'Appointments, hours, directions.', 5,
         '05 — Clinics & healthcare', 'Fewer calls asking the same four questions.',
         "Hours, location, services, which insurance you take. Put those where patients look and the front desk gets its day back.",
         "Online booking with SMS and email reminders\nServices, practitioners and hours, always current\nDirections, parking and accessibility information\nPatient forms filled in before the visit"],
        ['06', 'realestate', 'Real Estate', 'Listings, enquiries, viewings.', 6,
         '06 — Real estate', 'Listings that stay current without retyping them anywhere.',
         "Properties added once, shown properly, and taken down the moment they go — with every listing on its own shareable page.",
         "Listings with galleries, maps and floor plans\nSearch by area, price, type and beds\nViewing requests routed to the right agent\nSold and let listings archived automatically"],
        ['07', 'trades', 'Trades & Services', 'Quotes, jobs, callbacks.', 7,
         '07 — Trades & local services', 'The job goes to whoever answers first and looks real.',
         "Customers search, glance at two or three results, and call one. We make sure you're in that list and the enquiry reaches your phone.",
         "A site that loads on bad mobile data\nClick-to-call and WhatsApp buttons that always work\nQuote requests that reach you as a notification\nService areas and past work with real photos"],
        ['08', 'ngo', 'NGOs & Churches', 'Events, giving, updates.', 8,
         '08 — NGOs, churches & community', 'Tell the story, take the donation, publish the next event.',
         "Built to be run by volunteers on a budget — everything that changes weekly is editable without touching code.",
         "Events, programmes and notices, self-published\nOnline giving and recurring donations\nVolunteer and membership sign-up forms\nLow-cost hosting that fits a community budget"],
    ];

    $sort = 0;
    foreach ($i as [$code, $anchor, $name, $blurb, $grad, $kicker, $heading, $summary, $bullets]) {
        seed_row('industries', [
            'sort' => $sort++, 'code' => $code, 'anchor' => $anchor, 'name' => $name,
            'blurb' => $blurb, 'gradient' => $grad, 'kicker' => $kicker, 'heading' => $heading,
            'summary' => $summary, 'bullets' => $bullets, 'is_active' => 1,
        ]);
    }
}

function seed_steps(): void
{
    $steps = [
        ['01', 'Talk', "You describe what the business runs on today and what's missing. We come back with a written scope, a fixed price and a date — before anyone commits."],
        ['02', 'Build', "Domain, hosting, SSL, email, the site and any apps you need — set up as one system, not six. You review a working preview link, not a slide deck."],
        ['03', 'Launch', "Live, checked on real devices, and handed over with every login documented in your name. We walk your team through editing it themselves."],
        ['04', 'Watch', "Renewals, SSL, hosting and backups kept ahead of their dates, so nothing lapses quietly. One person to message when something needs changing."],
    ];
    $sort = 0;
    foreach ($steps as [$code, $title, $body]) {
        seed_row('steps', ['sort' => $sort++, 'code' => $code, 'title' => $title, 'body' => $body, 'is_active' => 1]);
    }
}

function seed_tiers(): void
{
    $t = [
        ['build', 'Starter', '$690', 'ONE-OFF · 2 WEEKS', 'For a business that has nothing online yet and needs to exist properly, fast.',
         "Up to 5 pages, custom designed\nDomain registered in your name\nHosting set up for the first year\nSSL certificate installed, auto-renewing\n2 business email accounts on your domain\nGoogle Business Profile claimed and completed\nContact form, map and WhatsApp button\nMobile-first, speed and on-page SEO done\n1-hour handover and training",
         '', 'Start with Starter', 0],
        ['build', 'Business', '$1,890', 'ONE-OFF · 3–4 WEEKS', 'The full offline-to-online setup: the whole stack, live, with your team trained on it.',
         "Everything in Starter\nUp to 15 pages, plus a blog or news section\nContent management you can edit yourself\n10 business email accounts, migrated and configured\nSPF, DKIM and DMARC set up for deliverability\nBrand kit — logo refresh, palette, type, templates\nBooking or enquiry system wired in\nAnalytics and Search Console in your account\nLocal SEO and directory listings made consistent\n3 months of care plan included free",
         'Most chosen', 'Start with Business →', 1],
        ['build', 'Custom', 'From $4,500', 'QUOTED · 6+ WEEKS', 'Apps, stores, portals and multi-branch setups — scoped and priced properly.',
         "Everything in Business\nWeb or mobile app built to your workflow\nE-commerce with payments and delivery\nCustomer or staff portals with logins\nAutomation and AI assistants\nIntegrations — CRM, accounting, POS, sheets\nMulti-branch or multi-language\nPriority support and a named contact",
         '', 'Get a quote', 0],

        ['care', 'Watch', '$39', 'PER MONTH', 'The safety net. Nothing expires, nothing goes down unnoticed.',
         "Uptime monitoring with alerts\nNightly off-site backups\nSecurity and malware scanning\nSoftware and server updates\nDomain, SSL and hosting renewals tracked\nMonthly status report",
         '', 'Choose Watch', 0],
        ['care', 'Run', '$99', 'PER MONTH', 'Everything watched, plus a person who makes the changes you ask for.',
         "Everything in Watch\n2 hours of content edits and changes monthly\nEmail and account support for your team\nPerformance tuning as traffic grows\nSEO health checks and fixes\nSame-business-day response window",
         'Best value', 'Choose Run →', 1],
        ['care', 'Grow', '$249', 'PER MONTH', 'For businesses actively pushing for more traffic, orders and bookings.',
         "Everything in Run\n6 hours of development and content monthly\nOngoing SEO work and content plan\nConversion tracking and improvements\nNew pages, campaigns and landing pages\nMonthly review call with your numbers\nPriority queue, named contact",
         '', 'Choose Grow', 0],
    ];
    $sort = 0;
    foreach ($t as [$grp, $name, $price, $period, $desc, $features, $tag, $cta, $featured]) {
        seed_row('tiers', [
            'sort' => $sort++, 'grp' => $grp, 'name' => $name, 'price' => $price, 'period' => $period,
            'description' => $desc, 'features' => $features, 'tag' => $tag, 'cta_label' => $cta,
            'is_featured' => $featured, 'is_active' => 1,
        ]);
    }
}

function seed_addons(): void
{
    $a = [
        ['A1', 'E-commerce store', 'From $1,200 — catalogue, checkout, payment methods, delivery zones, order notifications and a sales dashboard.', 'a'],
        ['A2', 'Extra email accounts', '$25 setup each, plus licence at cost.', 'c'],
        ['A3', 'Logo & brand kit', 'From $350, all files included.', 'c'],
        ['A4', 'Booking or portal system', 'From $900 — logins, calendars, reminders and role-based access for staff and customers.', 'b'],
        ['A5', 'Copywriting', '$90 per page, written for search — including the pages that answer what people actually type.', 'b'],
        ['A6', 'Automation & AI assistant', 'From $600 — enquiry routing, reminders, invoice generation, or an assistant trained on your own content.', 'b'],
        ['A7', 'Rescue & recovery', "From $250 — recovering a lapsed domain, a hacked site, or hosting held by a developer who's gone quiet.", 'b'],
        ['A8', 'Photo & video day', 'From $400 for on-site shooting — real photos of your premises, team and work.', 'b'],
    ];
    $sort = 0;
    foreach ($a as [$code, $title, $desc, $size]) {
        seed_row('addons', ['sort' => $sort++, 'code' => $code, 'title' => $title, 'description' => $desc, 'size' => $size, 'is_active' => 1]);
    }
}

function seed_compare(): void
{
    $rows = [
        ['Pages', 'Up to 5', 'Up to 15 + blog', 'Unlimited'],
        ['Domain registration', 'Yes, in your name', 'Yes, in your name', 'Yes, in your name'],
        ['Hosting setup', 'Shared, managed', 'Managed + CDN', 'Scaled to load'],
        ['Business email accounts', '2', '10, migrated', 'Unlimited'],
        ['Edit it yourself (CMS)', 'Text edits only', 'Full CMS', 'Full CMS'],
        ['Google Business Profile', 'Claimed', 'Claimed + optimised', 'Multi-location'],
        ['Branding & logo', 'Use existing', 'Brand kit', 'Full identity'],
        ['E-commerce', '—', 'Add-on', 'Included'],
        ['App development', '—', '—', 'Included'],
        ['Training & handover', '1 hour', '2 hours, recorded', 'Full team, recorded'],
        ['Care plan included', 'Optional', '3 months free', '6 months free'],
        ['Typical timeline', '2 weeks', '3–4 weeks', '6+ weeks'],
    ];
    $sort = 0;
    foreach ($rows as [$label, $c1, $c2, $c3]) {
        seed_row('compare_rows', ['sort' => $sort++, 'label' => $label, 'col1' => $c1, 'col2' => $c2, 'col3' => $c3, 'is_active' => 1]);
    }
}

function seed_faqs(): void
{
    $f = [
        ['services', 'We have nothing online at all. Is that a problem?', "It's the situation we work with most. Starting from zero is easier than untangling three half-finished setups — there's nothing to migrate and nothing built wrong. We handle the domain, hosting, email, site and listings in one sequence and explain each step as we go."],
        ['services', 'Who owns the domain and hosting accounts?', "You do. Every account is registered in your business's name with your billing details, and you get the logins. We manage them for you, but you can walk away with everything at any time. We don't hold a client's domain as leverage — ever."],
        ['services', 'Can you take over a website someone else built?', 'Usually yes. We audit what\'s there, tell you honestly whether it\'s worth keeping or rebuilding, and give you the cost either way. If the existing developer has gone quiet, we can recover domains and hosting in most cases — bring us whatever records and emails you still have.'],
        ['services', 'How long does it take to go live?', 'A focused business site is typically 2–4 weeks from approved content. A full offline-to-online setup with email, listings and training runs about 4 weeks. Apps and stores depend on scope — you get a written date before work starts, and we tell you early if anything threatens it.'],
        ['services', 'Can we edit the website ourselves?', "Yes. Everything we build comes with an editor for the parts you'll actually want to change — text, images, prices, hours, products, posts. We record a short handover walkthrough for your team, so it doesn't depend on one person remembering."],

        ['pricing', "What isn't included in these prices?", 'Third-party running costs: domain registration (typically $12–20/year), hosting ($5–40/month depending on the plan), email licences (about $6/user/month for Workspace or 365), and any paid fonts, stock photos or plugins you choose. We set all of these up in your own accounts and you pay the provider directly — we take no cut.'],
        ['pricing', 'How does payment work?', "50% to start, 50% on go-live for build packages. Care plans are billed monthly in advance and can be cancelled any time with 30 days' notice. Custom projects over $4,500 are split into milestone payments tied to delivery stages written into the scope."],
        ['pricing', 'What if the project needs more than the package covers?', 'We tell you before starting, not halfway through. The written scope lists exactly what\'s included; anything outside it is quoted as an add-on and you approve it before any work happens. There are no surprise invoices at the end.'],
        ['pricing', 'Do you work with small budgets?', "Often. If Starter is out of reach, tell us the number you have and what matters most. Sometimes the honest answer is that a properly configured Google Business Profile and a single-page site is all you need this year — and we'll say so rather than sell you a package."],
        ['pricing', 'What happens if we want to leave?', 'You take everything. Domain, hosting, email, code, content, analytics — all already in your name. We hand over documentation and, if you like, brief the next developer. No exit fee, no data held back.'],

        ['contact', "I don't have a domain, a website or anything. Where do I even start?", "Right here. Send the enquiry with just your business name and what you do. We'll check what domain names are available, tell you what a full setup would cost, and walk you through it in plain language. Most of our clients start with nothing."],
        ['contact', "My old developer has disappeared and I can't get into anything.", "More common than you'd think, and usually recoverable. Send whatever you have — the website address, any old invoices, the email address that receives renewal notices — and we'll work out who holds what. Domain recovery is often possible even years later."],
        ['contact', 'How quickly can you start?', 'Scoping starts the day you reply to our questions. Build slots are usually 1–2 weeks out. If something is genuinely urgent — an expired certificate, a hacked site, a domain about to lapse — say so in the subject line and we\'ll deal with it the same day.'],
        ['contact', 'Do you work with clients in other countries?', 'Yes. Everything we do is remote, and time zones have never been the hard part. We handle local domain extensions, local payment gateways and local search listings wherever you are.'],
        ['contact', 'Will you sign an NDA?', 'Happily, before you share anything sensitive. Send yours or ask for ours.'],
    ];
    $sort = 0;
    foreach ($f as [$page, $qn, $an]) {
        seed_row('faqs', ['sort' => $sort++, 'page' => $page, 'question' => $qn, 'answer' => $an, 'is_active' => 1]);
    }
}

function seed_testimonials(): void
{
    $t = [
        ['We had a shop and a phone number, nothing else. TECHBISS did the domain, the site, the email, the Google listing — all of it — and now people find us before they walk in.', 'Adaeze O.', 'Owner, home & living store', 'AO'],
        ["Our old site went down and nobody could tell us why, because three different companies were involved. Now there's one number to call and it just doesn't go down.", 'Kwame M.', 'Director, logistics firm', 'KM'],
        ['The booking app they built replaced a WhatsApp thread and a paper diary. Staff picked it up in a day and no-shows dropped straight away.', 'Sara R.', 'Manager, dental clinic', 'SR'],
    ];
    $sort = 0;
    foreach ($t as [$quote, $name, $role, $av]) {
        seed_row('testimonials', ['sort' => $sort++, 'quote' => $quote, 'name' => $name, 'role' => $role, 'avatar' => $av, 'is_active' => 1]);
    }
}

function seed_stats(): void
{
    $s = [
        ['180', '+', 'Websites, apps and setups delivered end to end.'],
        ['99.9', '%', 'Uptime target on hosting we manage and monitor.'],
        ['24', 'h', 'Response window on support requests, business days.'],
        ['0', '', 'Domains lost to a missed renewal since we started.'],
    ];
    $sort = 0;
    foreach ($s as [$value, $suffix, $label]) {
        seed_row('stats', ['sort' => $sort++, 'value' => $value, 'suffix' => $suffix, 'label' => $label, 'is_active' => 1]);
    }
}

function seed_rules(): void
{
    $r = [
        ['01', 'Your accounts, your name', "Every domain, host and mailbox is registered to your business with your billing details. You can leave with everything, any day. Nothing is held as leverage.", 'a'],
        ['02', 'Fixed price, written scope', 'Agreed before work starts.', 'c'],
        ['03', 'Plain language', 'No jargon used to inflate a bill.', 'c'],
        ['04', "We say no to work that won't help", "If an app won't fix your problem, we'll tell you — even when it's the bigger invoice.", 'b'],
        ['05', "Launch isn't the end", 'Renewals watched, forever.', 'c'],
        ['06', 'One person to message', 'Not a ticket queue.', 'c'],
    ];
    $sort = 0;
    foreach ($r as [$code, $title, $body, $size]) {
        seed_row('rules', ['sort' => $sort++, 'code' => $code, 'title' => $title, 'body' => $body, 'size' => $size, 'is_active' => 1]);
    }
}

function seed_team(): void
{
    $t = [
        ['T', 'Strategy & Scoping', 'FIRST CONVERSATION', 'Works out what the business needs before anyone opens a design tool, and writes the scope you sign.'],
        ['D', 'Design & Brand', 'LOOK & FEEL', 'Turns the scope into an identity and an interface — mobile first, no stock template.'],
        ['E', 'Engineering', 'BUILD & APPS', 'Builds the site, apps and integrations, and keeps the code clean enough for someone else to pick up.'],
        ['O', 'Infrastructure & Support', 'ALWAYS ON', 'Owns domains, DNS, hosting, SSL, email and backups — and answers when something needs fixing.'],
    ];
    $sort = 0;
    foreach ($t as [$initial, $name, $role, $body]) {
        seed_row('team', ['sort' => $sort++, 'initial' => $initial, 'name' => $name, 'role' => $role, 'body' => $body, 'is_active' => 1]);
    }
}
