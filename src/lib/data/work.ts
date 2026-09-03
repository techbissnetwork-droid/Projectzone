export interface CaseStudy {
  slug: string;
  title: string;
  client: string;
  category: string;
  industry: string;
  year: string;
  summary: string;
  problem: string;
  solution: string;
  tech: string[];
  results: { stat: string; label: string }[];
  accent: string;
}

export const caseStudies: CaseStudy[] = [
  {
    slug: "aurora-retail",
    title: "Rebuilding Aurora Retail's entire online business",
    client: "Aurora Retail Co.",
    category: "E-commerce",
    industry: "Retail",
    year: "2025",
    summary:
      "A 12-store regional retailer moved from in-store-only sales to a unified e-commerce platform connecting inventory across every location.",
    problem:
      "Aurora Retail had 12 physical stores but no way to sell online. Inventory lived in separate spreadsheets per location, and the brand had no digital presence beyond a directory listing.",
    solution:
      "We built a headless commerce platform with real-time inventory sync across all 12 locations, a custom storefront, and integrated payments — allowing customers to buy online and pick up in-store.",
    tech: ["Next.js", "Headless Commerce", "Stripe", "Inventory API"],
    results: [
      { stat: "3.4×", label: "online revenue in 6 months" },
      { stat: "12", label: "store locations connected" },
      { stat: "1.1s", label: "average page load" },
    ],
    accent: "#4fd1c5",
  },
  {
    slug: "meridian-health",
    title: "A digital front door for Meridian Health Group",
    client: "Meridian Health Group",
    category: "Healthcare",
    industry: "Hospital",
    year: "2025",
    summary:
      "Meridian replaced phone-only appointment booking with a full patient portal, appointment system and secure billing.",
    problem:
      "Patients could only book appointments by calling during office hours. Records were paper-based and billing required in-person visits.",
    solution:
      "We designed and built a HIPAA-conscious patient portal with online appointment scheduling, digital records access and secure online payments, connected to Meridian's internal systems.",
    tech: ["Next.js", "Appointment Engine", "Secure Payments", "Patient Records API"],
    results: [
      { stat: "68%", label: "of bookings now online" },
      { stat: "-41%", label: "front-desk call volume" },
      { stat: "4.9/5", label: "patient satisfaction" },
    ],
    accent: "#3ecf8e",
  },
  {
    slug: "northfield-academy",
    title: "Northfield Academy's admissions transformation",
    client: "Northfield Academy",
    category: "Education",
    industry: "School",
    year: "2024",
    summary:
      "A growing private school needed a modern admissions experience and a portal parents and students could actually use.",
    problem:
      "Admissions ran entirely on paper forms and email. Parents had no visibility into fees, schedules or communication from staff.",
    solution:
      "We built a full admissions website, an online application system, and a parent/student portal with fee payments, schedules and messaging — replacing years of manual paperwork.",
    tech: ["Next.js", "Student Portal", "Payment Gateway", "Notification System"],
    results: [
      { stat: "5×", label: "faster admissions processing" },
      { stat: "92%", label: "of fees now paid online" },
      { stat: "3 wks", label: "time to launch" },
    ],
    accent: "#5170ff",
  },
  {
    slug: "havenwood-hotels",
    title: "A direct booking engine for Havenwood Hotels",
    client: "Havenwood Hotels",
    category: "Hospitality",
    industry: "Hotel",
    year: "2024",
    summary:
      "An independent hotel group reduced OTA dependency by building a fast, beautiful direct booking experience.",
    problem:
      "Over 70% of bookings came through third-party platforms taking significant commission. The brand's own website felt outdated and rarely converted.",
    solution:
      "We rebuilt the brand's website and connected a modern booking engine with real-time availability, channel sync, and a guest management system tied to direct payments.",
    tech: ["Next.js", "Booking Engine", "Channel Manager", "Payments"],
    results: [
      { stat: "+58%", label: "direct bookings" },
      { stat: "-22%", label: "OTA commission spend" },
      { stat: "2.1×", label: "conversion rate" },
    ],
    accent: "#c9a463",
  },
  {
    slug: "forge-construction",
    title: "Forge Construction's client project portal",
    client: "Forge Construction Group",
    category: "Construction",
    industry: "Construction",
    year: "2024",
    summary:
      "A commercial construction firm needed a professional portfolio site and a portal for clients to track live projects.",
    problem:
      "Project updates were sent manually through email chains, and the company's online presence didn't reflect the scale of their work.",
    solution:
      "We designed an editorial portfolio site showcasing completed projects, paired with a client portal for live project tracking, document sharing and milestone approvals.",
    tech: ["Next.js", "Client Portal", "Document Management", "Notifications"],
    results: [
      { stat: "+140%", label: "qualified inbound leads" },
      { stat: "-60%", label: "status-update emails" },
      { stat: "18", label: "active projects tracked live" },
    ],
    accent: "#8f9bb3",
  },
  {
    slug: "lumen-fitness",
    title: "Lumen Fitness's membership and booking platform",
    client: "Lumen Fitness Studios",
    category: "Booking Platform",
    industry: "Service Business",
    year: "2023",
    summary:
      "A boutique fitness studio chain replaced spreadsheet scheduling with automated class booking and membership billing.",
    problem:
      "Class bookings were tracked in spreadsheets, no-shows were common, and membership billing was handled manually each month.",
    solution:
      "We built a class booking and membership platform with automated reminders, recurring billing, and a studio-facing dashboard for capacity management.",
    tech: ["Next.js", "Booking System", "Recurring Billing", "SMS Automation"],
    results: [
      { stat: "-35%", label: "class no-shows" },
      { stat: "100%", label: "membership billing automated" },
      { stat: "4 studios", label: "unified on one system" },
    ],
    accent: "#b98af0",
  },
];

export function getCaseStudy(slug: string) {
  return caseStudies.find((c) => c.slug === slug);
}
