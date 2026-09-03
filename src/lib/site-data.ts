// Shared factual content for TECHBISS across all three design concepts.
// Every concept renders this same information in its own visual language —
// keep copy here concept-neutral; concept-specific hero/tone copy lives in
// each concept's own page files.

export const company = {
  name: "TECHBISS",
  legalTagline: "Technology & Business Digitization",
  description:
    "TECHBISS helps businesses move from offline to online — building premium websites, applications, and complete digital infrastructure for companies ready to scale.",
  email: "team@techbiss.com",
  phone: "[Add phone number]",
  address: "[Add office address]",
};

export type NavItem = { label: string; href: string };

export const primaryNav: NavItem[] = [
  { label: "Home", href: "/" },
  { label: "About", href: "/about" },
  { label: "Services", href: "/services" },
  { label: "Portfolio", href: "/portfolio" },
  { label: "Pricing", href: "/pricing" },
  { label: "Process", href: "/process" },
  { label: "Technology", href: "/technology" },
  { label: "Contact", href: "/contact" },
];

export type Service = {
  slug: string;
  title: string;
  shortDescription: string;
  longDescription: string;
  features: string[];
  deliverables: string[];
  icon: string; // lucide-react icon name
  hasDetailPage: boolean;
};

export const services: Service[] = [
  {
    slug: "website-development",
    title: "Premium Website Development",
    shortDescription:
      "Custom-designed, high-performance websites built to represent your brand at an enterprise level.",
    longDescription:
      "We design and build fully custom websites — not templates — engineered for speed, clarity, and conversion. Every project starts with your business goals, not a theme picker.",
    features: [
      "Custom UI/UX design, no page-builder templates",
      "Performance-first engineering (Core Web Vitals, sub-second loads)",
      "SEO-ready structure and semantic markup",
      "CMS integration for teams who need to self-edit content",
      "Cross-browser and cross-device quality assurance",
    ],
    deliverables: [
      "Fully responsive multi-page website",
      "Design system and component library",
      "Analytics and search console setup",
      "Editor training and documentation",
    ],
    icon: "Globe",
    hasDetailPage: true,
  },
  {
    slug: "web-application-development",
    title: "Web Application Development",
    shortDescription:
      "Custom web platforms, dashboards, and internal tools engineered to run your operations.",
    longDescription:
      "From customer portals to internal dashboards, we build secure, scalable web applications with modern frameworks — designed around how your business actually works.",
    features: [
      "Custom dashboards and admin panels",
      "Authentication, roles, and permissions",
      "API design and third-party integrations",
      "Database architecture and data modelling",
      "Scalable cloud-native infrastructure",
    ],
    deliverables: [
      "Production web application",
      "API documentation",
      "Deployment pipeline",
      "Ongoing maintenance plan",
    ],
    icon: "LayoutDashboard",
    hasDetailPage: true,
  },
  {
    slug: "mobile-app-development",
    title: "Mobile App Development",
    shortDescription:
      "Native-quality iOS and Android apps that extend your business into your customers' pockets.",
    longDescription:
      "We design and develop mobile applications with cross-platform frameworks for consistent quality on iOS and Android, backed by the same infrastructure as your web platform.",
    features: [
      "iOS and Android from a single codebase",
      "Push notifications and offline support",
      "Secure payments and account management",
      "App Store and Play Store submission support",
      "Analytics and crash monitoring",
    ],
    deliverables: [
      "Published mobile application",
      "App store listing assets",
      "Backend API integration",
      "Release and update workflow",
    ],
    icon: "Smartphone",
    hasDetailPage: true,
  },
  {
    slug: "business-digitization",
    title: "Business Digitization",
    shortDescription:
      "A complete roadmap and execution plan to move your entire operation from offline to online.",
    longDescription:
      "For businesses starting from zero online presence, we plan and execute full digitization — website, systems, workflows, and digital tools — as one coordinated project.",
    features: [
      "Digital readiness audit",
      "Workflow and process digitization",
      "Digital tools and software recommendations",
      "Staff onboarding and training",
      "Phased rollout with minimal disruption",
    ],
    deliverables: [
      "Digitization roadmap",
      "Core digital infrastructure setup",
      "Staff training sessions",
      "90-day post-launch support plan",
    ],
    icon: "Building2",
    hasDetailPage: true,
  },
  {
    slug: "domain-hosting",
    title: "Domain Registration & Premium Hosting",
    shortDescription:
      "Secure your business identity online and host it on fast, reliable infrastructure.",
    longDescription:
      "We handle domain registration, DNS configuration, and premium hosting setup so your business runs on stable, fast, and properly secured infrastructure from day one.",
    features: [
      "Domain search, registration, and DNS management",
      "Premium hosting on high-availability infrastructure",
      "CDN and caching configuration",
      "Automated backups",
      "Uptime monitoring",
    ],
    deliverables: [
      "Registered domain and DNS setup",
      "Configured hosting environment",
      "Backup and monitoring setup",
      "Handover documentation",
    ],
    icon: "Server",
    hasDetailPage: true,
  },
  {
    slug: "ssl-security",
    title: "SSL & Website Security",
    shortDescription:
      "Enterprise-grade security to protect your business, your data, and your customers.",
    longDescription:
      "We implement SSL certificates, security headers, monitoring, and hardening best practices so your digital presence meets the standard your customers expect.",
    features: [
      "SSL/TLS certificate setup and renewal management",
      "Security headers and hardening",
      "Malware and vulnerability monitoring",
      "Firewall and access control configuration",
      "Incident response guidance",
    ],
    deliverables: [
      "Active SSL certificate",
      "Security configuration report",
      "Monitoring dashboard access",
      "Response playbook",
    ],
    icon: "ShieldCheck",
    hasDetailPage: true,
  },
  {
    slug: "business-email",
    title: "Professional Business Email",
    shortDescription:
      "Custom @yourbusiness.com email addresses that build instant trust with customers.",
    longDescription:
      "We set up professional email on your own domain with the security, storage, and collaboration tools your team needs — configured correctly the first time.",
    features: [
      "Custom domain email addresses",
      "Spam and phishing protection",
      "Shared calendars and collaboration tools",
      "Mobile and desktop mail client setup",
      "Team onboarding and migration",
    ],
    deliverables: [
      "Configured business email accounts",
      "Migration of existing mail (if applicable)",
      "Security policy setup",
      "Team quick-start guide",
    ],
    icon: "Mail",
    hasDetailPage: true,
  },
  {
    slug: "custom-solutions",
    title: "Custom Digital Solutions",
    shortDescription:
      "When your business needs something no off-the-shelf product can deliver.",
    longDescription:
      "Some problems don't fit a standard package. We scope, design, and build bespoke digital solutions — from automation tools to integrations between systems you already use.",
    features: [
      "Custom software scoping and architecture",
      "Systems integration and automation",
      "Legacy system modernization",
      "Proof-of-concept and MVP development",
    ],
    deliverables: [
      "Custom project scope and architecture",
      "Working solution tailored to your workflow",
      "Documentation and training",
    ],
    icon: "Sparkles",
    hasDetailPage: false,
  },
  {
    slug: "technical-support",
    title: "Ongoing Technical Support",
    shortDescription:
      "A technology partner that stays with you after launch, not just until invoice.",
    longDescription:
      "Digital infrastructure needs upkeep. Our support plans cover monitoring, updates, fixes, and improvements so your systems keep running as your business grows.",
    features: [
      "Proactive monitoring and maintenance",
      "Priority bug fixes and updates",
      "Monthly performance and security reports",
      "Flexible support tiers for any business size",
    ],
    deliverables: [
      "Ongoing support agreement",
      "Monthly reporting",
      "Priority response times",
    ],
    icon: "LifeBuoy",
    hasDetailPage: false,
  },
];

