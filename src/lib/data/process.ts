export interface ProcessStep {
  index: string;
  slug: string;
  title: string;
  summary: string;
  description: string;
  activities: string[];
  deliverables: string[];
  duration: string;
}

export const processSteps: ProcessStep[] = [
  {
    index: "01",
    slug: "discover",
    title: "Discover",
    summary: "Understand the business, customers and goals.",
    description:
      "Before anything is designed or built, we learn how your business actually works — who your customers are, how they currently reach you, and where the biggest opportunities for digital growth are hiding.",
    activities: [
      "Stakeholder & operations interviews",
      "Customer journey & competitor audit",
      "Technical & digital maturity assessment",
      "Goals, constraints and success metrics defined",
    ],
    deliverables: ["Discovery report", "Opportunity map", "Project brief & scope"],
    duration: "1–2 weeks",
  },
  {
    index: "02",
    slug: "design",
    title: "Design",
    summary: "Create the digital experience and architecture.",
    description:
      "We translate discovery into a concrete plan — the information architecture, user experience and visual design system your digital presence will be built on, validated with you before a line of code is written.",
    activities: [
      "Information architecture & sitemap",
      "UX flows & wireframing",
      "Visual design system & UI",
      "Prototype review & sign-off",
    ],
    deliverables: ["Design system", "Interactive prototype", "Content & UX plan"],
    duration: "2–3 weeks",
  },
  {
    index: "03",
    slug: "build",
    title: "Build",
    summary: "Develop the website, application and infrastructure.",
    description:
      "Design becomes a real, working system. Our engineers build the website, application and integrations on modern, maintainable foundations — with regular checkpoints so you see progress, not just a final reveal.",
    activities: [
      "Frontend & backend development",
      "CMS, database & API integration",
      "Cross-device & cross-browser testing",
      "Performance & accessibility optimization",
    ],
    deliverables: ["Staging environment", "QA & test reports", "Technical documentation"],
    duration: "3–8 weeks",
  },
  {
    index: "04",
    slug: "launch",
    title: "Launch",
    summary: "Configure domain, hosting, SSL, email and deployment.",
    description:
      "Launch is engineered, not improvised. Domain, hosting, SSL, email and monitoring are configured and verified before go-live, so your business goes online with zero avoidable surprises.",
    activities: [
      "Domain, DNS & SSL configuration",
      "Production hosting & infrastructure setup",
      "Business email deployment",
      "Final QA & go-live checklist",
    ],
    deliverables: ["Live production system", "Launch checklist", "Analytics & monitoring setup"],
    duration: "1 week",
  },
  {
    index: "05",
    slug: "grow",
    title: "Grow",
    summary: "Maintain, optimize and continuously improve the digital business.",
    description:
      "A launch is a beginning. We monitor real usage, maintain and secure the system, and continuously identify opportunities — new features, better conversion, deeper automation — to keep your digital business growing.",
    activities: [
      "Uptime, security & performance monitoring",
      "Ongoing maintenance & updates",
      "Conversion & analytics review",
      "Roadmap for new features & growth",
    ],
    deliverables: ["Monthly performance reports", "Maintenance & support plan", "Growth roadmap"],
    duration: "Ongoing",
  },
];
