import type { CaseStudy } from "@/lib/types";

export const caseStudies: CaseStudy[] = [
  {
    slug: "meridian-bank-core-modernization",
    client: "Meridian Bank",
    industry: "Fintech",
    title: "Modernizing core banking for 4 million customers without a single hour of downtime",
    summary:
      "A phased migration from a 20-year-old core banking system to a cloud-native platform, executed live with zero customer-facing downtime.",
    challenge:
      "Meridian Bank's core banking platform was two decades old, running on infrastructure that made every new product launch take quarters instead of weeks. Regulatory pressure and rising customer expectations meant the bank needed to modernize — but any downtime or data risk was unacceptable for 4 million active accounts.",
    solution:
      "We designed a strangler-fig migration architecture that ran the legacy and new systems in parallel, routing transactions through a reconciliation layer. Over 14 months, we migrated account services, payments and lending modules incrementally, validating every batch against the legacy ledger before cutover.",
    results: [
      { value: "99.99%", label: "uptime throughout migration" },
      { value: "4M+", label: "accounts migrated safely" },
      { value: "6x", label: "faster product release cycle" },
      { value: "$18M", label: "annual infrastructure savings" },
    ],
    services: ["Cloud & DevOps", "Security", "Data & Analytics"],
    gradient: ["#0e1016", "#4b5bff"],
    year: "2025",
    quote: {
      text: "TECHBISS didn't just modernize our stack — they de-risked the single hardest transformation this bank has ever attempted.",
      author: "Elena Vasquez",
      role: "Chief Technology Officer",
    },
  },
  {
    slug: "northwind-retail-headless-commerce",
    client: "Northwind Retail",
    industry: "E-commerce",
    title: "A headless commerce rebuild that lifted conversion by 34% in one quarter",
    summary:
      "Rebuilding a legacy e-commerce monolith into a composable, headless storefront that scaled through three consecutive peak sales events.",
    challenge:
      "Northwind's monolithic storefront couldn't keep pace with marketing's campaign velocity, and page speed on mobile was costing them checkout conversions during every peak sales event.",
    solution:
      "We rebuilt the storefront on a composable, headless architecture — decoupling content, catalog and checkout — and rebuilt the front-end for sub-second load times with edge caching and optimistic UI throughout the funnel.",
    results: [
      { value: "34%", label: "increase in conversion rate" },
      { value: "1.2s", label: "median mobile load time" },
      { value: "3x", label: "peak traffic handled without incident" },
      { value: "22%", label: "lift in average order value" },
    ],
    services: ["Product Engineering", "Design Systems", "Cloud & DevOps"],
    gradient: ["#f43f5e", "#f59e0b"],
    year: "2025",
    quote: {
      text: "Every peak season used to be a fire drill. Now it's our best quarter, every quarter — with zero war room.",
      author: "Marcus Chen",
      role: "VP of E-commerce",
    },
  },
  {
    slug: "aurora-health-patient-platform",
    client: "Aurora Health Network",
    industry: "Healthcare",
    title: "A unified patient platform connecting 40 clinics and 900,000 patient records",
    summary:
      "Consolidating fragmented clinic systems into one HIPAA-compliant patient engagement platform with real-time scheduling and secure messaging.",
    challenge:
      "Aurora Health operated 40 clinics on disconnected scheduling and records systems, creating friction for patients and administrative overhead that pulled clinical staff away from care.",
    solution:
      "We built a unified patient engagement platform on HL7/FHIR standards, integrating scheduling, secure messaging and records access into a single HIPAA-compliant experience for patients and providers alike.",
    results: [
      { value: "40", label: "clinics unified on one platform" },
      { value: "900K+", label: "patient records consolidated" },
      { value: "62%", label: "reduction in no-show rate" },
      { value: "4.8/5", label: "patient satisfaction score" },
    ],
    services: ["Product Engineering", "Security", "AI & Automation"],
    gradient: ["#22c55e", "#0ea5e9"],
    year: "2024",
    quote: {
      text: "Our clinicians got their time back, and our patients finally have one place to manage their care.",
      author: "Dr. Priya Nair",
      role: "Chief Medical Information Officer",
    },
  },
  {
    slug: "voltage-saas-scale",
    client: "Voltage Analytics",
    industry: "SaaS",
    title: "Scaling a Series B analytics platform from 200 to 12,000 organizations",
    summary:
      "Re-architecting a single-tenant SaaS analytics product into a multi-tenant platform capable of onboarding thousands of organizations self-serve.",
    challenge:
      "Voltage's early architecture was single-tenant and required manual provisioning for every new customer — a model that couldn't survive their Series B growth targets.",
    solution:
      "We re-architected the platform for true multi-tenancy, built self-serve onboarding and usage-based billing, and introduced a product analytics layer to guide the growth team's expansion strategy.",
    results: [
      { value: "12,000+", label: "organizations onboarded" },
      { value: "3.2x", label: "net revenue retention" },
      { value: "90%", label: "of signups now self-serve" },
      { value: "8 wks", label: "time to multi-tenant launch" },
    ],
    services: ["Product Engineering", "Data & Analytics", "Cloud & DevOps"],
    gradient: ["#4b5bff", "#a855f7"],
    year: "2024",
    quote: {
      text: "We stopped losing weeks to manual customer setup and started compounding growth instead.",
      author: "Sam Ito",
      role: "Co-founder & CEO",
    },
  },
  {
    slug: "cascade-logistics-ai-routing",
    client: "Cascade Logistics",
    industry: "Enterprise",
    title: "AI-powered routing that cut delivery costs by 21% across a national fleet",
    summary:
      "An applied machine-learning routing engine that optimizes thousands of daily deliveries in real time across weather, traffic and driver constraints.",
    challenge:
      "Cascade's dispatch team was manually routing a national fleet using spreadsheets and static rules, leaving significant fuel and labor efficiency on the table as volume grew.",
    solution:
      "We built a real-time routing engine combining predictive demand modeling with constraint-based optimization, integrated directly into dispatchers' existing workflow tools to minimize change management.",
    results: [
      { value: "21%", label: "reduction in delivery cost" },
      { value: "17%", label: "fewer miles driven" },
      { value: "99.2%", label: "on-time delivery rate" },
      { value: "5 mo", label: "payback period" },
    ],
    services: ["AI & Automation", "Data & Analytics"],
    gradient: ["#f59e0b", "#84cc16"],
    year: "2023",
    quote: {
      text: "The routing engine paid for itself before the project was even fully rolled out.",
      author: "Tom Reyes",
      role: "VP of Operations",
    },
  },
  {
    slug: "lumen-media-design-system",
    client: "Lumen Media Group",
    industry: "Agency",
    title: "A single design system unifying nine publishing brands",
    summary:
      "Building a shared design system and component library that let nine distinct editorial brands ship consistently without losing individual identity.",
    challenge:
      "Lumen's nine editorial brands each ran on bespoke front-ends, multiplying engineering effort and creating inconsistent reader experiences across the portfolio.",
    solution:
      "We designed a themeable design system with a shared component library and per-brand tokens, letting each masthead retain its identity while engineering shipped features once, everywhere.",
    results: [
      { value: "9", label: "brands unified on one system" },
      { value: "70%", label: "faster feature rollout" },
      { value: "45%", label: "reduction in front-end code" },
      { value: "2.1x", label: "increase in reader engagement" },
    ],
    services: ["Design Systems", "Product Engineering"],
    gradient: ["#a855f7", "#ec4899"],
    year: "2023",
    quote: {
      text: "We finally ship features once instead of nine times — and our brands have never looked sharper.",
      author: "Grace Whitfield",
      role: "Head of Product",
    },
  },
];