export const serviceBySlug = (slug: string) =>
  services.find((s) => s.slug === slug);

export type ProcessStep = {
  step: string;
  title: string;
  description: string;
};

export const processSteps: ProcessStep[] = [
  {
    step: "01",
    title: "Discovery & Strategy",
    description:
      "We learn your business, your customers, and your goals, then define what 'success' looks like before a single pixel is designed.",
  },
  {
    step: "02",
    title: "Design",
    description:
      "We design a complete visual system and user experience tailored to your brand — reviewed and refined with you at every stage.",
  },
  {
    step: "03",
    title: "Development",
    description:
      "Our engineers build on modern, scalable architecture with clean code, security, and performance built in from the start.",
  },
  {
    step: "04",
    title: "Testing & Quality Assurance",
    description:
      "Every build is tested across devices, browsers, and real-world conditions before it ever reaches your customers.",
  },
  {
    step: "05",
    title: "Launch",
    description:
      "We handle deployment, domain, hosting, and go-live coordination so launch day is smooth and stress-free.",
  },
  {
    step: "06",
    title: "Support & Growth",
    description:
      "Post-launch, we monitor, maintain, and help you evolve your platform as your business grows.",
  },
];

export type TechCategory = {
  category: string;
  items: string[];
};

export const techCapabilities: TechCategory[] = [
  {
    category: "Frontend Engineering",
    items: ["React", "Next.js", "TypeScript", "Tailwind CSS", "Motion & Interaction Design"],
  },
  {
    category: "Backend & APIs",
    items: ["Node.js", "REST & GraphQL APIs", "PostgreSQL", "Redis", "Serverless Functions"],
  },
  {
    category: "Mobile",
    items: ["React Native", "iOS", "Android", "Push Notifications", "App Store Deployment"],
  },
  {
    category: "Cloud & Infrastructure",
    items: ["Scalable Hosting", "CDN & Edge Caching", "CI/CD Pipelines", "Automated Backups"],
  },
  {
    category: "Security",
    items: ["SSL/TLS", "Security Headers", "Access Control", "Monitoring & Alerts"],
  },
  {
    category: "Business Systems",
    items: ["Business Email", "Domain Management", "CRM Integration", "Analytics & Reporting"],
  },
];

export type PricingTier = {
  name: string;
  audience: string;
  description: string;
  features: string[];
  highlighted?: boolean;
};

