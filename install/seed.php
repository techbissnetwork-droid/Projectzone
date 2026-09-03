<?php
declare(strict_types=1);

/** Starter content so the site is never empty on first load. */
function install_seed(PDO $pdo, string $ts): void
{
    $services = [
        ['websites',   'Websites',        'High-performance websites built around your brand.',        'Designed, written and engineered in-house. No page-builder bloat, no theme you share with ten thousand other businesses.', "Bespoke design system\nSub-second load targets\nCMS your team can actually use\nFull SEO & schema markup", 'Next.js, Astro, Headless CMS'],
        ['apps',       'Apps',            'Android, iOS and internal business applications.',          'Customer-facing apps and the internal tools your staff live in — sharing one backend, one login and one source of truth.', "Native & cross-platform\nOffline-first data sync\nPush notifications\nStore submission handled", 'React Native, Flutter, Kotlin, Swift'],
        ['domains',    'Domains',         'Your digital identity starts with the right name.',         'Registration, transfer and DNS managed for you — including the records that make email deliver and the site resolve everywhere.', ".com / .np / country TLDs\nDNS & record management\nAuto-renewal & lock\nWHOIS privacy", 'Registrar, DNS, DNSSEC'],
        ['hosting',    'Hosting & VPS',   'Reliable infrastructure sized to your business.',           'Managed servers with monitoring, backups and someone on the other end when it matters.', "Managed VPS & cloud\nDaily off-site backups\nCDN & edge caching\n24/7 uptime monitoring", 'NVMe, LiteSpeed, Docker, CDN'],
        ['security',   'SSL & Security',  'Protect your website, your customers and your data.',       'Encryption, hardening and active defence — plus a recovery plan for the day something does go wrong.', "SSL / TLS certificates\nWeb application firewall\nMalware scanning & removal\nRestore-tested backups", 'TLS 1.3, WAF, DDoS, 2FA'],
        ['email',      'Business Email',  'Professional communication on your own domain.',            'you@yourbusiness.com — configured with the authentication records that keep it out of the spam folder.', "Google Workspace / Microsoft 365\nSPF, DKIM & DMARC set up\nShared inboxes & aliases\nMigration from existing mail", 'Workspace, M365, IMAP, DMARC'],
        ['ecommerce',  'E-Commerce',      'Turn your products into a business that sells while you sleep.', 'Catalogue, checkout, delivery, invoices and stock — connected so that one sale updates everything at once.', "Custom or Shopify / Woo builds\nInventory & order management\nDelivery & courier integration\nAbandoned-cart recovery", 'Shopify, WooCommerce, Medusa, Stripe'],
        ['automation', 'Automation',      'Replace repetitive work with systems that never forget.',   'Invoices that send themselves, stock that reorders itself, follow-ups that happen on time.', "Workflow & approval automation\nCRM and accounting sync\nAutomated reporting\nInternal dashboards", 'Webhooks, Queues, API integration'],
        ['payments',   'Payments',        'Accept digital payments and reconcile every transaction.',  'Local wallets and international cards on the same checkout, with settlement you can actually audit.', "eSewa, Khalti, FonePay\nStripe & card gateways\nSubscriptions & invoicing\nReconciliation reports", 'Gateways, Webhooks, PCI-aware'],
    ];
    $st = $pdo->prepare('INSERT INTO services (slug,title,summary,body,features,tech,icon,sort_order,is_active,created_at)
                         VALUES (?,?,?,?,?,?,?,?,1,?)');
    foreach ($services as $i => $s) {
        $st->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[0], $i * 10, $ts]);
    }

    $products = [
        ['Restaurant Ordering System', 'restaurant-ordering-system', 'Hospitality',
         'Digital menu, online ordering, table reservations and a kitchen display — ready to brand and launch.',
         "A complete ordering stack for a single-location restaurant. Menu management, delivery and pickup ordering, table booking, kitchen tickets and a payment-ready checkout.",
         'PHP, MySQL, Bootstrap, REST API',
         "Full source code\nDatabase schema & seed data\nAdmin panel\nInstallation guide\n6 months of updates",
         45000, 35000],
        ['Retail Storefront', 'retail-storefront', 'E-Commerce',
         'A product catalogue, cart and checkout with inventory that stays in sync.',
         "Catalogue, variants, stock levels, coupon codes, order management and courier hand-off. Built to be rebranded in an afternoon.",
         'PHP, MySQL, Alpine.js, Stripe',
         "Full source code\nPayment gateway hooks\nAdmin panel\nInstallation guide\n6 months of updates",
         60000, null],
        ['School Management Portal', 'school-management-portal', 'Education',
         'Admissions, attendance, results and fee collection behind one login per role.',
         "Three roles — admin, teacher, parent — sharing one record set. Attendance, marks, report cards, fee invoices and notices.",
         'PHP, MySQL, Chart.js',
         "Full source code\nRole-based access\nReport card templates\nInstallation guide\n12 months of updates",
         85000, 72000],
        ['Clinic Booking System', 'clinic-booking-system', 'Healthcare',
         'Patient appointments, doctor schedules and billing on one calendar.',
         "Appointment booking with doctor availability, patient records, visit history and billing. Designed for small clinics that outgrew a paper diary.",
         'PHP, MySQL, FullCalendar',
         "Full source code\nCalendar & scheduling engine\nAdmin panel\nInstallation guide\n6 months of updates",
         70000, null],
    ];
    $st = $pdo->prepare('INSERT INTO products (title,slug,category,summary,body,tech,includes,price,sale_price,is_active,is_featured,sort_order,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)');
    foreach ($products as $i => $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $i === 0 ? 1 : 0, $i * 10, $ts]);
    }

    tb_seed_content($pdo, $ts);
    tb_seed_payments($pdo, $ts);
}

