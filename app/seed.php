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
        ['global', 'site.tagline', 'Footer description', 'We build your website and look after everything that keeps it running — your web address, hosting, security, email and support. One company instead of five.', 'textarea'],
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
        ['brand', 'brand.logo', 'Logo image', '', 'image'],
        ['brand', 'brand.logo_height', 'Logo height in the header (pixels)', '30', 'text'],
        ['brand', 'brand.favicon', 'Favicon (the little icon in the browser tab)', '', 'image'],
        ['brand', 'brand.social_image', 'Sharing image (shown when your link is posted)', '', 'image'],
        ['brand', 'seo.title_suffix', 'Text added after every page title', '', 'text'],
        ['brand', 'seo.default_description', 'Description used when a page has none', '', 'textarea'],
        ['brand', 'seo.twitter', 'X / Twitter handle (without the @)', '', 'text'],
        ['brand', 'seo.google_verification', 'Google Search Console verification code', '', 'text'],
        ['brand', 'seo.noindex', 'Hide the whole site from Google (1 = hide, 0 = show)', '0', 'text'],
        ['brand', 'biz.street', 'Street address', '', 'text'],
        ['brand', 'biz.city', 'Town or city', '', 'text'],
        ['brand', 'biz.region', 'County, state or region', '', 'text'],
        ['brand', 'biz.postcode', 'Postcode or ZIP', '', 'text'],
        ['brand', 'biz.country', 'Country', '', 'text'],
        ['global', 'cron.token', 'Cron token (keep this secret)', '', 'text'],
        ['global', 'reminders.enabled', 'Send renewal reminders (1 = yes, 0 = no)', '1', 'text'],
        ['global', 'reminders.days', 'Remind this many days before (comma separated)', '45,14,3', 'text'],
        ['global', 'reminders.notify_client', 'Also email the client (1 = yes, 0 = no)', '0', 'text'],
        ['global', 'reminders.last_run', 'When cron last ran', '', 'text'],
        ['global', 'marquee.words', 'Scrolling word strip (one per line)', "Websites\nApps\nDomains\nHosting\nSSL\nEmail\nSEO\nBranding\nAutomation\nSupport", 'textarea'],

        ['home', 'home.meta.title', 'Browser tab title', 'TECHBISS — Websites for small businesses, looked after for you', 'text'],
        ['home', 'home.meta.desc', 'Search description', 'We build websites and apps for small businesses, then look after them: your web address, hosting, security, email and support, all in one place.', 'textarea'],
        ['home', 'home.hero.eyebrow', 'Hero eyebrow', 'Website, hosting, email — all handled', 'text'],
        ['home', 'home.hero.line1', 'Hero line 1 (silver)', 'One company.', 'text'],
        ['home', 'home.hero.line2', 'Hero line 2 (green)', 'Everything sorted.', 'text'],
        ['home', 'home.hero.lead', 'Hero paragraph', 'We build your website, put your business on Google, set up your email, and keep it all running afterwards. You get one number to call and one bill to pay.', 'textarea'],
        ['home', 'home.hero.cta1', 'Hero primary button', 'See what we do →', 'text'],
        ['home', 'home.hero.cta2', 'Hero secondary button', 'Talk to us', 'text'],
        ['home', 'home.stack.heading', 'Stack section heading', 'Everything your business needs online.', 'text'],
        ['home', 'home.stack.sub', 'Stack section subheading', 'Most businesses end up with a different company for each of these. We do all four, so there is only ever one person to ask.', 'textarea'],
        ['home', 'home.proof.heading', 'Proof heading', 'We are still here after launch.', 'text'],
        ['home', 'home.stat1.value', 'Stat 1 number', '99.98%', 'text'],
        ['home', 'home.stat1.label', 'Stat 1 label', 'Sites staying online', 'text'],
        ['home', 'home.stat2.value', 'Stat 2 number', '140+', 'text'],
        ['home', 'home.stat2.label', 'Stat 2 label', 'Businesses we look after', 'text'],
        ['home', 'home.stat3.value', 'Stat 3 number', '100%', 'text'],
        ['home', 'home.stat3.label', 'Stat 3 label', 'Renewals never missed', 'text'],
        ['home', 'home.stat4.value', 'Stat 4 number', '<4h', 'text'],
        ['home', 'home.stat4.label', 'Stat 4 label', 'Average reply time', 'text'],
        ['home', 'home.process.heading', 'Process heading', 'How it works.', 'text'],
        ['home', 'home.process.sub', 'Process subheading', 'Four steps. We do them in the same order every time, and we do not skip any to hit a date.', 'textarea'],
        ['home', 'home.quotes.heading', 'Testimonials heading', 'What our customers say.', 'text'],
        ['home', 'home.portfolio.heading', 'Portfolio teaser heading', 'Some of our work.', 'text'],
        ['home', 'home.portfolio.sub', 'Portfolio teaser subheading', 'Real businesses we built for and still look after today.', 'textarea'],
        ['home', 'home.market.heading', 'Marketplace teaser heading', 'Or buy one ready-made.', 'text'],
        ['home', 'home.market.sub', 'Marketplace teaser subheading', 'Websites we have already built. Buy one today and we can have it live on your own web address within a week.', 'textarea'],
        ['home', 'home.cta.heading', 'Closing heading', 'Ready to get started?', 'text'],
        ['home', 'home.cta.body', 'Closing text', 'Tell us what your business does and what you have online today. If the answer is nothing at all, that is fine — most of our customers start there.', 'textarea'],

        ['services', 'services.meta.title', 'Browser tab title', 'What we do — TECHBISS', 'text'],
        ['services', 'services.meta.desc', 'Search description', 'Websites, apps, web addresses, hosting, security, business email, Google listings, branding and support — all from one company.', 'textarea'],
        ['services', 'services.hero.eyebrow', 'Hero eyebrow', 'Ten things we do', 'text'],
        ['services', 'services.hero.line1', 'Hero line 1 (silver)', 'Everything you need', 'text'],
        ['services', 'services.hero.line2', 'Hero line 2 (green)', 'to be online.', 'text'],
        ['services', 'services.hero.lead', 'Hero paragraph', 'Pick one thing or let us handle everything. Either way it all goes in your name, we write down how it is set up, and we keep an eye on it after it goes live.', 'textarea'],
        ['services', 'services.list.heading', 'List heading', 'What each one means.', 'text'],
        ['services', 'services.list.sub', 'List subheading', 'You can buy any of these on their own. Most people take the middle group together — web address, hosting, security and email — because that is the part nobody else joins up for you.', 'textarea'],
        ['services', 'services.statement', 'Big statement (use {braces} to highlight)', 'Take one thing or take all ten. {Either way it is one company to call} — and everything is registered in your name, not ours.', 'textarea'],
        ['services', 'services.cta.heading', 'Closing heading', 'Not sure what you need?', 'text'],
        ['services', 'services.cta.body', 'Closing text', 'Tell us what your business does and what is annoying you right now. We will tell you what is worth doing first — and what is not worth paying for.', 'textarea'],

        ['industries', 'industries.meta.title', 'Browser tab title', 'Who we work with — TECHBISS', 'text'],
        ['industries', 'industries.meta.desc', 'Search description', 'Shops, schools, restaurants, clinics, law firms, estate agents, tradespeople and local groups. We put them online and keep them there.', 'textarea'],
        ['industries', 'industries.hero.eyebrow', 'Hero eyebrow', 'Different businesses, same setup', 'text'],
        ['industries', 'industries.hero.line1', 'Hero line 1 (silver)', 'Who we work', 'text'],
        ['industries', 'industries.hero.line2', 'Hero line 2 (green)', 'with.', 'text'],
        ['industries', 'industries.hero.lead', 'Hero paragraph', 'What sits underneath is the same for everyone. What changes is the job the website has to do each day — take bookings, show a menu, bring in quotes — and that is what we build around.', 'textarea'],
        ['industries', 'industries.list.heading', 'List heading', 'Find the one closest to yours.', 'text'],
        ['industries', 'industries.list.sub', 'List subheading', 'Not on the list? It rarely changes anything. Tell us what a normal day looks like and we will work out what you need.', 'textarea'],
        ['industries', 'industries.statement', 'Big statement (use {braces} to highlight)', 'Whatever the business, the problem is the same: {lots of suppliers, nobody in charge.} We take the lot.', 'textarea'],
        ['industries', 'industries.cta.heading', 'Closing heading', 'Do not see your type of business?', 'text'],
        ['industries', 'industries.cta.body', 'Closing text', 'It rarely matters. Tell us what a normal day looks like and we will tell you what would help.', 'textarea'],

        ['pricing', 'pricing.meta.title', 'Browser tab title', 'Pricing — TECHBISS', 'text'],
        ['pricing', 'pricing.meta.desc', 'Search description', 'Fixed prices for websites, apps and full setup, plus optional monthly care. Things we buy for you are passed on at cost, in your name.', 'textarea'],
        ['pricing', 'pricing.hero.eyebrow', 'Hero eyebrow', 'Fixed prices, agreed up front', 'text'],
        ['pricing', 'pricing.hero.line1', 'Hero line 1 (silver)', 'Prices you can see', 'text'],
        ['pricing', 'pricing.hero.line2', 'Hero line 2 (green)', 'before you call.', 'text'],
        ['pricing', 'pricing.hero.lead', 'Hero paragraph', 'Every price below is fixed against a written list of what you get. Things we have to buy for you — your web address, hosting, email accounts — are charged at what they cost us, in your name, with nothing added on top.', 'textarea'],
        ['pricing', 'pricing.build.heading', 'Build packages heading', 'Building your site.', 'text'],
        ['pricing', 'pricing.build.sub', 'Build packages subheading', 'A one-off price to design, build and launch. Looking after it afterwards is separate and optional.', 'textarea'],
        ['pricing', 'pricing.care.heading', 'Care plans heading', 'Looking after it.', 'text'],
        ['pricing', 'pricing.care.sub', 'Care plans subheading', 'Optional, paid monthly, cancel any time. This is what stops things quietly expiring.', 'textarea'],
        ['pricing', 'pricing.addons.heading', 'Add-ons heading', 'Extras, priced plainly.', 'text'],
        ['pricing', 'pricing.addons.sub', 'Add-ons subheading', 'Add any of these to any package. We agree the price before we start, never after.', 'textarea'],
        ['pricing', 'pricing.statement', 'Big statement (use {braces} to highlight)', 'What we buy for you is charged {at cost, in your name.} We do not add a markup to your web address and call it a service.', 'textarea'],
        ['pricing', 'pricing.cta.heading', 'Closing heading', 'Want a price for your job?', 'text'],
        ['pricing', 'pricing.cta.body', 'Closing text', 'Tell us about your business in a few lines. We will send back a list of what you get and a fixed price. No meeting needed first.', 'textarea'],

        ['about', 'about.meta.title', 'Browser tab title', 'About — TECHBISS', 'text'],
        ['about', 'about.meta.desc', 'Search description', 'Who we are, how we work, and the things we will not do.', 'textarea'],
        ['about', 'about.hero.eyebrow', 'Hero eyebrow', 'Who you would be dealing with', 'text'],
        ['about', 'about.hero.line1', 'Hero line 1 (silver)', 'One team.', 'text'],
        ['about', 'about.hero.line2', 'Hero line 2 (green)', 'Everything handled.', 'text'],
        ['about', 'about.hero.lead', 'Hero paragraph', 'Most businesses we meet already have suppliers. What they do not have is anyone who answers. A web designer who went quiet, a hosting bill nobody understands, and email that keeps going to spam. We take all of it on and run it as one thing.', 'textarea'],
        ['about', 'about.story.heading', 'Story heading', 'Why we do it this way.', 'text'],
        ['about', 'about.story.body', 'Story text (blank line between paragraphs)', "Every business we take on arrives with the same story. The site was built by somebody who has moved on. The domain is registered to an agency that no longer answers. Hosting renews on a card nobody recognises. Email works, mostly, except when it does not.\n\nNone of that is one big failure. It is six small ones, spread across six suppliers, with nobody responsible for the whole. So we made the whole thing our job: register the domain in your name, provision the hosting, issue the certificate, set the mail records, build the site, list the business, then watch all of it afterwards.\n\nThat is the entire idea. It is not clever. It is just nobody else's job until it is somebody's job.", 'textarea'],
        ['about', 'about.rules.heading', 'Principles heading', 'How we work.', 'text'],
        ['about', 'about.cta.heading', 'Closing heading', 'Start with a conversation.', 'text'],
        ['about', 'about.cta.body', 'Closing text', 'No script and no deck. Tell us what the business does and what is not working.', 'textarea'],

        ['portfolio', 'portfolio.meta.title', 'Browser tab title', 'Our work — TECHBISS', 'text'],
        ['portfolio', 'portfolio.meta.desc', 'Search description', 'Websites and apps we have built for real businesses, and still look after today.', 'textarea'],
        ['portfolio', 'portfolio.hero.eyebrow', 'Hero eyebrow', 'Finished and live', 'text'],
        ['portfolio', 'portfolio.hero.line1', 'Hero line 1 (silver)', 'Work we have', 'text'],
        ['portfolio', 'portfolio.hero.line2', 'Hero line 2 (green)', 'done.', 'text'],
        ['portfolio', 'portfolio.hero.lead', 'Hero paragraph', 'Everything here is a real site that is live right now, and we still look after most of them. Where a customer asked us not to name them, we have described the work without the name.', 'textarea'],
        ['portfolio', 'portfolio.empty', 'Text shown when nothing is published yet', 'We are still writing these up. Ask us and we will send you examples in the meantime.', 'textarea'],

        ['marketplace', 'market.meta.title', 'Browser tab title', 'Ready-made websites — TECHBISS', 'text'],
        ['marketplace', 'market.meta.desc', 'Search description', 'Websites we have already built, ready to buy today. We can set one up on your own web address and hosting for you.', 'textarea'],
        ['marketplace', 'market.hero.eyebrow', 'Hero eyebrow', 'Already built · yours this week', 'text'],
        ['marketplace', 'market.hero.line1', 'Hero line 1 (silver)', 'Websites already', 'text'],
        ['marketplace', 'market.hero.line2', 'Hero line 2 (green)', 'built and ready.', 'text'],
        ['marketplace', 'market.hero.lead', 'Hero paragraph', 'Finished websites at a fixed price. Take one as it is, or we will put it on your own web address with hosting, security and email set up, and hand you the keys.', 'textarea'],
        ['marketplace', 'market.empty', 'Text shown when nothing is listed yet', 'Nothing is listed right now. Tell us what you are after and we will say if we have something close.', 'textarea'],
        ['marketplace', 'market.setup.price', 'Price of the optional setup service', '450', 'text'],
        ['marketplace', 'market.setup.blurb', 'What the setup service includes', 'We get your web address sorted, set up the hosting, turn on the padlock that makes your site secure, create your business email so it does not land in spam, install the site, put your own words and pictures in, and hand you every login.', 'textarea'],

        ['contact', 'contact.meta.title', 'Browser tab title', 'Contact — TECHBISS', 'text'],
        ['contact', 'contact.meta.desc', 'Search description', 'Tell us what your business does and what is not working. We reply within one working day.', 'textarea'],
        ['contact', 'contact.hero.eyebrow', 'Hero eyebrow', 'A straight answer, quickly', 'text'],
        ['contact', 'contact.hero.line1', 'Hero line 1 (silver)', 'Tell us what', 'text'],
        ['contact', 'contact.hero.line2', 'Hero line 2 (green)', 'you do.', 'text'],
        ['contact', 'contact.hero.lead', 'Hero paragraph', 'Tell us about your business and what is annoying you at the moment. You will get an honest answer about what is worth doing first, and what you can leave for later.', 'textarea'],
        ['contact', 'contact.form.heading', 'Form heading', 'Send it over.', 'text'],
        ['contact', 'contact.form.note', 'Small print under the form', 'We reply within one working day. We use your details to answer you and nothing else — no mailing list, no passing them on.', 'textarea'],
        ['contact', 'contact.thanks', 'Message shown after sending', 'Thanks, we have got it. We will reply within one working day.', 'textarea'],
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
        ['Websites', 'Designed and built for you', '\u{25c8}',
         'A website built for the business you actually run, not picked off a shelf. Quick to load on a phone, easy for Google to read, and simple enough that you can change the words yourself afterwards.',
         "Five to fifty pages, laid out around what your customers look for\nYou can edit the words, prices and pictures yourself — no code\nWritten with you, so it says what you actually do\nChecked on real phones, not just a big screen"],

        ['Web and mobile apps', 'When a website is not enough', '\u{25d0}',
         'Booking systems, online ordering, customer logins, staff dashboards. The sort of thing you need once a spreadsheet and a phone are not coping any more.',
         "Booking and appointments, with reminders that actually go out\nCustomer logins — their orders, their documents, their history\nDashboards for whoever runs the place\nBuilt on your own hosting, so you are never locked in"],

        ['Web addresses', 'Yours, in your name', '\u{25c7}',
         'Your web address — the yourbusiness.com part. The most common mess we clean up is finding it registered to an old designer who has vanished. Yours goes in your name, on your account, with you as the owner.',
         "Buying it, moving it, and renewing it — always in your name\nSet up and written down, so anyone could take it over\nLocked so it cannot be moved without you\nWe do the work without owning the thing"],

        ['Hosting', 'Where your site lives', '\u{25a3}',
         'Hosting is the space your website sits in. We set it up, make it quick, and keep an eye on it. Copies of everything are taken every night whether anyone remembers or not.',
         "Set up, moved over, and tuned for your site\nA fresh copy saved every night, kept for a month\nChecked every minute to make sure it is still up\nA private copy to try changes on before they go live"],

        ['Security and the padlock', 'So nothing quietly breaks', '\u{26e8}',
         'The padlock in the address bar means your site is secure — and browsers now warn people away from sites without one. We put it in place, renew it automatically, and watch it, because an expired one takes a business offline on a Sunday afternoon.',
         "The padlock set up and renewed before it ever runs out\nWatched, so we hear about a problem before you do\nSoftware kept up to date and logins locked down\nIf something nasty ever gets in, we clean it up"],

        ['Business email', 'Mail that arrives', '\u{2709}',
         'you@yourbusiness.com instead of a Gmail address. Just as important, we set it up properly behind the scenes so your quotes and invoices land in the inbox rather than the spam folder.',
         "Email addresses on your own name\nSet up so it does not get treated as spam\nMoved across from Gmail or an old provider, losing nothing\nShared inboxes like info@ for the whole team"],

        ['Getting found on Google', 'And on Maps', '\u{25ce}',
         'Two jobs. Making the site something Google can read properly, and making sure your business shows up on Maps with the right opening hours and phone number.',
         "The technical groundwork so Google can read every page\nYour Google listing set up and verified\nHours, address and phone kept right everywhere they appear\nA short monthly update in plain English, not a 40-page report"],

        ['Logo and branding', 'The look, everywhere', '\u{2726}',
         'Your logo, colours and typefaces, plus the files you need for everything else — signs, social media, invoices, the van.',
         "A logo in every format you will ever be asked for\nColours and type written down, so everything matches\nReady-made templates for social media and print\nA one-page guide, so other suppliers stay on brand"],

        ['Automating the boring bits', 'Fewer things done by hand', '\u{27f2}',
         'The jobs you repeat every week — chasing enquiries, sending invoices, reminding people about appointments — handed to software so nobody is doing them at 9pm.',
         "Enquiries going straight to the right inbox or phone\nInvoices and receipts created for you\nReminders for appointments and payments\nJoining up the tools you already pay for"],

        ['Support', 'One number, same people', '\u{260e}',
         'When you call, you get the people who built it. No ticket queue in another country, and no explaining your setup from scratch every time.',
         "First reply within four working hours\nAnything that takes your site down is fixed the same day\nSmall changes done for you if you are on a care plan\nYour setup written down, so it is not stuck in one head"],
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
        ['Shops and retail', '\u{25ec}', 'Stock that changes, opening hours that matter, and customers checking their phone while standing outside your door.',
         "Opening hours on Google that are always right\nProduct or menu pages you can update in a minute\nClick and collect, or a full online shop\nReviews pointed somewhere useful"],
        ['Schools and training', '\u{25eb}', 'Parents, students and staff all want different things from the same website, usually on the same evening.',
         "Term dates, newsletters and closures\nApplication and enquiry forms\nA staff area for notices and documents\nEmail on the school name, not personal accounts"],
        ['Restaurants and hotels', '\u{25c9}', 'Bookings, menus and good photographs. If the phone rings less after launch, that was the point.',
         "Table or room booking that keeps itself in step\nMenus you can change in two minutes\nPhoto galleries that do not slow the site down\nThe same details everywhere people look you up"],
        ['Solicitors and accountants', '\u{25c6}', 'Trust comes first. The website has to look like a firm that charges what you charge.',
         "Clear pages for each thing you do, and for each person\nEnquiry forms that handle sensitive details properly\nA private area for client documents\nEmail set up so client mail never lands in spam"],
        ['Clinics and healthcare', '\u{271a}', 'Appointments, the people who work there, and clear information for someone who is worried.',
         "Appointment requests, with reminders sent out\nProfiles and hours for each practitioner\nEnquiries handled carefully and kept private\nSeveral locations kept in step with each other"],
        ['Estate agents', '\u{25a2}', 'Listings that change every week, and buyers who leave the moment a page is slow.',
         "Property listings with photos and filters\nEnquiries sent straight to the right agent\nFeeds to the portals you already use\nArea pages that show up in local searches"],
        ['Builders and tradespeople', '\u{2699}', 'You are on a roof, not at a desk. The website has to sell while you work.',
         "A short quote form that lands on your phone\nPages for each area you cover, so locals find you\nBefore and after photos of real jobs\nReviews and trade memberships up front"],
        ['Charities and local groups', '\u{2661}', 'Small budgets, lots of volunteers, and a real need for things not to break when the person who set them up moves on.',
         "Events, notices and newsletters\nDonations or membership sign-ups\nShared inboxes, so handover is easy\nEverything written down, not stuck in one head"],
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
        ['Starter', '1,400', 'One-off', 'For a business with nothing online yet, or a site that has aged badly.',
         "Up to 5 pages\nWeb address and hosting set up\nPadlock security and business email\nYour Google listing sorted\nWe look after it free for 30 days", 0],
        ['Business', '3,900', 'One-off', 'For a busy business that wants the whole thing set up properly, once.',
         "Up to 15 pages, all editable by you\nEverything set up in your name\nEmail set up so it does not go to spam\nGroundwork so Google can find you\nWe look after it free for 90 days", 1],
        ['Custom', '', 'To scope', 'For anything with an app, a customer login, a shop or a big move in it.',
         "Apps, customer logins, online shops\nJoining up the tools you already use\nMoving you off your current supplier\nA priority line for support\nOngoing care included", 0],
    ];
    $care = [
        ['Essential', '90', 'per month', 'Keeps everything running and nothing expiring.',
         "We check your site is up, every minute\nA copy saved every night\nRenewals handled before they run out\nSecurity updates applied\nEmail support", 0],
        ['Standard', '180', 'per month', 'Everything in Essential, plus we make the small changes for you.',
         "Everything in Essential\nTwo hours of changes a month\nA short monthly update on how you are doing in Google\nWe answer you first\nA private copy to try changes on", 1],
        ['Managed', '390', 'per month', 'For businesses where the website is how customers find you.',
         "Everything in Standard\nSix hours of changes a month\nA proper catch-up every three months\nSame-day answers\nA direct line to the team", 0],
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
        ['An extra page', '120', 'Designed and written to match the rest of your site.'],
        ['An online shop', '900', 'Products, basket, checkout and card payments connected.'],
        ['Online booking', '750', 'Customers book themselves in, and get a reminder before they come.'],
        ['Logo and branding', '600', 'A logo in every format you need, plus your colours and fonts written down.'],
        ['Writing your words', '90', 'Per page, written with you rather than guessed at.'],
        ['Moving an existing site', '350', 'Bringing your site, email and web address across without losing anything.'],
        ['An extra email address', '4', 'Per address, per month, on your own business name.'],
        ['A photography day', '550', 'We come to you for a day, and hand back edited photos ready to use.'],
    ];
    $i = 0;
    foreach ($rows as [$n, $p, $b]) {
        db_insert('addons', ['name' => $n, 'price' => $p, 'blurb' => $b, 'sort' => $i++]);
    }
}

