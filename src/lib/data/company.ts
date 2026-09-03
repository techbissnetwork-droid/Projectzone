export interface Pillar {
  title: string;
  description: string;
  items: string[];
}

export const pillars: Pillar[] = [
  {
    title: "Start Online",
    description:
      "The foundation every business needs before anything else — a real presence, properly built and properly owned.",
    items: ["Domain", "Website", "App", "Hosting", "SSL", "Business Email"],
  },
  {
    title: "Operate Online",
    description:
      "The systems that turn a presence into a business — selling, taking payment, booking and running day to day.",
    items: [
      "E-commerce",
      "Payments",
      "Booking",
      "Automation",
      "Business Systems",
      "Analytics",
    ],
  },
  {
    title: "Grow Online",
    description:
      "The ongoing work that compounds — keeping systems fast, secure, current, and evolving with the business.",
    items: [
      "Maintenance",
      "Optimization",
      "Custom Development",
      "Infrastructure",
      "Support",
      "Continuous Improvement",
    ],
  },
];

export interface Principle {
  title: string;
  description: string;
}

export const principles: Principle[] = [
  {
    title: "Engineering Discipline",
    description:
      "Every system is built the way we'd build our own — clean architecture, tested code, and documentation that outlives the person who wrote it.",
  },
  {
    title: "Design Intelligence",
    description:
      "Interfaces are decided with evidence, not preference. Every layout earns its place by serving the customer at the other end of it.",
  },
  {
    title: "Radical Reliability",
    description:
      "Uptime, backups and monitoring aren't add-ons. A system that isn't reliable isn't finished, no matter how it looks.",
  },
  {
    title: "Client Partnership",
    description:
      "We stay in the system after launch. Digital infrastructure is never really done — it's maintained, measured, and improved.",
  },
  {
    title: "Built to Scale",
    description:
      "What we ship for twelve customers should still work for twelve thousand. We design for the business you're becoming.",
  },
  {
    title: "Transparent Delivery",
    description:
      "Clear timelines, visible progress, honest tradeoffs. You always know what stage your project is in and what happens next.",
  },
];

export interface CapabilityStat {
  stat: string;
  label: string;
}

export const capabilityStats: CapabilityStat[] = [
  { stat: "150+", label: "digital systems shipped" },
  { stat: "40+", label: "industries served" },
  { stat: "99.99%", label: "uptime architecture" },
  { stat: "24/7", label: "monitoring & support" },
];
