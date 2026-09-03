export const site = {
  name: "TECHBISS",
  legalName: "Techbiss",
  tagline: "Your Business. Built for the Digital World.",
  description:
    "TECHBISS is a premium digital transformation partner. We design, build, launch and grow the complete online presence of your business — websites, apps, hosting, security, email, payments and automation.",
  url: "https://www.techbiss.com",
  email: "hello@techbiss.com",
  salesEmail: "start@techbiss.com",
  phone: "+1 (628) 555-0192",
  address: "One Market Street, San Francisco, CA",
  social: {
    linkedin: "https://linkedin.com/company/techbiss",
    instagram: "https://instagram.com/techbiss",
    x: "https://x.com/techbiss",
    dribbble: "https://dribbble.com/techbiss",
  },
  founded: 2018,
} as const;

export const primaryNav = [
  { label: "Services", href: "/services" },
  { label: "Solutions", href: "/solutions" },
  { label: "Process", href: "/process" },
  { label: "Work", href: "/work" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
] as const;

export const footerNav = {
  company: [
    { label: "About", href: "/about" },
    { label: "Process", href: "/process" },
    { label: "Work", href: "/work" },
    { label: "Contact", href: "/contact" },
  ],
  services: [
    { label: "Website Development", href: "/services/website-development" },
    { label: "Mobile Apps", href: "/services/mobile-app-development" },
    { label: "E-commerce", href: "/services/e-commerce" },
    { label: "Business Digitization", href: "/services/business-digitization" },
  ],
  infrastructure: [
    { label: "Hosting & Infrastructure", href: "/services/hosting-infrastructure" },
    { label: "Security & SSL", href: "/services/security" },
    { label: "Business Email", href: "/services/business-email" },
    { label: "Payment Integration", href: "/services/payment-integration" },
  ],
} as const;
