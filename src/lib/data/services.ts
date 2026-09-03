export type Service = {
  slug: string;
  name: string;
  short: string;
  icon: string;
  eyebrow: string;
  headline: string;
  summary: string;
  problem: string;
  solution: string;
  benefits: string[];
  process: { title: string; detail: string }[];
  technology: string[];
  outcome: string;
  related: string[];
};

export const services: Service[] = [
  {
    slug: "website-development",
    name: "Websites",
    short: "High-performance websites built around business goals.",
    icon: "Globe",
    eyebrow: "Web Development",
    headline: "Websites engineered to represent and grow your business.",
    summary:
      "A TECHBISS website is not a brochure — it's the foundation of your digital operations, built for speed, clarity and conversion.",
    problem:
      "Most businesses either have no website, or one that was built quickly and never revisited — slow, outdated, and disconnected from how the business actually operates today.",
    solution:
      "We design and engineer a website around your customers' real decisions: what they need to see, how they need to act, and how the site connects to the rest of your digital operations — payments, booking, email, analytics.",
    benefits: [
      "Custom design system built around your brand",
      "Sub-second load times and strong Core Web Vitals",
      "Content structure your team can actually maintain",
      "Built to connect with hosting, SSL, email and payments",
      "SEO-structured from the first line of code",
    ],
    process: [
      { title: "Discover", detail: "We map your business, customers and goals." },
      { title: "Architect", detail: "Information architecture, content model, technical plan." },
      { title: "Design", detail: "A visual system unique to your brand." },
      { title: "Build", detail: "Production engineering with real content." },
      { title: "Launch", detail: "Domain, hosting, SSL and email configured together." },
    ],
    technology: ["Next.js", "TypeScript", "Headless CMS", "Edge hosting", "Structured data / SEO"],
    outcome: "A site that loads instantly, ranks properly, and gives customers a reason to act.",
    related: ["ecommerce", "hosting-infrastructure", "automation"],
  },
  {
    slug: "mobile-app-development",
    name: "Apps",
    short: "Android, iOS and custom applications.",
    icon: "Smartphone",
    eyebrow: "Mobile & App Development",
    headline: "Native-quality applications for iOS, Android and the web.",
    summary:
      "From customer-facing apps to internal operating tools, we build mobile products that feel fast, native and dependable.",
    problem:
      "Businesses that grow past a website often need a dedicated app — for bookings, orders, loyalty, or internal operations — but building one in-house is slow and expensive.",
    solution:
      "We design and build cross-platform applications sharing a single codebase with your web platform where possible, connected to the same backend, payments and customer data.",
    benefits: [
      "Shared design system with your website and dashboard",
      "Push notifications, offline support, native performance",
      "App Store and Play Store submission handled end-to-end",
      "Connected to the same payments and customer data",
      "Built for iteration — not a one-time delivery",
    ],
    process: [
      { title: "Define", detail: "Core flows, platforms and success metrics." },
      { title: "Prototype", detail: "Clickable prototypes validated with real users." },
      { title: "Build", detail: "Native or cross-platform engineering." },
      { title: "Test", detail: "Device testing across real hardware." },
      { title: "Ship", detail: "Store submission, monitoring, versioning." },
    ],
    technology: ["React Native", "Swift", "Kotlin", "Push infrastructure", "App analytics"],
    outcome: "An app your customers actually keep on their home screen.",
    related: ["website-development", "payments", "automation"],
  },
  {
    slug: "ecommerce",
    name: "E-commerce",
    short: "Turn products into online businesses.",
    icon: "ShoppingBag",
    eyebrow: "E-commerce",
    headline: "Sell online without compromising on speed or trust.",
    summary:
      "A complete commerce system — catalog, cart, checkout, payments, inventory and fulfillment — designed around your products.",
    problem:
      "Generic store builders slow down as catalogs grow, and rarely fit the exact way a business actually sells and fulfills orders.",
    solution:
      "We build commerce platforms tuned to your catalog size, fulfillment model and payment needs — from a focused product line to multi-vendor marketplaces.",
    benefits: [
      "Fast catalog browsing, even with thousands of SKUs",
      "Secure checkout with major payment providers",
      "Inventory and order management built in",
      "Subscription and recurring billing support",
      "Analytics on conversion, not just traffic",
    ],
    process: [
      { title: "Catalog audit", detail: "Products, variants, pricing and fulfillment model." },
      { title: "Commerce architecture", detail: "Cart, checkout, tax, shipping, payments." },
      { title: "Storefront design", detail: "A store that sells your specific products well." },
      { title: "Integrate", detail: "Payments, inventory, fulfillment, email." },
      { title: "Launch & grow", detail: "Conversion tracking and continuous optimization." },
    ],
    technology: ["Headless commerce", "Stripe / payment gateways", "Inventory sync", "Tax & shipping APIs"],
    outcome: "A store that converts, and stays fast as your catalog grows.",
    related: ["payments", "website-development", "automation"],
  },
  {
    slug: "hosting-infrastructure",
    name: "Hosting & Infrastructure",
    short: "Reliable infrastructure your business can depend on.",
    icon: "Server",
    eyebrow: "Hosting & Infrastructure",
    headline: "Infrastructure that stays online while you focus on business.",
    summary:
      "Managed hosting on modern cloud infrastructure — scaled, monitored and backed up, without you needing a technical team.",
    problem:
      "Downtime, slow servers and unmanaged infrastructure quietly cost businesses customers and revenue every day.",
    solution:
      "We provision, monitor and manage infrastructure sized to your traffic — with automatic scaling, backups and uptime monitoring included.",
    benefits: [
      "Global edge delivery for fast load times everywhere",
      "Automatic backups and point-in-time recovery",
      "24/7 uptime monitoring and alerting",
      "Scales automatically with traffic",
      "Staging environments for safe updates",
    ],
    process: [
      { title: "Assess", detail: "Traffic patterns, growth plans, compliance needs." },
      { title: "Provision", detail: "Infrastructure sized and configured for your stack." },
      { title: "Migrate", detail: "Zero-downtime migration from existing hosting." },
      { title: "Monitor", detail: "Uptime, performance and error monitoring." },
      { title: "Scale", detail: "Capacity adjusted as your business grows." },
    ],
    technology: ["Edge CDN", "Managed containers", "Automated backups", "Uptime monitoring"],
    outcome: "Infrastructure that simply works, every day, at any scale.",
    related: ["ssl-security", "maintenance-support", "website-development"],
  },
  {
    slug: "ssl-security",
    name: "SSL & Security",
    short: "Protect your website and your customers.",
    icon: "ShieldCheck",
    eyebrow: "SSL & Security",
    headline: "Security your customers can trust, handled automatically.",
    summary:
      "SSL certificates, security headers, monitoring and hardening — configured correctly and kept current, automatically.",
    problem:
      "An expired certificate or misconfigured security setting can take a site offline or expose customer data — usually discovered too late.",
    solution:
      "We configure and continuously manage SSL, security headers, firewall rules and monitoring so your site — and the data flowing through it — stays protected.",
    benefits: [
      "Automatic SSL issuance and renewal",
      "Security headers and hardened configuration",
      "Malware and vulnerability monitoring",
      "DDoS mitigation on infrastructure level",
      "Compliance-ready configuration",
    ],
    process: [
      { title: "Audit", detail: "Review current certificates and configuration." },
      { title: "Configure", detail: "SSL, headers, firewall rules." },
      { title: "Automate", detail: "Renewal and monitoring, without manual steps." },
      { title: "Monitor", detail: "Continuous vulnerability and uptime scanning." },
    ],
    technology: ["Automated SSL/TLS", "WAF", "Security headers", "Vulnerability scanning"],
    outcome: "A site that's protected by default — not by accident.",
    related: ["hosting-infrastructure", "payments", "maintenance-support"],
  },
  {
    slug: "business-email",
    name: "Business Email",
    short: "Professional communication on your own domain.",
    icon: "Mail",
    eyebrow: "Business Email",
    headline: "Email that matches the business you've built.",
    summary:
      "Professional mailboxes on your own domain, configured with proper deliverability from day one.",
    problem:
      "Free email addresses undercut trust, and DIY email setups often fail silently — landing in spam because of missing configuration.",
    solution:
      "We provision business mailboxes on your domain, configure SPF, DKIM and DMARC correctly, and connect them to the tools your team already uses.",
    benefits: [
      "Mailboxes on yourbusiness.com, not a free provider",
      "Correct SPF / DKIM / DMARC for deliverability",
      "Shared inboxes and team aliases",
      "Calendar and contacts included",
      "Mobile and desktop apps supported",
    ],
    process: [
      { title: "Provision", detail: "Mailboxes and aliases for your team." },
      { title: "Authenticate", detail: "SPF, DKIM, DMARC configured correctly." },
      { title: "Migrate", detail: "Existing mail and contacts moved over." },
      { title: "Train", detail: "Quick onboarding for your team." },
    ],
    technology: ["Business mail hosting", "SPF/DKIM/DMARC", "Calendar & contacts sync"],
    outcome: "Email that lands in the inbox — and looks like the business you are.",
    related: ["hosting-infrastructure", "automation"],
  },
  {
    slug: "automation",
    name: "Automation",
    short: "Reduce repetitive operations.",
    icon: "Workflow",
    eyebrow: "Automation",
    headline: "Automate the busywork behind every order and enquiry.",
    summary:
      "We connect the systems your business already runs on — bookings, orders, email, payments — so information moves without manual re-entry.",
    problem:
      "Growing businesses end up manually copying information between spreadsheets, inboxes and tools, which doesn't scale and invites mistakes.",
    solution:
      "We map your operational workflow and automate the repetitive steps — confirmations, notifications, data sync, reporting — using reliable, monitored automations.",
    benefits: [
      "Automated confirmations, receipts and reminders",
      "Data synced across your tools automatically",
      "Custom internal dashboards and reports",
      "Fewer manual errors, faster response times",
      "Monitored automations, not fragile scripts",
    ],
    process: [
      { title: "Map", detail: "Document the current manual workflow." },
      { title: "Design", detail: "Identify what to automate first for impact." },
      { title: "Build", detail: "Connect systems with monitored automations." },
      { title: "Refine", detail: "Iterate as your operations evolve." },
    ],
    technology: ["Workflow engines", "Webhooks & APIs", "Scheduled jobs", "Internal dashboards"],
    outcome: "Hours returned to your team, every single week.",
    related: ["business-email", "payments", "maintenance-support"],
  },
  {
    slug: "payments",
    name: "Payments",
    short: "Accept and manage online transactions.",
    icon: "CreditCard",
    eyebrow: "Payments",
    headline: "Get paid online, reliably and securely.",
    summary:
      "Full payment integration — cards, wallets, subscriptions and payouts — connected directly to your website, app or booking system.",
    problem:
      "Manual invoicing and disconnected payment links lose sales and make reconciliation painful as order volume grows.",
    solution:
      "We integrate proven payment infrastructure directly into your product — checkout, subscriptions, refunds and payouts — with reporting built in.",
    benefits: [
      "Card, wallet and local payment methods",
      "Subscription and recurring billing",
      "Automatic invoicing and receipts",
      "PCI-compliant by design",
      "Real-time payment reporting",
    ],
    process: [
      { title: "Plan", detail: "Payment methods, currencies, billing model." },
      { title: "Integrate", detail: "Checkout, subscriptions, payouts." },
      { title: "Test", detail: "End-to-end payment and refund testing." },
      { title: "Launch", detail: "Go live with monitoring and reporting." },
    ],
    technology: ["Stripe", "PayPal", "Local payment gateways", "Subscription billing"],
    outcome: "Revenue that flows in without manual follow-up.",
    related: ["ecommerce", "automation", "ssl-security"],
  },
  {
    slug: "maintenance-support",
    name: "Maintenance & Support",
    short: "Continuous improvement, not one-time delivery.",
    icon: "LifeBuoy",
    eyebrow: "Maintenance & Support",
    headline: "A technology partner that stays after launch.",
    summary:
      "Ongoing maintenance, monitoring, updates and support — so your digital operations keep working, and keep improving.",
    problem:
      "Most agencies disappear after launch, leaving businesses without support when something breaks or needs to change.",
    solution:
      "TECHBISS remains involved — monitoring uptime and performance, applying updates, and continuously improving based on real usage data.",
    benefits: [
      "Proactive monitoring and issue resolution",
      "Regular updates and security patching",
      "Direct support from the team that built it",
      "Performance and conversion optimization over time",
      "Flexible support plans as you grow",
    ],
    process: [
      { title: "Monitor", detail: "Uptime, performance and error tracking." },
      { title: "Maintain", detail: "Updates, patches and dependency upkeep." },
      { title: "Support", detail: "Direct access to the team, when you need it." },
      { title: "Improve", detail: "Ongoing optimization based on real data." },
    ],
    technology: ["Uptime monitoring", "Error tracking", "Performance auditing", "Support ticketing"],
    outcome: "A digital presence that keeps getting better, not older.",
    related: ["hosting-infrastructure", "ssl-security", "automation"],
  },
];

export function getService(slug: string) {
  return services.find((s) => s.slug === slug);
}
