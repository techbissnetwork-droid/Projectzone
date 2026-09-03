export interface ProcessStage {
  index: string;
  title: string;
  description: string;
  details: string[];
}

export const processStages: ProcessStage[] = [
  {
    index: "01",
    title: "Discover",
    description: "Understand your business, customers and goals.",
    details: [
      "Stakeholder interviews and business audit",
      "Customer and market research",
      "Technical and competitive assessment",
      "Success metrics defined up front",
    ],
  },
  {
    index: "02",
    title: "Choose / Design",
    description: "Choose a ready-made theme or design a custom digital architecture.",
    details: [
      "Marketplace theme selection, or",
      "Custom information architecture",
      "Interface design in your brand system",
      "Technical architecture planning",
    ],
  },
  {
    index: "03",
    title: "Build",
    description: "Develop the website, application and infrastructure.",
    details: [
      "Production engineering sprints",
      "Backend systems and integrations",
      "Infrastructure provisioning",
      "Continuous internal QA",
    ],
  },
  {
    index: "04",
    title: "Brand",
    description: "Customize the digital experience around your identity.",
    details: [
      "Logo, color and typography system",
      "Content and imagery placement",
      "Brand Studio configuration",
      "Consistency across every page",
    ],
  },
  {
    index: "05",
    title: "Launch",
    description: "Configure domain, hosting, SSL, email and deployment.",
    details: [
      "Domain connection and DNS",
      "Hosting and SSL configuration",
      "Business email setup",
      "Go-live checklist and deployment",
    ],
  },
  {
    index: "06",
    title: "Grow",
    description: "Maintain, optimize and continuously improve.",
    details: [
      "Performance and SEO monitoring",
      "Ongoing maintenance and updates",
      "Feature iterations",
      "Monthly reporting and strategy",
    ],
  },
];
