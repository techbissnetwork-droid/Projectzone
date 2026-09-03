export interface Solution {
  slug: string;
  business: string;
  from: string;
  to: string;
  tagline: string;
  narrative: string;
  painPoints: string[];
  digitalFeatures: string[];
  servicesUsed: string[];
  metric: { value: string; label: string };
}

export const solutions: Solution[] = [
  {
    slug: "restaurants",
    business: "Restaurant",
    from: "Phone orders & paper tickets",
    to: "Online Ordering System",
    tagline: "From a landline that's always busy to orders that never sleep.",
    narrative:
      "Restaurants lose orders every night to busy phone lines and mis-heard tickets. We replace that with a branded ordering platform — live menu, real-time kitchen tickets and delivery coordination — so every order is captured correctly, the first time.",
    painPoints: [
      "Orders taken by phone, often mis-heard or lost",
      "No visibility into peak-hour demand",
      "Menu changes require reprinting",
      "Delivery coordinated manually by staff",
    ],
    digitalFeatures: [
      "Branded online ordering website & app",
      "Live menu with real-time availability",
      "Kitchen display & order routing",
      "Integrated online payments",
    ],
    servicesUsed: ["website-development", "mobile-app-development", "payment-integration"],
    metric: { value: "+41%", label: "order volume in 90 days" },
  },
  {
    slug: "retail",
    business: "Retail Store",
    from: "In-store only, one location",
    to: "E-commerce Platform",
    tagline: "From one storefront to a shelf that never closes.",
    narrative:
      "A physical shelf can only reach the customers who walk in. We turn a retail catalog into a full e-commerce platform — inventory, checkout and fulfillment connected to the store you already run — so the same products can sell nationally, or globally.",
    painPoints: [
      "Sales limited to store foot traffic",
      "Inventory tracked manually across channels",
      "No way to reach customers outside the area",
      "Promotions run only in-store",
    ],
    digitalFeatures: [
      "Full online storefront & checkout",
      "Real-time inventory sync with in-store POS",
      "Customer accounts & order history",
      "Email & promotional automation",
    ],
    servicesUsed: ["e-commerce", "payment-integration", "automation"],
    metric: { value: "3.4×", label: "revenue reach beyond local area" },
  },
  {
    slug: "education",
    business: "School",
    from: "Paper enrollment & noticeboards",
    to: "Education Portal",
    tagline: "From notices pinned on a board to a portal every parent checks.",
    narrative:
      "Schools run on communication — enrollment, attendance, grades, fees — and paper slows all of it down. We build education portals that give administrators, teachers and parents one connected system for everything that used to live in a filing cabinet.",
    painPoints: [
      "Enrollment handled entirely on paper",
      "Parent communication inconsistent",
      "Fee collection manual and hard to track",
      "No central record of attendance or grades",
    ],
    digitalFeatures: [
      "Online enrollment & admissions portal",
      "Parent-teacher communication system",
      "Digital attendance & gradebook",
      "Online fee payment & tracking",
    ],
    servicesUsed: ["business-digitization", "website-development", "payment-integration"],
    metric: { value: "-73%", label: "administrative processing time" },
  },
  {
    slug: "healthcare",
    business: "Hospital / Clinic",
    from: "Walk-in queues & phone booking",
    to: "Booking & Management System",
    tagline: "From a crowded waiting room to a schedule that manages itself.",
    narrative:
      "Patients shouldn't have to call repeatedly to book an appointment, and staff shouldn't have to manage a schedule by hand. We build booking and management systems that let patients self-schedule, and let staff run the practice from one dashboard.",
    painPoints: [
      "Appointments booked entirely by phone",
      "Double-bookings and scheduling conflicts",
      "Patient records kept on paper or in silos",
      "No automated reminders — high no-show rate",
    ],
    digitalFeatures: [
      "Online appointment booking system",
      "Automated reminders & confirmations",
      "Digital patient records dashboard",
      "Staff scheduling & management tools",
    ],
    servicesUsed: ["business-digitization", "mobile-app-development", "automation"],
    metric: { value: "-52%", label: "missed appointments" },
  },
  {
    slug: "service-companies",
    business: "Service Company",
    from: "Manual scheduling & quotes",
    to: "Online Booking Platform",
    tagline: "From back-and-forth calls to a calendar that fills itself.",
    narrative:
      "Service businesses — from consultants to contractors — lose time to scheduling back-and-forth. We build booking platforms with live availability, automated quotes and reminders, so customers can book instantly and your calendar stays full.",
    painPoints: [
      "Booking requires manual back-and-forth",
      "Quotes calculated and sent individually",
      "No-shows with no reminder system",
      "Customer history scattered across notebooks and inboxes",
    ],
    digitalFeatures: [
      "Live availability & instant booking",
      "Automated quotes & invoicing",
      "SMS & email appointment reminders",
      "Centralized customer & job history",
    ],
    servicesUsed: ["website-development", "automation", "payment-integration"],
    metric: { value: "+58%", label: "booked capacity" },
  },
  {
    slug: "startups",
    business: "Startup",
    from: "An idea and a slide deck",
    to: "Complete Digital Product",
    tagline: "From zero to a product customers can actually use.",
    narrative:
      "Startups need to move from concept to a real, working product fast — without cutting corners that cost more later. We take an idea through architecture, design and engineering to a launch-ready digital product, backed by infrastructure that scales with traction.",
    painPoints: [
      "No technical co-founder or in-house team",
      "Idea validated but nothing built",
      "Uncertainty over what to build first",
      "Limited runway to reach a launchable product",
    ],
    digitalFeatures: [
      "Product strategy & MVP scoping",
      "Full-stack application build",
      "Scalable cloud infrastructure",
      "Launch, analytics & iteration support",
    ],
    servicesUsed: ["custom-web-applications", "mobile-app-development", "hosting-infrastructure"],
    metric: { value: "8 wks", label: "idea to launch-ready MVP" },
  },
];

export function getSolutionBySlug(slug: string) {
  return solutions.find((s) => s.slug === slug);
}
