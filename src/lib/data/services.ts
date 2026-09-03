export type IconName =
  | "Globe"
  | "Smartphone"
  | "Workflow"
  | "Fingerprint"
  | "Server"
  | "ShieldCheck"
  | "Mail"
  | "ShoppingCart"
  | "AppWindow"
  | "Zap"
  | "CreditCard"
  | "LifeBuoy";

export type AccentColor = "gold" | "signal";

export interface ServiceStat {
  value: string;
  label: string;
}

export interface ServiceFeature {
  title: string;
  description: string;
  icon: IconName;
}

export interface ServiceStep {
  step: string;
  description: string;
}

export interface ServiceFaq {
  q: string;
  a: string;
}

export interface Service {
  slug: string;
  hasDetailPage: boolean;
  name: string;
  fullName: string;
  category: string;
  icon: IconName;
  color: AccentColor;
  shortDescription: string;
  heroDescription: string;
  stats: ServiceStat[];
  features: ServiceFeature[];
  howWeWork: ServiceStep[];
  deliverables: string[];
  technologies: string[];
  faqs: ServiceFaq[];
  relatedServices: string[];
}

export const services: Service[] = [
  {
    slug: "website-development",
    hasDetailPage: true,
    name: "Websites",
    fullName: "Website Design & Development",
    category: "Digital Presence",
    icon: "Globe",
    color: "gold",
    shortDescription:
      "High-performance websites engineered around your brand, your customers and your growth.",
    heroDescription:
      "Your website is the first handshake with every customer you haven't met yet. We design and build fast, beautifully composed websites that carry your brand credibility online — from architecture and content to the last pixel of polish.",
    stats: [
      { value: "98+", label: "Avg. Lighthouse score" },
      { value: "2.4×", label: "Avg. conversion lift" },
      { value: "< 1.2s", label: "Median load time" },
    ],
    features: [
      {
        title: "Brand-first design systems",
        description:
          "A visual language built around your identity, not a template — typography, color and motion tuned to your brand.",
        icon: "Globe",
      },
      {
        title: "Engineered for speed",
        description:
          "Modern rendering, optimized assets and clean code so pages load instantly on any connection.",
        icon: "Zap",
      },
      {
        title: "Content architecture",
        description:
          "Information structured around how customers actually decide, not how your org chart is arranged.",
        icon: "AppWindow",
      },
      {
        title: "SEO-ready foundations",
        description:
          "Semantic markup, structured data and clean URLs so search engines understand your business from day one.",
        icon: "Fingerprint",
      },
      {
        title: "Built to extend",
        description:
          "A component system your team can grow — new pages and sections without starting over.",
        icon: "Workflow",
      },
    ],
    howWeWork: [
      { step: "Discover", description: "Audit your brand, competitors and customer journey." },
      { step: "Architect", description: "Map sitemap, content and conversion paths." },
      { step: "Design & build", description: "Design the system, then build it on modern web foundations." },
      { step: "Launch & tune", description: "Ship, measure real usage and refine performance." },
    ],
    deliverables: [
      "Custom design system & UI kit",
      "Fully responsive multi-page website",
      "CMS for self-service content updates",
      "On-page SEO & analytics setup",
      "Performance & accessibility audit",
      "30-day post-launch support window",
    ],
    technologies: ["Next.js", "React", "TypeScript", "Headless CMS", "Vercel / Cloud hosting", "Tailwind CSS"],
    faqs: [
      {
        q: "How long does a typical website project take?",
        a: "Most brand websites launch in 4–8 weeks depending on scope. Larger multi-page platforms with custom features run 8–14 weeks.",
      },
      {
        q: "Will I be able to update content myself?",
        a: "Yes. Every site ships with a content management interface so your team can update copy, images and pages without touching code.",
      },
      {
        q: "Do you design and develop, or just one?",
        a: "Both, under one roof. The same team that designs your site builds it — nothing gets lost in translation between disciplines.",
      },
    ],
    relatedServices: ["e-commerce", "hosting-infrastructure", "security"],
  },
  {
    slug: "mobile-app-development",
    hasDetailPage: true,
    name: "Mobile Apps",
    fullName: "Mobile App Development",
    category: "Digital Presence",
    icon: "Smartphone",
    color: "signal",
    shortDescription:
      "Native-grade Android, iOS and cross-platform apps that put your business in your customer's pocket.",
    heroDescription:
      "For businesses whose customers live on their phones, a website isn't enough. We design and build fast, reliable mobile applications — from booking and ordering apps to full customer platforms — for iOS, Android and beyond.",
    stats: [
      { value: "4.7★", label: "Avg. store rating" },
      { value: "2", label: "Platforms, one codebase" },
      { value: "99.9%", label: "Crash-free sessions" },
    ],
    features: [
      {
        title: "Cross-platform engineering",
        description:
          "One codebase, native performance on iOS and Android — faster to build, easier to maintain.",
        icon: "Smartphone",
      },
      {
        title: "Offline-first architecture",
        description:
          "Apps that stay useful without a connection and sync cleanly the moment it returns.",
        icon: "Zap",
      },
      {
        title: "Push & lifecycle engagement",
        description:
          "Notifications, onboarding and retention flows designed to bring customers back.",
        icon: "Workflow",
      },
      {
        title: "Secure by default",
        description:
          "Encrypted storage, secure auth and hardened APIs protecting customer data end to end.",
        icon: "ShieldCheck",
      },
      {
        title: "App store readiness",
        description:
          "Store listings, review compliance and release management handled for you.",
        icon: "AppWindow",
      },
    ],
    howWeWork: [
      { step: "Define", description: "Scope the core user journeys that matter most." },
      { step: "Prototype", description: "Click-through prototypes validated before a line of code." },
      { step: "Build", description: "Native-grade development with continuous testing." },
      { step: "Ship & support", description: "Store submission, monitoring and version updates." },
    ],
    deliverables: [
      "iOS & Android applications",
      "Backend API & admin dashboard",
      "Push notification system",
      "App store submission & compliance",
      "Analytics & crash monitoring",
      "Ongoing update roadmap",
    ],
    technologies: ["React Native", "Swift", "Kotlin", "Node.js", "Firebase", "GraphQL"],
    faqs: [
      {
        q: "Do you build for both iOS and Android?",
        a: "Yes, typically from a single cross-platform codebase, which keeps cost and maintenance far lower than two native builds.",
      },
      {
        q: "Can the app connect to our existing systems?",
        a: "Yes. We regularly integrate with existing POS, CRM, inventory and booking systems, or build the backend from scratch if you don't have one.",
      },
      {
        q: "What happens after launch?",
        a: "We offer ongoing maintenance plans covering OS updates, store compliance changes, bug fixes and new feature releases.",
      },
    ],
    relatedServices: ["business-digitization", "payment-integration", "automation"],
  },
  {
    slug: "e-commerce",
    hasDetailPage: true,
    name: "E-commerce",
    fullName: "E-commerce Development",
    category: "Digital Presence",
    icon: "ShoppingCart",
    color: "gold",
    shortDescription:
      "Turn a product catalog into a scalable online store built to convert and built to grow.",
    heroDescription:
      "Selling online is more than a shopping cart. We build complete commerce platforms — catalog, checkout, payments, fulfillment and customer accounts — engineered to convert browsers into buyers and buyers into repeat customers.",
    stats: [
      { value: "3.1×", label: "Avg. checkout conversion" },
      { value: "< 2s", label: "Cart-to-checkout time" },
      { value: "100%", label: "PCI-compliant payments" },
    ],
    features: [
      {
        title: "Conversion-tuned checkout",
        description:
          "A frictionless path from cart to confirmation, with every drop-off point designed away.",
        icon: "ShoppingCart",
      },
      {
        title: "Catalog & inventory sync",
        description:
          "Products, variants and stock kept accurate across your store and back office in real time.",
        icon: "Workflow",
      },
      {
        title: "Integrated payments",
        description:
          "Cards, wallets and local payment methods accepted securely, out of the box.",
        icon: "CreditCard",
      },
      {
        title: "Merchandising & search",
        description:
          "Fast, relevant on-site search and merchandising tools that help customers find what they want.",
        icon: "Fingerprint",
      },
      {
        title: "Built to scale",
        description:
          "Infrastructure that handles traffic spikes — launches, sales and seasonal peaks — without buckling.",
        icon: "Server",
      },
    ],
    howWeWork: [
      { step: "Map the journey", description: "From discovery to post-purchase, mapped end to end." },
      { step: "Design the store", description: "Merchandising, catalog and checkout UX." },
      { step: "Integrate", description: "Payments, shipping, tax and inventory systems." },
      { step: "Launch & optimize", description: "Go live, then iterate on real conversion data." },
    ],
    deliverables: [
      "Full storefront design & build",
      "Product catalog & inventory system",
      "Secure checkout & payment integration",
      "Order, shipping & tax configuration",
      "Customer accounts & order history",
      "Conversion analytics dashboard",
    ],
    technologies: ["Next.js Commerce", "Shopify / Headless", "Stripe", "Algolia", "Node.js", "PostgreSQL"],
    faqs: [
      {
        q: "Can you migrate our existing store?",
        a: "Yes — products, customers, order history and SEO redirects are migrated carefully so you don't lose rankings or historical data.",
      },
      {
        q: "Which payment providers do you support?",
        a: "Stripe, PayPal and major regional processors, plus local payment methods depending on your market.",
      },
      {
        q: "Can it handle a big traffic spike, like a launch day?",
        a: "Yes. We build on infrastructure that auto-scales, and we load-test high-traffic events before they happen.",
      },
    ],
    relatedServices: ["payment-integration", "hosting-infrastructure", "automation"],
  },
  {
    slug: "business-digitization",
    hasDetailPage: true,
    name: "Digitization",
    fullName: "Business Digitization",
    category: "Digital Presence",
    icon: "Workflow",
    color: "signal",
    shortDescription:
      "Move an entire offline operation — records, orders, processes — into one connected digital system.",
    heroDescription:
      "Most businesses aren't missing one tool — they're missing a system. We map your offline operations end to end and rebuild them as a connected digital business: records, workflows, customer data and reporting in one place.",
    stats: [
      { value: "-68%", label: "Avg. manual admin time" },
      { value: "1", label: "Unified system of record" },
      { value: "24/7", label: "Operational visibility" },
    ],
    features: [
      {
        title: "Process mapping",
        description:
          "Every manual workflow documented, then redesigned for how digital systems actually work.",
        icon: "Workflow",
      },
      {
        title: "Records migration",
        description:
          "Paper files, spreadsheets and legacy systems consolidated into one structured database.",
        icon: "Server",
      },
      {
        title: "Unified operations dashboard",
        description:
          "One place to see orders, customers, staff and performance — no more switching tools.",
        icon: "AppWindow",
      },
      {
        title: "Staff-ready tooling",
        description:
          "Interfaces designed for the people actually using them, not just the org chart.",
        icon: "Smartphone",
      },
      {
        title: "Change management",
        description:
          "Training and rollout support so the transition sticks, not just the software.",
        icon: "ShieldCheck",
      },
    ],
    howWeWork: [
      { step: "Audit", description: "Shadow real operations to find where digital work pays off." },
      { step: "Blueprint", description: "Design the target system and migration path." },
      { step: "Digitize", description: "Build and migrate data, workflow by workflow." },
      { step: "Train & grow", description: "Roll out to your team and keep improving." },
    ],
    deliverables: [
      "Operations audit & digital blueprint",
      "Centralized business database",
      "Custom operations dashboard",
      "Workflow automation for key processes",
      "Staff onboarding & training",
      "90-day optimization support",
    ],
    technologies: ["Custom web apps", "PostgreSQL", "Airtable / Retool", "Zapier / n8n", "Role-based access", "API integrations"],
    faqs: [
      {
        q: "We still rely on paper and spreadsheets — where do we start?",
        a: "With an audit. We spend time in your actual operation to find which workflows create the most friction, and digitize those first.",
      },
      {
        q: "Do our staff need to be technical?",
        a: "No. We design interfaces around the people already doing the work, and provide hands-on training during rollout.",
      },
      {
        q: "Does this replace software we already use?",
        a: "Not necessarily — we often connect and consolidate existing tools rather than ripping everything out.",
      },
    ],
    relatedServices: ["automation", "mobile-app-development", "hosting-infrastructure"],
  },
  {
    slug: "hosting-infrastructure",
    hasDetailPage: true,
    name: "Hosting & Infrastructure",
    fullName: "Domain, Hosting & Infrastructure",
    category: "Infrastructure",
    icon: "Server",
    color: "signal",
    shortDescription:
      "Your digital identity and the infrastructure it runs on — domains, hosting and VPS, done right.",
    heroDescription:
      "Every digital business starts with an address and a foundation to build on. We handle domain registration, premium hosting and VPS infrastructure so your website, app and email run fast, securely and reliably — with zero guesswork.",
    stats: [
      { value: "99.99%", label: "Infrastructure uptime" },
      { value: "< 50ms", label: "Global edge latency" },
      { value: "24/7", label: "Infrastructure monitoring" },
    ],
    features: [
      {
        title: "Domain strategy",
        description:
          "The right domain, secured and configured correctly — DNS, redirects and subdomains included.",
        icon: "Fingerprint",
      },
      {
        title: "Managed premium hosting",
        description:
          "Enterprise-grade hosting tuned for speed, with monitoring so issues get caught before customers notice.",
        icon: "Server",
      },
      {
        title: "Scalable VPS",
        description:
          "Dedicated virtual infrastructure that scales with traffic instead of falling over during it.",
        icon: "Workflow",
      },
      {
        title: "Global CDN delivery",
        description:
          "Content served from edge locations near your customers, wherever they are.",
        icon: "Zap",
      },
      {
        title: "Automated backups",
        description:
          "Continuous backups and fast recovery, so a bad deploy or outage never costs you data.",
        icon: "ShieldCheck",
      },
    ],
    howWeWork: [
      { step: "Assess", description: "Understand traffic, growth plans and compliance needs." },
      { step: "Provision", description: "Domain, DNS, hosting and VPS configured correctly." },
      { step: "Harden", description: "Security, backups and monitoring put in place." },
      { step: "Monitor & scale", description: "Ongoing oversight as your business grows." },
    ],
    deliverables: [
      "Domain registration & DNS setup",
      "Managed premium hosting or VPS",
      "Global CDN configuration",
      "Automated backup & recovery",
      "Uptime & performance monitoring",
      "Infrastructure documentation",
    ],
    technologies: ["Cloudflare", "AWS / GCP", "Docker", "Nginx", "PostgreSQL / Redis", "Terraform"],
    faqs: [
      {
        q: "Do you handle domain registration too?",
        a: "Yes — we help you choose, register and correctly configure the right domain as part of infrastructure setup.",
      },
      {
        q: "Shared hosting or VPS — which do we need?",
        a: "It depends on traffic and complexity. We assess your needs and recommend (and manage) the right tier, upgrading as you grow.",
      },
      {
        q: "What happens if something goes down?",
        a: "Infrastructure is monitored continuously, with automated alerts and a support team ready to respond, day or night.",
      },
    ],
    relatedServices: ["security", "business-email", "website-development"],
  },
  {
    slug: "security",
    hasDetailPage: true,
    name: "Security",
    fullName: "SSL & Website Security",
    category: "Infrastructure",
    icon: "ShieldCheck",
    color: "gold",
    shortDescription:
      "Protect your website, your customers and your data with security built in, not bolted on.",
    heroDescription:
      "Trust is fragile online, and it takes one incident to lose it. We implement SSL, hardened infrastructure, monitoring and best-practice safeguards so your business, your customers and their data stay protected by default.",
    stats: [
      { value: "A+", label: "SSL Labs rating" },
      { value: "0", label: "Tolerated critical vulnerabilities" },
      { value: "24/7", label: "Threat monitoring" },
    ],
    features: [
      {
        title: "SSL & encryption",
        description:
          "End-to-end encryption for every connection, with automatic certificate renewal.",
        icon: "ShieldCheck",
      },
      {
        title: "Web application firewall",
        description:
          "Filtering malicious traffic before it ever reaches your application.",
        icon: "Server",
      },
      {
        title: "Continuous monitoring",
        description:
          "Automated scanning for vulnerabilities, malware and suspicious activity.",
        icon: "Fingerprint",
      },
      {
        title: "Secure authentication",
        description:
          "Modern auth practices protecting customer accounts and admin access alike.",
        icon: "Zap",
      },
      {
        title: "Compliance-ready",
        description:
          "Data handling aligned with GDPR, PCI-DSS and other standards relevant to your business.",
        icon: "Workflow",
      },
    ],
    howWeWork: [
      { step: "Audit", description: "Assess current exposure across site, app and infrastructure." },
      { step: "Harden", description: "Close gaps: SSL, firewalls, access control, encryption." },
      { step: "Monitor", description: "Continuous scanning and alerting put in place." },
      { step: "Respond & improve", description: "Incident response plan and ongoing review." },
    ],
    deliverables: [
      "SSL/TLS certificate & auto-renewal",
      "Web application firewall configuration",
      "Vulnerability & malware scanning",
      "Secure authentication implementation",
      "Security & compliance documentation",
      "Incident response plan",
    ],
    technologies: ["Let's Encrypt", "Cloudflare WAF", "OWASP practices", "OAuth 2.0", "2FA / MFA", "Automated scanning"],
    faqs: [
      {
        q: "Is SSL really necessary for a small business site?",
        a: "Yes — beyond encrypting data, it's now a baseline trust and SEO signal. Browsers actively flag sites without it.",
      },
      {
        q: "How do you handle customer data protection?",
        a: "Through encryption at rest and in transit, strict access controls, and alignment with relevant regulations like GDPR.",
      },
      {
        q: "What if we're targeted by an attack?",
        a: "Monitoring is designed to catch threats early, and we have an incident response process to contain and resolve issues fast.",
      },
    ],
    relatedServices: ["hosting-infrastructure", "payment-integration", "business-email"],
  },
  {
    slug: "business-email",
    hasDetailPage: true,
    name: "Business Email",
    fullName: "Professional Business Email",
    category: "Infrastructure",
    icon: "Mail",
    color: "signal",
    shortDescription:
      "Professional communication on your own domain — credible, secure and properly deliverable.",
    heroDescription:
      "\"you@gmail.com\" doesn't say established business. We set up professional email on your own domain, configured correctly for deliverability and security, so every message you send reinforces trust rather than undermining it.",
    stats: [
      { value: "99.9%", label: "Inbox deliverability" },
      { value: "100%", label: "SPF / DKIM / DMARC configured" },
      { value: "∞", label: "Team mailboxes" },
    ],
    features: [
      {
        title: "Custom domain mailboxes",
        description:
          "you@yourbusiness.com across your whole team, set up correctly from day one.",
        icon: "Mail",
      },
      {
        title: "Deliverability configuration",
        description:
          "SPF, DKIM and DMARC configured so your emails land in inboxes, not spam folders.",
        icon: "ShieldCheck",
      },
      {
        title: "Cross-device sync",
        description:
          "Mail, calendar and contacts synced cleanly across desktop and mobile.",
        icon: "Smartphone",
      },
      {
        title: "Team administration",
        description:
          "Centralized control for adding staff, groups and shared mailboxes as you grow.",
        icon: "Workflow",
      },
      {
        title: "Spam & threat filtering",
        description:
          "Enterprise-grade filtering keeping phishing and spam out of the inbox.",
        icon: "Fingerprint",
      },
    ],
    howWeWork: [
      { step: "Plan", description: "Map team structure and mailbox needs." },
      { step: "Configure", description: "DNS records, SPF/DKIM/DMARC set up correctly." },
      { step: "Migrate", description: "Existing mail and contacts moved without disruption." },
      { step: "Support", description: "Ongoing administration and troubleshooting." },
    ],
    deliverables: [
      "Custom domain email mailboxes",
      "SPF, DKIM & DMARC configuration",
      "Mail, calendar & contacts sync",
      "Spam & phishing filtering",
      "Mailbox migration from existing provider",
      "Admin console & training",
    ],
    technologies: ["Google Workspace", "Microsoft 365", "Zoho Mail", "DNS / DMARC", "SSO", "Mobile sync"],
    faqs: [
      {
        q: "Can you migrate our existing inboxes?",
        a: "Yes — mail, contacts and calendars are migrated with minimal downtime, and old addresses can keep forwarding during the switch.",
      },
      {
        q: "Which email platform do you recommend?",
        a: "Usually Google Workspace or Microsoft 365, depending on your existing tools and team preferences — we'll help you decide.",
      },
      {
        q: "Why do our emails go to spam right now?",
        a: "Almost always misconfigured or missing SPF/DKIM/DMARC records. We audit and fix deliverability as part of setup.",
      },
    ],
    relatedServices: ["hosting-infrastructure", "security", "business-digitization"],
  },
  {
    slug: "automation",
    hasDetailPage: true,
    name: "Automation",
    fullName: "Business Automation",
    category: "Growth & Operations",
    icon: "Zap",
    color: "gold",
    shortDescription:
      "Replace repetitive manual work with intelligent systems that run your business in the background.",
    heroDescription:
      "Every hour spent on repetitive admin is an hour not spent growing the business. We design automated workflows — for orders, notifications, reporting and operations — that quietly handle the repetitive work so your team doesn't have to.",
    stats: [
      { value: "15+", label: "Hrs saved / week (avg.)" },
      { value: "0", label: "Manual data re-entry" },
      { value: "3×", label: "Faster response times" },
    ],
    features: [
      {
        title: "Workflow automation",
        description:
          "Orders, approvals and notifications routed automatically, without anyone touching a spreadsheet.",
        icon: "Workflow",
      },
      {
        title: "System integration",
        description:
          "Your website, app, inventory and accounting tools connected so data moves itself.",
        icon: "Server",
      },
      {
        title: "Automated reporting",
        description:
          "Dashboards and reports generated on schedule — no more manual data pulls.",
        icon: "AppWindow",
      },
      {
        title: "Customer communication flows",
        description:
          "Confirmations, reminders and follow-ups sent automatically at the right moment.",
        icon: "Mail",
      },
      {
        title: "Smart triggers",
        description:
          "Rules-based logic that reacts to real events — low stock, new orders, missed replies.",
        icon: "Zap",
      },
    ],
    howWeWork: [
      { step: "Identify", description: "Find the repetitive work costing the most time." },
      { step: "Design", description: "Map the automated workflow and its edge cases." },
      { step: "Build", description: "Connect systems and implement the automation." },
      { step: "Refine", description: "Monitor real runs and tune the logic." },
    ],
    deliverables: [
      "Workflow audit & automation roadmap",
      "System-to-system integrations",
      "Automated notification & reporting flows",
      "Custom triggers & business rules",
      "Monitoring & error alerting",
      "Documentation & team handover",
    ],
    technologies: ["n8n / Zapier", "Node.js", "Webhooks & APIs", "Cron / scheduled jobs", "Airtable", "Custom scripts"],
    faqs: [
      {
        q: "What kind of tasks can actually be automated?",
        a: "Order confirmations, inventory alerts, appointment reminders, reporting, invoicing and most repetitive multi-step processes.",
      },
      {
        q: "Will this replace staff?",
        a: "No — the goal is removing repetitive admin so your team spends time on customers and growth instead of data entry.",
      },
      {
        q: "Does automation work with the tools we already use?",
        a: "In most cases yes. We connect existing software wherever possible rather than forcing a full replacement.",
      },
    ],
    relatedServices: ["business-digitization", "payment-integration", "mobile-app-development"],
  },
  {
    slug: "payment-integration",
    hasDetailPage: true,
    name: "Payments",
    fullName: "Online Payment Integration",
    category: "Growth & Operations",
    icon: "CreditCard",
    color: "signal",
    shortDescription:
      "Accept payments online, securely and reliably, with full visibility into every transaction.",
    heroDescription:
      "Getting paid should be the easiest part of running a business. We integrate secure, reliable payment systems into your website and app — cards, wallets and local methods — with clear reporting on every transaction.",
    stats: [
      { value: "100%", label: "PCI-DSS compliant" },
      { value: "< 3s", label: "Avg. checkout time" },
      { value: "15+", label: "Payment methods supported" },
    ],
    features: [
      {
        title: "Secure payment processing",
        description:
          "Card and wallet payments processed through PCI-compliant, encrypted channels.",
        icon: "ShieldCheck",
      },
      {
        title: "Multiple payment methods",
        description:
          "Cards, digital wallets and regional payment methods, matched to your customers.",
        icon: "CreditCard",
      },
      {
        title: "Subscriptions & recurring billing",
        description:
          "Automated recurring payments for memberships, plans and retainers.",
        icon: "Workflow",
      },
      {
        title: "Transaction dashboard",
        description:
          "Real-time visibility into payments, refunds and payouts in one place.",
        icon: "AppWindow",
      },
      {
        title: "Fraud protection",
        description:
          "Automated risk checks that catch fraudulent transactions before they clear.",
        icon: "Fingerprint",
      },
    ],
    howWeWork: [
      { step: "Assess", description: "Understand payment flows, currencies and volume." },
      { step: "Integrate", description: "Connect processors to your website or app." },
      { step: "Secure", description: "Compliance, fraud checks and encryption verified." },
      { step: "Reconcile", description: "Reporting and payout tracking set up." },
    ],
    deliverables: [
      "Payment gateway integration",
      "Multi-method checkout experience",
      "Subscription & recurring billing setup",
      "Fraud detection configuration",
      "Transaction reporting dashboard",
      "PCI-DSS compliance review",
    ],
    technologies: ["Stripe", "PayPal", "Razorpay / regional gateways", "Webhooks", "PCI-DSS", "Node.js"],
    faqs: [
      {
        q: "Which payment providers can you integrate?",
        a: "Stripe and PayPal for most businesses, plus regional processors depending on where your customers are located.",
      },
      {
        q: "Can we offer subscriptions or payment plans?",
        a: "Yes — recurring billing, trials and installment plans can all be configured as part of the integration.",
      },
      {
        q: "How do you keep payments secure?",
        a: "We never store raw card data ourselves — processing runs through PCI-compliant providers with encryption and fraud checks built in.",
      },
    ],
    relatedServices: ["e-commerce", "security", "automation"],
  },
  {
    slug: "custom-web-applications",
    hasDetailPage: false,
    name: "Custom Web Apps",
    fullName: "Custom Web Applications",
    category: "Digital Presence",
    icon: "AppWindow",
    color: "gold",
    shortDescription:
      "Bespoke internal tools and customer platforms built for exactly how your business operates.",
    heroDescription:
      "When off-the-shelf software doesn't fit, we build exactly what your business needs — internal tools, customer portals and platforms tailored to your workflow.",
    stats: [
      { value: "100%", label: "Built to your workflow" },
      { value: "0", label: "Licensing lock-in" },
      { value: "∞", label: "Extensible by design" },
    ],
    features: [
      { title: "Tailored to your workflow", description: "Software shaped around your process, not the other way around.", icon: "Workflow" },
      { title: "Internal tools & dashboards", description: "Purpose-built interfaces for your team's daily operations.", icon: "AppWindow" },
      { title: "Customer-facing portals", description: "Self-service platforms that reduce support load.", icon: "Smartphone" },
      { title: "API-first architecture", description: "Built to connect with the systems you already run.", icon: "Server" },
    ],
    howWeWork: [
      { step: "Scope", description: "Define the exact problem worth solving." },
      { step: "Prototype", description: "Validate the workflow before full build." },
      { step: "Build", description: "Ship a production-grade application." },
      { step: "Iterate", description: "Extend it as the business changes." },
    ],
    deliverables: ["Custom application build", "Admin & user dashboards", "API integrations", "Ongoing iteration support"],
    technologies: ["React", "Node.js", "PostgreSQL", "REST / GraphQL APIs"],
    faqs: [
      { q: "How is this different from a website?", a: "Custom applications involve logic, data and workflows — think dashboards or portals, not just informational pages." },
      { q: "Can it integrate with what we already use?", a: "Yes, custom applications are typically built API-first specifically to connect with your existing tools." },
    ],
    relatedServices: ["business-digitization", "automation", "website-development"],
  },
  {
    slug: "maintenance-support",
    hasDetailPage: false,
    name: "Maintenance & Support",
    fullName: "Maintenance & Technical Support",
    category: "Growth & Operations",
    icon: "LifeBuoy",
    color: "signal",
    shortDescription:
      "Ongoing care that keeps your digital business fast, secure and running without surprises.",
    heroDescription:
      "Launch is the beginning, not the finish line. Our maintenance plans keep your website, app and infrastructure updated, monitored and supported long after launch day.",
    stats: [
      { value: "24/7", label: "Uptime monitoring" },
      { value: "< 1hr", label: "Avg. critical response" },
      { value: "100%", label: "Update coverage" },
    ],
    features: [
      { title: "Proactive monitoring", description: "Issues caught and resolved before they affect customers.", icon: "ShieldCheck" },
      { title: "Regular updates", description: "Software, plugins and dependencies kept current and secure.", icon: "Workflow" },
      { title: "Priority support", description: "A direct line to the team that built your system.", icon: "LifeBuoy" },
      { title: "Continuous improvement", description: "Ongoing refinements based on real usage data.", icon: "Zap" },
    ],
    howWeWork: [
      { step: "Baseline", description: "Document the system as launched." },
      { step: "Monitor", description: "Track uptime, performance and errors continuously." },
      { step: "Maintain", description: "Apply updates and fixes on a regular cadence." },
      { step: "Improve", description: "Recommend upgrades as your business grows." },
    ],
    deliverables: ["Uptime & performance monitoring", "Scheduled updates & patching", "Priority issue response", "Monthly health reports"],
    technologies: ["Monitoring & alerting", "Automated backups", "CI/CD pipelines", "Version control"],
    faqs: [
      { q: "What's included in a maintenance plan?", a: "Monitoring, updates, backups, security patching and a support channel — scoped to your specific systems." },
      { q: "What if something breaks outside business hours?", a: "Critical issues are monitored around the clock, with response times defined in your support plan." },
    ],
    relatedServices: ["hosting-infrastructure", "security", "automation"],
  },
];

export function getServiceBySlug(slug: string) {
  return services.find((s) => s.slug === slug);
}

export const detailedServices = services.filter((s) => s.hasDetailPage);
