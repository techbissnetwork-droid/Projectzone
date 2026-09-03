export interface ServiceStep {
  title: string;
  description: string;
}

export interface Service {
  slug: string;
  index: string;
  name: string;
  shortName: string;
  tagline: string;
  description: string;
  benefits: string[];
  process: ServiceStep[];
  tech: string[];
  accent: string;
}

export const services: Service[] = [
  {
    slug: "website-development",
    index: "01",
    name: "Website Development",
    shortName: "Websites",
    tagline: "High-performance websites built around your business.",
    description:
      "We design and engineer fast, accessible, conversion-focused websites — from marketing sites to complex web platforms — built to represent your brand at the highest standard and scale with your business.",
    benefits: [
      "Custom design systems tailored to your brand",
      "Sub-second load times and excellent Core Web Vitals",
      "Content-managed, editable by your team",
      "SEO architecture built in from day one",
      "Fully responsive across every device",
    ],
    process: [
      { title: "Discovery", description: "We study your business, audience and goals." },
      { title: "Architecture", description: "Sitemap, content model and technical plan." },
      { title: "Design", description: "Interface design in your brand system." },
      { title: "Build", description: "Production engineering and integration." },
      { title: "Launch", description: "QA, performance tuning and deployment." },
    ],
    tech: ["Next.js", "React", "TypeScript", "Headless CMS", "Edge Hosting"],
    accent: "#5170ff",
  },
  {
    slug: "mobile-app-development",
    index: "02",
    name: "Mobile App Development",
    shortName: "Apps",
    tagline: "Android, iOS and cross-platform applications.",
    description:
      "We build native-feeling mobile applications that extend your business into the pocket of every customer — from booking and ordering apps to full-scale platforms with backend systems.",
    benefits: [
      "iOS, Android and cross-platform delivery",
      "Native performance and offline support",
      "Push notifications and engagement systems",
      "Secure authentication and payments",
      "App Store and Play Store launch management",
    ],
    process: [
      { title: "Product Definition", description: "Feature scope and user flows." },
      { title: "UI/UX Design", description: "Mobile-first interface design." },
      { title: "Engineering", description: "Native or cross-platform development." },
      { title: "Testing", description: "Device testing across real hardware." },
      { title: "Release", description: "Store submission and rollout." },
    ],
    tech: ["React Native", "Swift", "Kotlin", "Firebase", "REST/GraphQL"],
    accent: "#7c8cff",
  },
  {
    slug: "ecommerce",
    index: "03",
    name: "E-commerce",
    shortName: "E-commerce",
    tagline: "Turn your products into a fully online business.",
    description:
      "From product catalogs to checkout and fulfillment, we build e-commerce systems that convert — with inventory, payments and customer accounts fully connected under one platform.",
    benefits: [
      "Custom storefronts or headless commerce",
      "Secure, PCI-compliant payment checkout",
      "Inventory and order management",
      "Product discovery and merchandising",
      "Multi-currency and international selling",
    ],
    process: [
      { title: "Catalog Strategy", description: "Product structure and merchandising." },
      { title: "Storefront Design", description: "Shopping experience design." },
      { title: "Commerce Engine", description: "Checkout, tax and fulfillment logic." },
      { title: "Payments", description: "Gateway integration and compliance." },
      { title: "Launch & Grow", description: "Conversion optimization post-launch." },
    ],
    tech: ["Shopify", "Headless Commerce", "Stripe", "Inventory Systems"],
    accent: "#4fd1c5",
  },
  {
    slug: "business-digitization",
    index: "04",
    name: "Business Digitization",
    shortName: "Digitization",
    tagline: "Move offline operations into a connected digital system.",
    description:
      "We digitize the operational core of your business — records, scheduling, orders and customer management — replacing manual processes with connected digital systems.",
    benefits: [
      "Paperless records and workflows",
      "Centralized customer and order data",
      "Staff dashboards and internal tools",
      "Custom business management systems",
      "Integrations across your existing tools",
    ],
    process: [
      { title: "Operations Audit", description: "Map current manual processes." },
      { title: "System Design", description: "Design the digital equivalent." },
      { title: "Build", description: "Develop the internal systems." },
      { title: "Migration", description: "Move existing data safely." },
      { title: "Training", description: "Onboard your team to the new system." },
    ],
    tech: ["Custom Systems", "Cloud Database", "Admin Dashboards", "API Integrations"],
    accent: "#c9a463",
  },
  {
    slug: "hosting-infrastructure",
    index: "05",
    name: "Hosting & Infrastructure",
    shortName: "Hosting",
    tagline: "Reliable infrastructure your business can depend on.",
    description:
      "We provision and manage cloud infrastructure engineered for uptime, speed and scale — from single websites to multi-region systems handling serious traffic.",
    benefits: [
      "Global edge delivery for fast load times",
      "Automatic scaling under traffic spikes",
      "Daily backups and disaster recovery",
      "99.99% uptime architecture",
      "24/7 infrastructure monitoring",
    ],
    process: [
      { title: "Assessment", description: "Traffic, scale and compliance needs." },
      { title: "Architecture", description: "Design the infrastructure topology." },
      { title: "Provisioning", description: "Deploy cloud infrastructure." },
      { title: "Hardening", description: "Security and performance tuning." },
      { title: "Monitoring", description: "Ongoing observability and alerts." },
    ],
    tech: ["Cloud Edge Network", "CDN", "Auto-scaling", "Load Balancing"],
    accent: "#5eb3ff",
  },
  {
    slug: "ssl-security",
    index: "06",
    name: "SSL & Security",
    shortName: "Security",
    tagline: "Protection for your website and your customers.",
    description:
      "We secure every layer of your digital presence — encrypted connections, hardened infrastructure and ongoing monitoring — so your business and customers stay protected.",
    benefits: [
      "SSL/TLS encryption on every domain",
      "Web application firewall protection",
      "DDoS mitigation and threat monitoring",
      "Automated security patching",
      "Compliance-ready data handling",
    ],
    process: [
      { title: "Risk Review", description: "Audit current exposure." },
      { title: "Encryption", description: "SSL/TLS across all endpoints." },
      { title: "Hardening", description: "Firewall and access controls." },
      { title: "Monitoring", description: "Continuous threat detection." },
      { title: "Response", description: "Incident response readiness." },
    ],
    tech: ["TLS 1.3", "WAF", "DDoS Protection", "Security Monitoring"],
    accent: "#3ecf8e",
  },
  {
    slug: "business-email",
    index: "07",
    name: "Business Email",
    shortName: "Email",
    tagline: "Professional communication on your own domain.",
    description:
      "We set up and manage professional business email on your own domain — reliable delivery, generous storage and unified communication across your team.",
    benefits: [
      "Custom @yourbusiness.com addresses",
      "Reliable deliverability and spam protection",
      "Calendar, contacts and file storage",
      "Team collaboration tools",
      "Mobile and desktop sync",
    ],
    process: [
      { title: "Domain Setup", description: "DNS and domain verification." },
      { title: "Mailbox Provisioning", description: "Create team accounts." },
      { title: "Security", description: "SPF, DKIM and DMARC configuration." },
      { title: "Migration", description: "Move existing mail safely." },
      { title: "Support", description: "Ongoing account management." },
    ],
    tech: ["Business Mail", "SPF/DKIM/DMARC", "Cloud Storage", "Calendar Sync"],
    accent: "#f2b84b",
  },
  {
    slug: "automation",
    index: "08",
    name: "Automation",
    shortName: "Automation",
    tagline: "Reduce repetitive processes across your business.",
    description:
      "We connect your systems and automate the repetitive work between them — from customer follow-ups to internal notifications — freeing your team to focus on growth.",
    benefits: [
      "Automated customer communication",
      "Workflow triggers across systems",
      "Order and booking automation",
      "Internal notifications and reporting",
      "Third-party tool integrations",
    ],
    process: [
      { title: "Process Mapping", description: "Identify repetitive workflows." },
      { title: "Automation Design", description: "Design trigger-based flows." },
      { title: "Integration", description: "Connect your existing tools." },
      { title: "Testing", description: "Validate edge cases." },
      { title: "Optimization", description: "Refine based on usage data." },
    ],
    tech: ["Workflow Engine", "Webhooks", "API Integrations", "Scheduled Jobs"],
    accent: "#b98af0",
  },
  {
    slug: "payment-integration",
    index: "09",
    name: "Payment Integration",
    shortName: "Payments",
    tagline: "Accept and manage online transactions securely.",
    description:
      "We integrate secure, flexible payment systems into your website or app — supporting cards, wallets and local payment methods with full transaction visibility.",
    benefits: [
      "Card, wallet and bank payment support",
      "Subscription and recurring billing",
      "Multi-currency transaction handling",
      "Fraud detection and prevention",
      "Real-time payment analytics",
    ],
    process: [
      { title: "Payment Strategy", description: "Methods and currencies needed." },
      { title: "Gateway Setup", description: "Configure payment providers." },
      { title: "Integration", description: "Connect checkout and billing." },
      { title: "Compliance", description: "PCI-DSS aligned handling." },
      { title: "Reconciliation", description: "Reporting and payouts." },
    ],
    tech: ["Stripe", "PCI-DSS", "Recurring Billing", "Fraud Detection"],
    accent: "#ff8a65",
  },
  {
    slug: "maintenance-support",
    index: "10",
    name: "Maintenance & Support",
    shortName: "Maintenance",
    tagline: "Continuous improvement, long after launch.",
    description:
      "Your digital presence keeps evolving after launch. We provide ongoing maintenance, monitoring, optimization and support so your systems stay fast, secure and current.",
    benefits: [
      "Proactive monitoring and updates",
      "Performance and SEO optimization",
      "Priority technical support",
      "Feature iterations and improvements",
      "Monthly health and analytics reporting",
    ],
    process: [
      { title: "Baseline Audit", description: "Assess current health." },
      { title: "Support Plan", description: "Define SLAs and scope." },
      { title: "Monitoring", description: "Continuous uptime and error tracking." },
      { title: "Improvements", description: "Ongoing iteration cycles." },
      { title: "Reporting", description: "Transparent monthly reporting." },
    ],
    tech: ["Uptime Monitoring", "Error Tracking", "Analytics", "CI/CD"],
    accent: "#8f9bb3",
  },
];

export function getService(slug: string) {
  return services.find((s) => s.slug === slug);
}
