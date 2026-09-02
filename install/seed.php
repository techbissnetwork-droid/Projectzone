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
}
