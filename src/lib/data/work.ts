export type CaseStudy = {
  slug: string;
  client: string;
  industry: string;
  year: string;
  accent: string;
  summary: string;
  problem: string;
  strategy: string;
  solution: string;
  technology: string[];
  result: string[];
};

export const caseStudies: CaseStudy[] = [
  {
    slug: "himalayan-kitchen",
    client: "Himalayan Kitchen",
    industry: "Restaurant",
    year: "2025",
    accent: "#C8A165",
    summary:
      "A family restaurant moved from phone-only orders to a full digital ordering and reservation system in six weeks.",
    problem:
      "Himalayan Kitchen took every order by phone, had no online visibility, and turned away customers during peak hours simply because the line was busy.",
    strategy:
      "Launch fast with a ready-made restaurant product, customized to the brand, then extend with delivery zone logic specific to their neighborhood.",
    solution:
      "TECHBISS deployed Restaurant Pro from the marketplace, rebuilt the brand system around the client's identity, and integrated online ordering, reservations and payments.",
    technology: ["Next.js", "Stripe", "TECHBISS Hosting", "Business Email"],
    result: [
      "38% of weekly orders now placed online",
      "Reservation no-shows down 24%",
      "Live in under 6 weeks from kickoff",
    ],
  },
  {
    slug: "northfield-realty",
    client: "Northfield Realty",
    industry: "Real Estate",
    year: "2025",
    accent: "#4A72F2",
    summary:
      "A regional real estate agency replaced PDF listing sheets with a searchable property platform and structured lead capture.",
    problem:
      "Listings were shared as PDFs over messaging apps. Agents had no way to track which properties generated real interest.",
    strategy:
      "Build a custom property platform with structured data from day one, so search, filtering and lead scoring could scale with the catalog.",
    solution:
      "A custom-built platform on the Meridian Realty foundation, extended with a CRM integration and agent-specific lead routing.",
    technology: ["Next.js", "PostgreSQL", "Pulse CRM integration"],
    result: [
      "3.2x increase in qualified inbound leads",
      "Average agent response time cut from 2 days to 4 hours",
      "Full catalog of 400+ listings migrated with zero downtime",
    ],
  },
  {
    slug: "brightside-academy",
    client: "Brightside Academy",
    industry: "Education",
    year: "2024",
    accent: "#34C77B",
    summary:
      "A growing private school digitized admissions and parent communication ahead of a 40% enrollment increase.",
    problem:
      "Admissions ran entirely on paper forms, and parents had no way to check fee status or school updates without calling the office.",
    strategy:
      "Combine a marketplace school theme with a custom parent portal and payment integration to handle the coming enrollment growth.",
    solution:
      "Bright Academy theme customized and brand-integrated, connected to a custom parent portal, online admissions and fee payments.",
    technology: ["Next.js", "TypeScript", "Payments", "Business Email"],
    result: [
      "Admissions processing time down 60%",
      "92% of parents active on the portal within one term",
      "Zero missed fee payment reminders since launch",
    ],
  },
];

export function getCaseStudy(slug: string) {
  return caseStudies.find((c) => c.slug === slug);
}