function seed_faqs(): void
{
    $rows = [
        ['services', 'Do I have to take everything?',
         'No. Plenty of people come to us for one thing — usually email that keeps going to spam, or a web address they cannot get control of. We are happy to fix just that and leave the rest alone.'],
        ['services', 'Who owns the web address and hosting?',
         'You do. Everything is bought in your business name, on accounts you control, and you get every login in writing when we hand over. We do the work; we never own the thing. If you ever leave us, nothing has to move.'],
        ['services', 'We already have a website. Do we need a new one?',
         'Often not. If your site works and just needs to be faster, safer, or easier to find, we will say so and quote for that instead. We would rather do the smaller job properly than sell you a rebuild you did not need.'],
        ['services', 'Can we change the website ourselves?',
         'Yes. Words, pictures, prices and opening hours are all editable from a simple admin screen — no code, nothing to install. We show you how before we hand over, and if you would rather we did it, that is what the care plan covers.'],
        ['services', 'How long does it take?',
         'A small site is usually two to three weeks once we have your words and photos. A bigger one is four to six. Honestly, the wait is almost always for content from your side — the building rarely holds things up.'],
        ['pricing', 'Is the price really fixed?',
         'Yes, against a written list of what you are getting. If you ask for something outside that list, we tell you the price before we do it, never after. The only thing that moves a price is you changing your mind, and you always see that number first.'],
        ['pricing', 'What are the extra costs you mention?',
         'Things we have to buy on your behalf: your web address, your hosting, and any paid email accounts. We charge you exactly what they cost us. For a small business it usually comes to somewhere between $80 and $300 a year.'],
        ['pricing', 'Do I have to pay you monthly?',
         'No. The care plan is optional and you can stop it any time. Without one, the site is yours to run and we are here when you need us at an hourly rate. With one, we watch the renewals, take the backups and keep things updated so nothing quietly expires.'],
        ['pricing', 'How do payments work?',
         'Half up front, half when the site goes live. Care plans are paid monthly in advance and you can cancel whenever you like. No long contracts and no cancellation fee.'],
        ['pricing', 'What if I want to leave?',
         'You take everything with you. Your web address, hosting and email are already in your name, and we hand over the site files and every login. There is nothing for us to unlock and nothing to argue about.'],
    ];
    $i = 0;
    foreach ($rows as [$page, $q, $a]) {
        db_insert('faqs', ['page' => $page, 'question' => $q, 'answer' => $a, 'sort' => $i++]);
    }
}

function seed_testimonials(): void
{
    $rows = [
        ['It finally feels like one thing instead of five.', 'Adaeze O.', 'Runs four shops'],
        ['Our emails stopped going to spam the week they touched it.', 'Marcus R.', 'Delivery company'],
        ['Nobody had told us our web address was in the old designer\'s name.', 'Priya S.', 'Runs three clinics'],
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
