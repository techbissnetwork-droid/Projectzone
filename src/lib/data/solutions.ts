export type Solution = {
  slug: string;
  name: string;
  icon: string;
  eyebrow: string;
  headline: string;
  problem: string;
  opportunity: string;
  solution: string;
  system: string[];
  transformation: { from: string; to: string }[];
};

export const solutions: Solution[] = [
  {
    slug: "restaurant",
    name: "Restaurant",
    icon: "UtensilsCrossed",
    eyebrow: "Restaurants & Hospitality",
    headline: "From phone orders to a full digital dining experience.",
    problem:
      "Orders taken by phone, no online presence, and no way for new customers to discover the menu or book a table.",
    opportunity:
      "Diners now search, browse menus, order and reserve online before they ever call — restaurants without that presence lose them to competitors who have it.",
    solution:
      "A website with online ordering, table reservations and a digital menu, connected to payments and customer communication.",
    system: ["Website & digital menu", "Online ordering", "Table reservations", "Payments", "Customer messaging"],
    transformation: [
      { from: "Physical restaurant", to: "Website & digital menu" },
      { from: "Phone orders only", to: "Online ordering" },
      { from: "Walk-in only", to: "Table reservations" },
      { from: "Cash only", to: "Digital payments" },
    ],
  },
  {
    slug: "retail",
    name: "Retail",
    icon: "Store",
    eyebrow: "Retail & Product Businesses",
    headline: "From storefront to a store that never closes.",
    problem:
      "A physical storefront limits reach to foot traffic, with no way to sell, track inventory or reach customers outside store hours.",
    opportunity:
      "E-commerce extends a store's reach nationally or globally, while unifying inventory and customer data across every channel.",
    solution:
      "A commerce platform connected to inventory, payments and analytics — so the online store and physical store operate as one business.",
    system: ["E-commerce storefront", "Unified inventory", "Online payments", "Customer analytics"],
    transformation: [
      { from: "Physical store", to: "E-commerce storefront" },
      { from: "Manual stock counts", to: "Unified inventory" },
      { from: "Cash register", to: "Online payments" },
      { from: "No customer data", to: "Analytics & insights" },
    ],
  },
  {
    slug: "school",
    name: "School",
    icon: "GraduationCap",
    eyebrow: "Education",
    headline: "From paper records to a connected school platform.",
    problem:
      "Admissions, fees and communication with parents run through paper forms, spreadsheets and phone calls.",
    opportunity:
      "A digital school platform reduces administrative overhead and gives parents and students a single place to see everything.",
    solution:
      "A school website with a student/parent portal, online fee payments and an administrative management system.",
    system: ["School website", "Student & parent portal", "Fee payments", "Management system"],
    transformation: [
      { from: "Traditional school office", to: "School website" },
      { from: "Paper records", to: "Student portal" },
      { from: "Cash/cheque fees", to: "Online payments" },
      { from: "Manual admin", to: "Management system" },
    ],
  },
  {
    slug: "hospital",
    name: "Hospital & Clinic",
    icon: "HeartPulse",
    eyebrow: "Healthcare",
    headline: "From waiting-room queues to online appointments.",
    problem:
      "Appointments are booked over the phone, patient records live on paper, and there's no digital front door to the practice.",
    opportunity:
      "Patients expect to book, pay and communicate with providers online — a digital front door reduces no-shows and staff workload.",
    solution:
      "A website with online appointment booking, patient management and secure digital payments.",
    system: ["Clinic website", "Appointment booking", "Patient management", "Digital payments"],
    transformation: [
      { from: "Traditional hospital", to: "Website" },
      { from: "Phone bookings", to: "Appointment system" },
      { from: "Paper records", to: "Patient management" },
      { from: "In-person payment only", to: "Digital payments" },
    ],
  },
  {
    slug: "hotel",
    name: "Hotel & Stay",
    icon: "BedDouble",
    eyebrow: "Hospitality & Travel",
    headline: "From phone reservations to direct online bookings.",
    problem:
      "Reliance on third-party booking platforms and phone reservations means high commission fees and no direct guest relationship.",
    opportunity:
      "A direct booking website reduces commission costs and gives full control over pricing, availability and guest experience.",
    solution:
      "A booking-ready website with real-time availability, direct payments and guest communication tools.",
    system: ["Booking website", "Real-time availability", "Direct payments", "Guest communication"],
    transformation: [
      { from: "Phone reservations", to: "Direct online booking" },
      { from: "Third-party platforms only", to: "Owned booking channel" },
      { from: "Manual availability", to: "Real-time calendar" },
    ],
  },
  {
    slug: "real-estate",
    name: "Real Estate",
    icon: "Building2",
    eyebrow: "Real Estate",
    headline: "From listing sheets to a searchable property platform.",
    problem:
      "Listings are shared as PDFs and photos over messaging apps, with no central, searchable place for buyers to browse.",
    opportunity:
      "A property platform with search and inquiry tools converts browsers into qualified leads, and builds long-term brand trust.",
    solution:
      "A real estate website with property search, inquiry management and agent profiles.",
    system: ["Property website", "Search & filters", "Inquiry management", "Agent profiles"],
    transformation: [
      { from: "PDF listing sheets", to: "Searchable property site" },
      { from: "Messaging app inquiries", to: "Structured lead capture" },
    ],
  },
  {
    slug: "construction",
    name: "Construction",
    icon: "HardHat",
    eyebrow: "Construction & Contracting",
    headline: "From word-of-mouth to a credible digital portfolio.",
    problem:
      "Projects and expertise exist only as photos on a phone, with no way for prospective clients to evaluate the business online.",
    opportunity:
      "A project portfolio and quoting system builds credibility and lets prospects reach out with the right information upfront.",
    solution:
      "A website showcasing completed projects, service areas and a structured quote request system.",
    system: ["Project portfolio website", "Quote requests", "Service area pages"],
    transformation: [
      { from: "Word-of-mouth only", to: "Project portfolio website" },
      { from: "Unstructured phone calls", to: "Structured quote requests" },
    ],
  },
  {
    slug: "agency",
    name: "Agency",
    icon: "Briefcase",
    eyebrow: "Agencies & Studios",
    headline: "From a slide deck to a platform that sells the work.",
    problem:
      "Case studies live in scattered PDFs and decks, making it hard for prospects to evaluate the agency's actual capability.",
    opportunity:
      "A case-study-driven website turns past work into a compounding sales asset that works while the team is heads-down delivering.",
    solution:
      "An editorial website built around case studies, services and a structured inquiry process.",
    system: ["Case study website", "Service pages", "Structured inquiries"],
    transformation: [
      { from: "Deck-based pitching", to: "Case study website" },
      { from: "Ad-hoc inquiries", to: "Structured intake" },
    ],
  },
  {
    slug: "startup",
    name: "Startup",
    icon: "Rocket",
    eyebrow: "Startups",
    headline: "From an idea to a product customers can use.",
    problem:
      "An early-stage product needs to move fast without accumulating technical debt that slows the next stage of growth.",
    opportunity:
      "The right technical foundation lets a startup ship quickly now, and scale without a rebuild later.",
    solution:
      "A product build — website, application and backend — architected to launch fast and scale as usage grows.",
    system: ["Marketing website", "Product application", "Backend & database", "Analytics"],
    transformation: [
      { from: "Idea / prototype", to: "Production product" },
      { from: "No infrastructure", to: "Scalable backend" },
    ],
  },
  {
    slug: "service-business",
    name: "Service Business",
    icon: "PhoneCall",
    eyebrow: "Service Businesses",
    headline: "From a ringing phone to automated booking and payments.",
    problem:
      "Every booking, reminder and payment depends on a phone call, which caps how many customers the business can actually serve.",
    opportunity:
      "Online booking and automated communication let a service business handle more customers without more phone time.",
    solution:
      "A website with online booking, automated reminders and integrated payments.",
    system: ["Booking website", "Automated reminders", "Online payments"],
    transformation: [
      { from: "Phone-based booking", to: "Online booking" },
      { from: "Manual reminders", to: "Automated communication" },
    ],
  },
];

export function getSolution(slug: string) {
  return solutions.find((s) => s.slug === slug);
}
