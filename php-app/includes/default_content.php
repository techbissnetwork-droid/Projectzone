<?php
/**
 * The site's built-in Services / Industries / Case Studies / Pricing /
 * Team / Values content — used to seed the content_* tables on install
 * (install/migrations/010_content_sections.php, superseded by
 * install/migrations/014_content_tables.php, which reads this same file
 * as its ultimate fallback).
 */
return [
    'services' => [
        ['icon' => 'monitor', 'name' => 'Website Design & Development', 'blurb' => 'A site built around your business, not squeezed into a generic template.', 'bullets' => ['Custom design & copy', 'Fast, mobile-friendly pages', 'Built to grow as you do']],
        ['icon' => 'code', 'name' => 'App Development', 'blurb' => 'iOS and Android apps built from your idea, not a boilerplate.', 'bullets' => ['iOS & Android, one build', 'Real designs before we code', 'Built to pass App Store review']],
        ['icon' => 'globe', 'name' => 'Domain, Hosting & Email', 'blurb' => 'The unglamorous stuff, set up right the first time and never left to lapse.', 'bullets' => ['Domain registration & DNS', 'Fast hosting with SSL included', 'Business email on your domain']],
        ['icon' => 'rocket', 'name' => 'App Store & Play Store Publishing', 'blurb' => 'We handle listings, screenshots and the entire review process.', 'bullets' => ['Store listing & screenshots', 'Submission & review handled', 'Updates after you launch']],
        ['icon' => 'chart', 'name' => 'SEO & Search Ranking', 'blurb' => 'So being online actually means being found.', 'bullets' => ['On-page & technical SEO', 'Google Maps & local search', 'Plain-language ranking reports']],
        ['icon' => 'cart', 'name' => 'Ready-Made Themes & Templates', 'blurb' => 'Buy a theme, brand it as your own, and launch in days.', 'bullets' => ['Fully brandable, no lock-in', 'Your logo, colors & content', 'Same support as a custom build']],
    ],
    'solutions' => [
        ['icon' => 'cart', 'name' => 'Shops & Local Retail', 'out' => ['An online store that matches your storefront', 'Orders and inventory in one place', 'Local SEO so nearby customers find you']],
        ['icon' => 'heart', 'name' => 'Restaurants & Cafés', 'out' => ['Menu, hours & online ordering', 'Table booking built in', 'Your Google & Maps listing done right']],
        ['icon' => 'gear', 'name' => 'Home & Local Services', 'out' => ['Booking & quote requests online', 'Service-area SEO that actually ranks', 'Reviews and contact, front and center']],
        ['icon' => 'spark', 'name' => 'Creators & Personal Brands', 'out' => ['A site or app that looks like you', 'Portfolio, shop or booking in one place', 'App store publishing handled']],
        ['icon' => 'flag', 'name' => 'Nonprofits & Community Groups', 'out' => ['Donation & event pages', 'Volunteer sign-ups made simple', 'Discounted plans available']],
    ],
    'case_studies' => [
        ['sector' => 'Bakery', 'icon' => 'cart', 'client' => 'Maple & Co. Bakery', 'stat' => '+64%', 'statLabel' => 'online orders in month one', 'quote' => 'We went from a Facebook page to a real website with ordering in under two weeks.', 'body' => 'Maple & Co. was taking orders through Facebook comments and DMs. We built them a website with online ordering, connected a custom domain and business email, and got them ranking for "bakery near me" in their own neighborhood.'],
        ['sector' => 'Fitness', 'icon' => 'heart', 'client' => 'Solstice Yoga Studio', 'stat' => '3x', 'statLabel' => 'more class bookings', 'quote' => 'Our booking calendar used to be a shared spreadsheet. Now people book from their phone.', 'body' => 'Solstice had no website at all — just word of mouth. We built them a site with class booking, set up hosting and email, and helped them show up in local search.'],
        ['sector' => 'Home services', 'icon' => 'gear', 'client' => 'Corner Hardware & Repair', 'stat' => '+120', 'statLabel' => 'quote requests per month', 'quote' => 'People find us on Google now instead of just driving past.', 'body' => 'Corner Hardware had a storefront but no online presence at all. We built a simple, fast site with a quote-request form and got them ranking on Google Maps for their service area.'],
        ['sector' => 'Creator', 'icon' => 'spark', 'client' => 'Nomad Coffee Roasters', 'stat' => '2 wks', 'statLabel' => 'from first call to a live app', 'quote' => 'We had an idea for a loyalty app on a napkin. Two weeks later it was in the App Store.', 'body' => 'Nomad wanted a simple loyalty app for regulars. We designed it, built it, and handled the entire App Store submission — they never had to touch a developer account.'],
        ['sector' => 'Nonprofit', 'icon' => 'flag', 'client' => 'Kinship Pet Rescue', 'stat' => '+210', 'statLabel' => 'volunteer sign-ups since launch', 'quote' => 'Donations and volunteer sign-ups finally happen without ten emails back and forth.', 'body' => 'Kinship ran on a free page builder that couldn\'t handle donations or sign-ups. We rebuilt their site, added donation and volunteer forms, and moved them onto their own domain.'],
        ['sector' => 'Retail', 'icon' => 'box', 'client' => 'Bloom & Bramble Florist', 'stat' => '+47%', 'statLabel' => 'website-driven sales', 'quote' => 'Customers can finally order flowers from their phone at 11pm.', 'body' => 'Bloom & Bramble took phone orders only. We built an online store with same-day-delivery scheduling and got them showing up first for local flower searches.'],
    ],
    'pricing' => [
        ['n' => 'Starter', 'm' => 39, 'y' => 31, 'd' => 'Hosting, domain renewal and a small monthly update — for once your site is live.', 'f' => ['Hosting, SSL & domain included', '1 small update per month', 'Email support', 'Uptime monitoring'], 'cta' => 'Start with Starter', 'rec' => false],
        ['n' => 'Growth', 'm' => 99, 'y' => 79, 'd' => 'For businesses adding bookings, an online store, or an app.', 'f' => ['Everything in Starter', 'Priority support', 'Marketplace theme credit', 'Monthly SEO check-in', 'App store update handling'], 'cta' => 'Start with Growth', 'rec' => true],
        ['n' => 'Custom Build', 'm' => null, 'y' => null, 'd' => 'A website or app built from scratch around your business.', 'f' => ['Custom design & development', 'Dedicated project lead', 'Domain, hosting, SSL & email included', 'App Store & Play Store publishing', 'Free ranking check-up'], 'cta' => 'Get a free quote', 'rec' => false],
    ],
    'pricing_faq' => [
        ['How do you land on a final price?', 'We ask what you need, what you already have, and how complex it is, then send a fixed quote before any work starts — no surprise invoices.'],
        ['What if I already have a website?', 'We can take over hosting and support for an existing site, or rebuild it if it needs modern love — either way, nothing changes for your visitors during the switch.'],
        ['Can I start with a marketplace theme instead of a custom build?', 'Yes — a ready-made theme costs less than a custom build, and we\'ll still brand and launch it for you.'],
        ['Is ongoing support included?', 'Ongoing hosting, domain renewal and small updates can be added as a monthly care plan once your site or app is live — we\'ll go over options on the quote call.'],
        ['Do you offer nonprofit or small business discounts?', 'Yes, reach out through Contact and we\'ll tailor a plan for community and mission-driven organizations.'],
    ],
    'team' => [
        ['i' => 'MA', 'n' => 'Mara Aldous', 'r' => 'Founder & CEO'],
        ['i' => 'DK', 'n' => 'Devon Kwan', 'r' => 'Head of Engineering'],
        ['i' => 'RS', 'n' => 'Rhea Solano', 'r' => 'Head of Design'],
        ['i' => 'JT', 'n' => 'Jonah Traeger', 'r' => 'VP Client Success'],
    ],
    'values' => [
        ['icon' => 'heart', 't' => 'Plain language, always', 'd' => 'No jargon you need a developer to translate. If we can\'t explain it simply, we haven\'t understood it yet.'],
        ['icon' => 'shield', 't' => 'Nothing rented that should be owned', 'd' => 'Your domain, your site, your app — registered and built in your name, not locked to us.'],
        ['icon' => 'users', 't' => 'A real person replies', 'd' => 'Support that\'s an actual person who knows your project, not a ticket number.'],
        ['icon' => 'spark', 't' => 'Built to be found, not just to exist', 'd' => 'A website nobody can find isn\'t really online. SEO is part of the build, not an upsell.'],
    ],
];
