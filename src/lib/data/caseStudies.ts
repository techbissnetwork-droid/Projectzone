export type VisualTheme = "grid" | "flow" | "pulse" | "orbit" | "waves" | "terminal";

export interface CaseStudyResult {
  value: string;
  label: string;
}

export interface CaseStudy {
  slug: string;
  brand: string;
  businessType: string;
  year: string;
  color: "gold" | "signal";
  visualTheme: VisualTheme;
  tagline: string;
  problem: string;
  solution: string;
  results: CaseStudyResult[];
  technologies: string[];
  services: string[];
  quote: { text: string; author: string; role: string };
}

export const caseStudies: CaseStudy[] = [
  {
    slug: "marlowe-and-finch",
    brand: "Marlowe & Finch",
    businessType: "Boutique Retail",
    year: "2025",
    color: "gold",
    visualTheme: "grid",
    tagline: "Three physical stores. One platform that outsells all of them combined.",
    problem:
      "A boutique fashion retailer with three physical locations had no online presence beyond social media. Inventory lived in three disconnected spreadsheets, and every sale outside store hours was a sale lost.",
    solution:
      "We built a full e-commerce platform on a headless architecture, unified inventory across all three stores in real time, and layered in automated email flows for restocks and abandoned carts — turning a social following into a sellable audience.",
    results: [
      { value: "3.4×", label: "revenue growth in 12 months" },
      { value: "41%", label: "of sales now online" },
      { value: "1.1s", label: "median page load time" },
    ],
    technologies: ["Next.js", "Stripe", "Headless commerce", "Klaviyo automation"],
    services: ["e-commerce", "payment-integration", "automation"],
    quote: {
      text: "We didn't just get a website. We got an entire second storefront that never closes — and it now outsells our flagship location.",
      author: "Store Director",
      role: "Marlowe & Finch",
    },
  },
  {
    slug: "harbor-and-co",
    brand: "Harbor & Co.",
    businessType: "Restaurant Group",
    year: "2025",
    color: "signal",
    visualTheme: "flow",
    tagline: "A five-location kitchen that used to run on a landline now runs on data.",
    problem:
      "A five-location restaurant group was losing an estimated 15% of phone orders nightly to busy lines and mis-heard tickets, with zero visibility into which locations or dishes actually drove revenue.",
    solution:
      "We designed and built a branded ordering website and mobile app connected directly to kitchen display systems at every location, with live menu management and integrated delivery coordination — replacing the phone entirely for online orders.",
    results: [
      { value: "+41%", label: "order volume in 90 days" },
      { value: "0", label: "mis-heard orders" },
      { value: "5", label: "locations, one system" },
    ],
    technologies: ["React Native", "Node.js", "Stripe", "Real-time order routing"],
    services: ["mobile-app-development", "website-development", "payment-integration"],
    quote: {
      text: "Our kitchens finally have the same information as our customers. Orders are accurate, and for the first time we can see exactly what's working.",
      author: "Operations Lead",
      role: "Harbor & Co.",
    },
  },
  {
    slug: "meridian-health",
    brand: "Meridian Health",
    businessType: "Multi-Clinic Healthcare Group",
    year: "2024",
    color: "gold",
    visualTheme: "pulse",
    tagline: "From a waiting room that overflowed to a schedule that runs itself.",
    problem:
      "A multi-location clinic group booked every appointment by phone, resulting in frequent double-bookings, a high no-show rate, and patient records split across paper charts and disconnected systems.",
    solution:
      "We built a unified booking and patient management system with self-service scheduling, automated SMS and email reminders, and a centralized dashboard giving staff one accurate view of every location's calendar and records.",
    results: [
      { value: "-52%", label: "missed appointments" },
      { value: "68%", label: "of bookings now self-service" },
      { value: "4", label: "clinics unified on one system" },
    ],
    technologies: ["Custom web application", "PostgreSQL", "Automated SMS/email", "Role-based dashboards"],
    services: ["business-digitization", "automation", "mobile-app-development"],
    quote: {
      text: "Patients book themselves in under a minute now, and our front desk finally has time to focus on the people in front of them instead of the phone.",
      author: "Practice Manager",
      role: "Meridian Health",
    },
  },
  {
    slug: "northfield-academy",
    brand: "Northfield Academy",
    businessType: "Private School Network",
    year: "2024",
    color: "signal",
    visualTheme: "orbit",
    tagline: "Admissions, attendance and parent communication, unified for three campuses.",
    problem:
      "A growing school network managed admissions on paper forms, communicated with parents through inconsistent channels, and had no digital record connecting attendance, grades and fees across its three campuses.",
    solution:
      "We digitized the full academic operation into a single education portal — online admissions, a parent communication hub, digital attendance and gradebooks, and integrated fee payments — accessible to staff, teachers and families alike.",
    results: [
      { value: "-73%", label: "admin processing time" },
      { value: "3", label: "campuses on one portal" },
      { value: "92%", label: "parent adoption in term one" },
    ],
    technologies: ["Custom web application", "Role-based access", "Payment integration", "Notification system"],
    services: ["business-digitization", "website-development", "payment-integration"],
    quote: {
      text: "Parents finally have one place to check everything, and our staff got hundreds of hours a term back from paperwork.",
      author: "Head of Admissions",
      role: "Northfield Academy",
    },
  },
  {
    slug: "vantage-legal",
    brand: "Vantage Legal",
    businessType: "Professional Services Firm",
    year: "2025",
    color: "gold",
    visualTheme: "waves",
    tagline: "A firm built on referrals now fills its calendar without a single phone call.",
    problem:
      "A professional services firm relied entirely on referrals and phone scheduling, with no way for prospective clients to see availability or book a consultation outside office hours.",
    solution:
      "We built a premium brand website with live consultation booking, automated intake questionnaires and quote generation, and reminder workflows — giving the firm a credible digital presence that converts visitors without manual back-and-forth.",
    results: [
      { value: "+58%", label: "booked consultations" },
      { value: "24/7", label: "self-service booking" },
      { value: "2.1×", label: "inbound leads within 6 months" },
    ],
    technologies: ["Next.js", "Automated scheduling", "Document workflows", "CRM integration"],
    services: ["website-development", "automation", "custom-web-applications"],
    quote: {
      text: "Clients now book consultations while we sleep. It changed how prospects perceive the firm before they ever speak to us.",
      author: "Managing Partner",
      role: "Vantage Legal",
    },
  },
  {
    slug: "loop",
    brand: "Loop",
    businessType: "Fintech Startup",
    year: "2026",
    color: "signal",
    visualTheme: "terminal",
    tagline: "From a pitch deck to a funded product in eight weeks.",
    problem:
      "A pre-seed fintech startup had validated demand and secured initial funding, but no technical team and no product — with investor expectations for a working demo on a tight runway.",
    solution:
      "We took the product from architecture to launch: designing the core user experience, building a full-stack application on scalable cloud infrastructure, and integrating secure payments — delivering a launch-ready MVP investors and early users could actually use.",
    results: [
      { value: "8 wks", label: "idea to launch-ready MVP" },
      { value: "99.9%", label: "infrastructure uptime" },
      { value: "$2.4M", label: "seed round closed post-launch" },
    ],
    technologies: ["Next.js", "Node.js", "Stripe", "AWS infrastructure"],
    services: ["custom-web-applications", "hosting-infrastructure", "payment-integration"],
    quote: {
      text: "TECHBISS built our entire technical foundation. We walked into our seed round with a real product, not a prototype.",
      author: "Founder & CEO",
      role: "Loop",
    },
  },
];

export function getCaseStudyBySlug(slug: string) {
  return caseStudies.find((c) => c.slug === slug);
}