/** Editable content blocks, starter pages and menus. Split out so a
 *  migration can add them to an existing install without touching
 *  services or products. */
function tb_seed_content(PDO $pdo, string $ts): void
{
    /* ── repeatable content blocks ───────────────────────── */
    $ci = $pdo->prepare('INSERT INTO content_items (kind,label,title,body,extra,meta1,meta2,icon,sort_order,is_active,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,1,?)');
    $blocks = [];

    foreach ([
        ['Services', '12'],
        ['Uptime', '99.98%'],
        ['Projects', '140+'],
    ] as $i => $r) { $blocks[] = ['stat', $r[0], $r[1], null, null, null, null, null, $i * 10]; }

    foreach ([
        ['Discover', 'week 1', 'We understand your business, your customers and your goals — how orders actually arrive, where time is lost, what growth is blocked on.',
         "Business & operations audit\nCustomer journey mapping\nTechnical requirements\nScope, budget and timeline", 'mapping business logic'],
        ['Design', 'week 2–3', 'We create the digital experience and the architecture behind it — interface, brand system and the data model they sit on.',
         "Design system & UI\nInteractive prototype\nSystem architecture\nContent & copy structure", 'composing the system'],
        ['Build', 'week 3–8', 'We develop the website, the application and the infrastructure together, shipping to a staging environment you can watch grow.',
         "Front-end & backend build\nIntegrations & automation\nQA across real devices\nWeekly staging reviews", 'building & testing'],
        ['Launch', 'launch week', 'We configure domain, hosting, SSL, email and deployment — then move you across without downtime and hand over the keys.',
         "DNS, SSL & mail records\nZero-downtime cutover\nAnalytics & monitoring\nTeam training & handover", 'cutting over'],
        ['Grow', 'ongoing', 'We maintain, optimise and keep improving the digital side of your business — because launch day is the beginning of the work, not the end.',
         "Maintenance & support\nPerformance & SEO tuning\nConversion improvements\nNew features each quarter", 'compounding results'],
    ] as $i => $r) { $blocks[] = ['process', $r[0], $r[1], $r[2], $r[3], $r[4], null, null, $i * 10]; }

    foreach ([
        ['Secure', 'auto-renewed', 'TLS 1.3, firewalled, hardened and access-audited by default.', 'meter'],
        ['Scalable', '3 regions', 'Capacity that follows demand instead of the other way round.', 'bars'],
        ['Fast', 'lighthouse target', 'Edge-cached, image-optimised, measured against Core Web Vitals.', 'gauge'],
        ['Reliable', '99.98% uptime', 'Monitored around the clock, with restore-tested backups.', 'ticks'],
        ['Cloud-ready', 'containerised', 'Containerised and portable — you are never locked to one vendor.', 'orbits'],
        ['Mobile-ready', 'touch-first', 'Designed for the device most of your customers actually use.', 'devices'],
    ] as $i => $r) { $blocks[] = ['pillar', $r[0], $r[1], $r[2], null, null, null, $r[3], $i * 10]; }

    foreach ([
        ['BUSINESS', 'Goals & operations', '0'],
        ['TECHBISS CORE', 'Architecture & APIs', '1'],
        ['WEBSITE', 'Marketing & conversion', '2'],
        ['APP', 'iOS & Android', '2'],
        ['PAYMENTS', 'Checkout', '2'],
        ['EMAIL', 'On your domain', '2'],
        ['HOSTING', 'Edge + origin', '3'],
        ['DATABASE', 'One source of truth', '3'],
        ['SECURITY', 'TLS · WAF · access', '3'],
        ['CLOUD', 'Scale & backups', '3'],
    ] as $i => $r) { $blocks[] = ['arch', $r[0], $r[1], null, null, $r[2], null, null, $i * 10]; }

    foreach ([
        ['Restaurant', 'Online ordering system', 'Digital menu, delivery and pickup ordering, table reservations, kitchen display and payments — one flow from customer to counter.',
         "Menu & ordering\nReservations\nKitchen tickets\nDigital payments\nLoyalty"],
        ['Retail store', 'E-commerce platform', 'Your shelves online with live stock, courier integration, invoices and a storefront that sells outside opening hours.',
         "Catalogue\nInventory sync\nCheckout\nDelivery\nAnalytics"],
        ['School', 'Education portal', 'Admissions, attendance, results, fee collection and parent communication behind one secure login per role.',
         "Admissions\nAttendance\nResults\nFee payments\nParent portal"],
        ['Hospital', 'Booking & management system', 'Patient appointments, doctor schedules, records and billing on infrastructure built for privacy and uptime.',
         "Appointments\nSchedules\nRecords\nBilling\nEncrypted backups"],
        ['Service company', 'Online booking platform', 'Availability, quotes, bookings, job dispatch and automatic follow-up — so enquiries stop dying in a chat thread.',
         "Availability\nQuotes\nDispatch\nInvoices\nCRM"],
        ['Startup', 'Complete digital product', 'Brand, marketing site, product, API and infrastructure — built to ship early and scale when the numbers say so.',
         "Brand\nProduct\nAPI\nCloud\nAnalytics"],
    ] as $i => $r) { $blocks[] = ['transform', $r[0], $r[1], $r[2], $r[3], null, null, null, $i * 10]; }

    foreach ($blocks as $b) {
        $ci->execute([$b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $ts]);
    }

    /* ── starter pages ───────────────────────────────────── */
    $pg = $pdo->prepare('INSERT INTO pages (slug,title,subtitle,eyebrow,body,status,show_cta,sort_order,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?)');
    $pg->execute(['about', 'About us', 'A team that builds the whole digital side of a business, not just the front page.', 'Who we are',
        "We started because too many good businesses were being handed a website and left to work out hosting, email, security and payments on their own.\n\n"
      . "Today we take responsibility for the whole stack. One team designs the interface, writes the code, configures the server, sets up the mail records and picks up the phone when something breaks.\n\n"
      . "Edit this page from the admin panel: Pages → About us.",
        'published', 1, 10, $ts, $ts]);
    $pg->execute(['privacy', 'Privacy policy', 'How we handle the information you give us.', 'Legal',
        "Replace this text with your own privacy policy.\n\nCover at least: what you collect, why you collect it, how long you keep it, who you share it with, and how someone asks for their data to be removed.",
        'published', 0, 20, $ts, $ts]);
    $pg->execute(['terms', 'Terms of service', 'The terms that apply when you work with us.', 'Legal',
        "Replace this text with your own terms.\n\nCover at least: scope of work, payment terms, revisions, ownership of the finished work, hosting and maintenance obligations, and how either side ends the engagement.",
        'published', 0, 30, $ts, $ts]);

    /* ── menus ───────────────────────────────────────────── */
    $nv = $pdo->prepare('INSERT INTO nav_items (location,label,url,page_id,new_tab,sort_order,is_active,created_at)
                         VALUES (?,?,?,?,0,?,1,?)');
    foreach ([['Services', 'services.php'], ['Work', 'portfolio.php'],
              ['Marketplace', 'marketplace.php'], ['About', null], ['Contact', 'contact.php']] as $i => $n) {
        $nv->execute(['header', $n[0], $n[1], $n[1] === null ? 1 : null, $i * 10, $ts]);
    }
    foreach ([['Services', 'services.php'], ['Selected work', 'portfolio.php'],
              ['Marketplace', 'marketplace.php']] as $i => $n) {
        $nv->execute(['footer_1', $n[0], $n[1], null, $i * 10, $ts]);
    }
    foreach ([['About us', null], ['Contact', 'contact.php'],
              ['Privacy policy', null], ['Terms of service', null]] as $i => $n) {
        $nv->execute(['footer_2', $n[0], $n[1], $n[1] === null ? ($i === 0 ? 1 : ($i === 2 ? 2 : 3)) : null, $i * 10, $ts]);
    }
    foreach ([['Client portal', 'login.php'], ['Support & maintenance', 'login.php'],
              ['Request a quote', 'contact.php']] as $i => $n) {
        $nv->execute(['footer_3', $n[0], $n[1], null, $i * 10, $ts]);
    }
}

/**
 * A bank-transfer method ready to fill in — switched OFF, and with the account
 * fields empty rather than a placeholder number. An active method carrying
 * 0000000000000000 would have real buyers transfer real money into nothing.
 */
function tb_seed_payments(PDO $pdo, string $ts): void
{
    $pdo->prepare('INSERT INTO payment_methods
        (code,name,provider,summary,instructions,account_name,account_number,config,is_active,is_test,sort_order,created_at)
        VALUES (?,?,?,?,?,?,?,?,0,0,?,?)')
        ->execute([
            'bank-transfer', 'Bank transfer', 'manual',
            'Pay directly into our account and send the reference.',
            "Transfer the total to the account below, then reply with the transaction reference.\n"
          . "We confirm every transfer manually within one business day.",
            '', '', null, 10, $ts,
        ]);
}
