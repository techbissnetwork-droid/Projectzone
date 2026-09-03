import type { Solution } from "@/lib/types";

export const solutions: Solution[] = [
  {
    slug: "enterprise",
    name: "Enterprise",
    audience: "Global & regional enterprises",
    description:
      "Modernize core systems, unify fragmented platforms and scale digital operations across regions without disrupting the business that's running today.",
    icon: "Building2",
    highlights: ["Legacy modernization", "Multi-region architecture", "Governance & compliance", "Change management"],
    stat: { value: "40%", label: "avg. reduction in operating cost" },
  },
  {
    slug: "startups",
    name: "Startups & Scale-ups",
    audience: "Seed to Series C",
    description:
      "Ship a fundable, investor-ready product fast — then scale the architecture as usage, headcount and funding grow, without a costly rebuild.",
    icon: "Rocket",
    highlights: ["MVP to production in weeks", "Fractional CTO guidance", "Scalable architecture", "Fundraising-ready metrics"],
    stat: { value: "6 wks", label: "avg. time to first release" },
  },
  {
    slug: "ecommerce",
    name: "E-commerce & Retail",
    audience: "DTC & omnichannel brands",
    description:
      "Composable commerce platforms engineered for conversion — from headless storefronts to inventory, fulfillment and personalization at scale.",
    icon: "ShoppingBag",
    highlights: ["Headless commerce", "Checkout optimization", "Personalization engines", "Omnichannel inventory"],
    stat: { value: "28%", label: "avg. conversion lift" },
  },
  {
    slug: "fintech",
    name: "Fintech & Financial Services",
    audience: "Banks, lenders & fintechs",
    description:
      "Compliant, secure and highly available financial infrastructure — payments, lending and banking products built to institutional standards.",
    icon: "Landmark",
    highlights: ["PCI-DSS & SOC 2 ready", "Real-time payments", "Fraud & risk systems", "Core banking integration"],
    stat: { value: "99.99%", label: "platform uptime SLA" },
  },
  {
    slug: "healthcare",
    name: "Healthcare & Life Sciences",
    audience: "Providers, payers & health tech",
    description:
      "HIPAA-ready patient and provider platforms that put clinical safety, interoperability and data privacy at the center of every decision.",
    icon: "HeartPulse",
    highlights: ["HIPAA & HL7/FHIR", "Patient engagement", "Clinical workflow tools", "Interoperability"],
    stat: { value: "500K+", label: "patient records securely managed" },
  },
  {
    slug: "saas",
    name: "SaaS Platforms",
    audience: "B2B & B2C software companies",
    description:
      "Multi-tenant SaaS architecture, billing, onboarding and analytics engineered for growth-stage retention and expansion revenue.",
    icon: "LayoutGrid",
    highlights: ["Multi-tenant architecture", "Usage-based billing", "Self-serve onboarding", "Product analytics"],
    stat: { value: "3.2x", label: "avg. net revenue retention gain" },
  },
];
