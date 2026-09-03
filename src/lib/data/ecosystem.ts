export interface EcosystemNode {
  key: string;
  name: string;
  description: string;
  tech: string[];
  href: string;
  accent: string;
}

export const ecosystemNodes: EcosystemNode[] = [
  {
    key: "websites",
    name: "Websites",
    description: "High-performance websites built around the business.",
    tech: ["Next.js", "CMS", "SEO"],
    href: "/services/website-development",
    accent: "#5170ff",
  },
  {
    key: "apps",
    name: "Apps",
    description: "Android, iOS and custom applications.",
    tech: ["iOS", "Android", "Cross-platform"],
    href: "/services/mobile-app-development",
    accent: "#7c8cff",
  },
  {
    key: "domains",
    name: "Domains",
    description: "Digital identity and domain registration.",
    tech: ["DNS", "WHOIS", "Registration"],
    href: "/dashboard/domains",
    accent: "#5eb3ff",
  },
  {
    key: "hosting",
    name: "Hosting",
    description: "Reliable, scalable cloud infrastructure.",
    tech: ["Edge CDN", "Auto-scale", "99.99% Uptime"],
    href: "/services/hosting-infrastructure",
    accent: "#4fd1c5",
  },
  {
    key: "ssl",
    name: "SSL & Security",
    description: "Protection for websites and customers.",
    tech: ["TLS 1.3", "WAF", "DDoS Protection"],
    href: "/services/ssl-security",
    accent: "#3ecf8e",
  },
  {
    key: "email",
    name: "Business Email",
    description: "Professional communication on your domain.",
    tech: ["SPF/DKIM", "Cloud Mail", "Sync"],
    href: "/services/business-email",
    accent: "#f2b84b",
  },
  {
    key: "ecommerce",
    name: "E-commerce",
    description: "Turn products into an online business.",
    tech: ["Storefront", "Checkout", "Inventory"],
    href: "/services/ecommerce",
    accent: "#c9a463",
  },
  {
    key: "automation",
    name: "Automation",
    description: "Reduce repetitive processes across your business.",
    tech: ["Workflows", "Webhooks", "Integrations"],
    href: "/services/automation",
    accent: "#b98af0",
  },
  {
    key: "payments",
    name: "Payments",
    description: "Accept and manage online transactions.",
    tech: ["Cards", "Wallets", "Recurring Billing"],
    href: "/services/payment-integration",
    accent: "#ff8a65",
  },
  {
    key: "marketplace",
    name: "Marketplace",
    description: "Buy professionally built themes and digital products.",
    tech: ["Themes", "UI Kits", "App Templates"],
    href: "/marketplace",
    accent: "#e07be0",
  },
];
