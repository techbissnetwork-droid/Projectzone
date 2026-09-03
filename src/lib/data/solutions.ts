export interface Solution {
  slug: string;
  name: string;
  tagline: string;
  problem: string;
  chain: string[];
  systems: string[];
  accent: string;
}

export const solutions: Solution[] = [
  {
    slug: "restaurant",
    name: "Restaurant",
    tagline: "From walk-ins to a full online ordering business.",
    problem:
      "Orders taken by phone, no online presence, no way to reach customers beyond the neighborhood.",
    chain: ["Physical Restaurant", "Website", "Online Ordering", "Digital Payments", "Customer Management"],
    systems: ["Menu CMS", "Ordering Engine", "Payment Gateway", "CRM"],
    accent: "#f2b84b",
  },
  {
    slug: "retail",
    name: "Retail Store",
    tagline: "From a single storefront to a full e-commerce operation.",
    problem: "Sales limited to foot traffic, inventory tracked manually, no digital sales channel.",
    chain: ["Physical Store", "E-commerce", "Online Payments", "Inventory", "Analytics"],
    systems: ["Storefront", "Inventory Sync", "Payments", "Sales Analytics"],
    accent: "#4fd1c5",
  },
  {
    slug: "school",
    name: "School",
    tagline: "From paper records to a connected student platform.",
    problem: "Admissions, fees and communication handled manually across disconnected paperwork.",
    chain: ["Traditional School", "Website", "Student Portal", "Online Payments", "Management System"],
    systems: ["Admissions Portal", "Fee Payments", "Student Records", "Parent Communication"],
    accent: "#5170ff",
  },
  {
    slug: "hospital",
    name: "Hospital",
    tagline: "From phone-in appointments to full patient management.",
    problem: "Appointments scheduled by phone, patient records kept on paper, no digital payments.",
    chain: ["Traditional Hospital", "Website", "Appointment System", "Patient Management", "Digital Payments"],
    systems: ["Appointment Booking", "Patient Records", "Billing", "Telehealth-Ready"],
    accent: "#3ecf8e",
  },
  {
    slug: "hotel",
    name: "Hotel",
    tagline: "From walk-in bookings to a global reservation system.",
    problem: "Reservations handled by phone and spreadsheets, no visibility for international guests.",
    chain: ["Property", "Booking Website", "Reservation System", "Payments", "Guest Management"],
    systems: ["Booking Engine", "Channel Sync", "Payments", "Guest CRM"],
    accent: "#c9a463",
  },
  {
    slug: "real-estate",
    name: "Real Estate",
    tagline: "From listing sheets to an interactive property platform.",
    problem: "Listings shared as PDFs and phone calls, no searchable inventory for buyers.",
    chain: ["Agency", "Listings Platform", "Lead Capture", "CRM", "Digital Payments"],
    systems: ["Listings CMS", "Lead Pipeline", "CRM", "Virtual Tours"],
    accent: "#7c8cff",
  },
  {
    slug: "construction",
    name: "Construction",
    tagline: "From project folders to a client-facing digital system.",
    problem: "Project updates and quotes shared manually, no central client communication system.",
    chain: ["Firm", "Portfolio Website", "Project Portal", "Quoting System", "Client Communication"],
    systems: ["Project Showcase", "Quote Requests", "Client Portal", "Invoicing"],
    accent: "#8f9bb3",
  },
  {
    slug: "agency",
    name: "Agency",
    tagline: "From a portfolio deck to a full client operations platform.",
    problem: "Proposals and project tracking scattered across documents and email threads.",
    chain: ["Studio", "Portfolio Site", "Client Portal", "Proposals", "Billing"],
    systems: ["Case Study CMS", "Client Portal", "Proposal Engine", "Billing"],
    accent: "#ff8a65",
  },
  {
    slug: "startup",
    name: "Startup",
    tagline: "From an idea to a fully launched digital product.",
    problem: "No technical team, no infrastructure, needs to move fast without cutting corners.",
    chain: ["Idea", "MVP", "Product Platform", "Payments", "Growth Systems"],
    systems: ["Product Engineering", "Auth & Billing", "Analytics", "Growth Automation"],
    accent: "#5eb3ff",
  },
  {
    slug: "service-business",
    name: "Service Business",
    tagline: "From phone bookings to automated scheduling and payments.",
    problem: "Appointments scheduled by phone, no reminders, payments collected manually.",
    chain: ["Phone-based Business", "Website", "Booking", "Payments", "Automated Communication"],
    systems: ["Booking Engine", "Payments", "SMS/Email Automation", "Client Records"],
    accent: "#b98af0",
  },
];

export function getSolution(slug: string) {
  return solutions.find((s) => s.slug === slug);
}
