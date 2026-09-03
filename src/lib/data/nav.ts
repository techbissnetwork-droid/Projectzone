export type NavLink = { label: string; href: string; description?: string };

export const primaryNav: { label: string; href: string; columns?: NavLink[][] }[] = [
  {
    label: "Services",
    href: "/services",
    columns: [
      [
        { label: "Product Engineering", href: "/services#product-engineering", description: "Web, mobile & platform builds" },
        { label: "Cloud & DevOps", href: "/services#cloud-devops", description: "Infrastructure that scales" },
        { label: "AI & Automation", href: "/services#ai-automation", description: "Applied intelligence" },
      ],
      [
        { label: "Design Systems", href: "/services#design", description: "Interfaces that convert" },
        { label: "Data & Analytics", href: "/services#data", description: "Decisions backed by data" },
        { label: "Security", href: "/services#security", description: "Enterprise-grade protection" },
      ],
    ],
  },
  {
    label: "Solutions",
    href: "/solutions",
  },
  {
    label: "Marketplace",
    href: "/marketplace",
  },
  { label: "Work", href: "/work" },
  { label: "Process", href: "/process" },
  { label: "About", href: "/about" },
  { label: "Resources", href: "/resources" },
];

export const footerNav = {
  company: [
    { label: "About", href: "/about" },
    { label: "Work", href: "/work" },
    { label: "Process", href: "/process" },
    { label: "Resources", href: "/resources" },
    { label: "Contact", href: "/contact" },
  ],
  services: [
    { label: "Product Engineering", href: "/services#product-engineering" },
    { label: "Cloud & DevOps", href: "/services#cloud-devops" },
    { label: "AI & Automation", href: "/services#ai-automation" },
    { label: "Design Systems", href: "/services#design" },
  ],
  marketplace: [
    { label: "Browse Marketplace", href: "/marketplace" },
    { label: "Advanced Installer", href: "/installer" },
    { label: "Your Cart", href: "/marketplace/cart" },
  ],
  access: [
    { label: "Client Login", href: "/login/client" },
    { label: "Staff Login", href: "/login/staff" },
    { label: "Admin Login", href: "/login/admin" },
  ],
};