export const pricingTiers: PricingTier[] = [
  {
    name: "Launch",
    audience: "For new businesses",
    description:
      "Everything a growing business needs to establish a credible, complete online presence.",
    features: [
      "Custom premium website (up to 6 pages)",
      "Domain registration & premium hosting",
      "SSL security setup",
      "1 professional business email",
      "30-day post-launch support",
    ],
  },
  {
    name: "Growth",
    audience: "For scaling businesses",
    description:
      "A complete digital foundation with web application capability and ongoing support.",
    features: [
      "Everything in Launch",
      "Custom web application module",
      "Advanced SEO & analytics setup",
      "5 professional business emails",
      "90-day priority support",
    ],
    highlighted: true,
  },
  {
    name: "Enterprise",
    audience: "For established organizations",
    description:
      "Full-scale digital transformation across web, mobile, and internal systems.",
    features: [
      "Website + web application + mobile app",
      "Full business digitization roadmap",
      "Dedicated security & monitoring",
      "Unlimited business emails",
      "Ongoing technical support plan",
    ],
  },
];

export const pricingNote =
  "Every business is different. These tiers illustrate typical scope — your final package is scoped and quoted after a discovery call.";

export type CaseStudy = {
  slug: string;
  industry: string;
  title: string;
  summary: string;
  services: string[];
  outcome: string;
};

export const caseStudies: CaseStudy[] = [
  {
    slug: "retail-digital-relaunch",
    industry: "Retail",
    title: "Retail Brand Digital Relaunch",
    summary:
      "A multi-location retail business moved from a paper-based catalog to a full e-commerce-ready website with secure hosting and business email.",
    services: ["Website Development", "Domain & Hosting", "Business Email"],
    outcome:
      "Illustrative example — replace with a verified client outcome and metrics.",
  },
  {
    slug: "healthcare-booking-platform",
    industry: "Healthcare",
    title: "Healthcare Provider Booking Platform",
    summary:
      "A clinic group needed a secure booking system for patients across multiple locations, integrated with their existing records workflow.",
    services: ["Web Application Development", "SSL & Security"],
    outcome:
      "Illustrative example — replace with a verified client outcome and metrics.",
  },
  {
    slug: "logistics-operations-dashboard",
    industry: "Logistics",
    title: "Logistics Operations Dashboard",
    summary:
      "An internal dashboard replacing spreadsheet-based tracking with real-time visibility across a logistics company's fleet operations.",
    services: ["Web Application Development", "Business Digitization"],
    outcome:
      "Illustrative example — replace with a verified client outcome and metrics.",
  },
  {
    slug: "hospitality-mobile-experience",
    industry: "Hospitality",
    title: "Hospitality Mobile Experience",
    summary:
      "A hospitality group launched a guest-facing mobile app for bookings, loyalty, and concierge requests across their properties.",
    services: ["Mobile App Development", "Web Application Development"],
    outcome:
      "Illustrative example — replace with a verified client outcome and metrics.",
  },
];

export const trustStats = [
  { label: "Service Categories", value: "10+" },
  { label: "Technology Stack Areas", value: "6" },
  { label: "Support Availability", value: "Ongoing" },
  { label: "Delivery Approach", value: "End-to-End" },
];

export type Faq = { question: string; answer: string };

export const faqs: Faq[] = [
  {
    question: "We have zero online presence today. Where do we start?",
    answer:
      "That's exactly what Business Digitization is for. We start with a discovery audit, then build a roadmap covering your website, domain, hosting, email, and any systems you need — rolled out in manageable phases.",
  },
  {
    question: "How long does a typical website project take?",
    answer:
      "Most premium websites take 4–8 weeks from discovery to launch, depending on scope. Web and mobile applications typically take longer and are scoped individually.",
  },
  {
    question: "Do you offer support after launch?",
    answer:
      "Yes. Every project includes a post-launch support window, and we offer ongoing technical support plans for ongoing monitoring, updates, and improvements.",
  },
  {
    question: "Can you migrate our existing website or systems?",
    answer:
      "Yes, we assess your current setup and plan a migration path that minimizes downtime and preserves your existing data, SEO value, and workflows.",
  },
  {
    question: "Is our data and infrastructure secure?",
    answer:
      "Security is built into every project — SSL, hardened configurations, access controls, and monitoring are standard, not optional add-ons.",
  },
  {
    question: "Do you work with businesses outside a specific industry?",
    answer:
      "Yes. We work across retail, healthcare, logistics, hospitality, professional services, and more — the digitization principles are the same, the execution is tailored to you.",
  },
];

export const concepts = [
  {
    slug: "concept-1",
    name: "Future Luxury",
    tagline: "A cinematic, futuristic enterprise-technology aesthetic.",
  },
  {
    slug: "concept-2",
    name: "Ultra-Minimal Luxury",
    tagline: "Editorial precision — expensive because of restraint.",
  },
  {
    slug: "concept-3",
    name: "Digital Experience",
    tagline: "An interactive, dashboard-inspired digital product feel.",
  },
] as const;
