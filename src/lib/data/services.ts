import type { Service } from "@/lib/types";

export const services: Service[] = [
  {
    slug: "product-engineering",
    name: "Product Engineering",
    short: "Web, mobile & platform builds",
    description:
      "Full-lifecycle engineering for web, mobile and cross-platform products — from architecture and prototyping through to production-grade delivery and long-term ownership.",
    icon: "Code2",
    capabilities: [
      "Web application engineering",
      "Native & cross-platform mobile",
      "API & platform architecture",
      "Design-system driven front-ends",
      "Legacy modernization",
      "Quality engineering & automated testing",
    ],
    deliverables: ["Technical architecture", "Production codebase", "CI/CD pipeline", "Documentation & handover"],
  },
  {
    slug: "cloud-devops",
    name: "Cloud & DevOps",
    short: "Infrastructure that scales",
    description:
      "Resilient, cost-efficient cloud infrastructure engineered for scale — with automated delivery pipelines, observability and zero-downtime operations built in from day one.",
    icon: "Cloud",
    capabilities: [
      "Cloud architecture (AWS, GCP, Azure)",
      "Infrastructure as code",
      "Kubernetes & container orchestration",
      "CI/CD automation",
      "Observability & incident response",
      "Cost & performance optimization",
    ],
    deliverables: ["Infrastructure blueprint", "Automated pipelines", "Monitoring dashboards", "Runbooks"],
  },
  {
    slug: "ai-automation",
    name: "AI & Automation",
    short: "Applied intelligence",
    description:
      "Applied AI systems that remove operational friction — intelligent automation, LLM-powered products and predictive models grounded in your real business data.",
    icon: "Sparkles",
    capabilities: [
      "LLM & agentic product development",
      "Workflow & process automation",
      "Predictive & recommendation models",
      "Computer vision & document intelligence",
      "MLOps & model lifecycle",
      "Responsible AI governance",
    ],
    deliverables: ["Model & pipeline architecture", "Production AI services", "Evaluation framework", "Governance playbook"],
  },
  {
    slug: "design",
    name: "Design Systems",
    short: "Interfaces that convert",
    description:
      "Cohesive, scalable design systems and product interfaces engineered for clarity, conversion and brand consistency across every surface you ship.",
    icon: "Palette",
    capabilities: [
      "Product & UX strategy",
      "Design systems & component libraries",
      "Interaction & motion design",
      "Brand & visual identity",
      "Usability research",
      "Accessibility engineering",
    ],
    deliverables: ["Design system", "High-fidelity prototypes", "Component library", "Brand guidelines"],
  },
  {
    slug: "data",
    name: "Data & Analytics",
    short: "Decisions backed by data",
    description:
      "Modern data platforms and analytics products that turn fragmented data into a single source of truth — and truth into decisions your teams can act on.",
    icon: "BarChart3",
    capabilities: [
      "Data platform architecture",
      "ETL / ELT pipelines",
      "Real-time analytics",
      "Business intelligence & dashboards",
      "Data governance",
      "Warehouse & lakehouse migration",
    ],
    deliverables: ["Data architecture", "Pipelines & warehouse", "BI dashboards", "Governance framework"],
  },
  {
    slug: "security",
    name: "Security",
    short: "Enterprise-grade protection",
    description:
      "Security engineered in, not bolted on — from architecture review and threat modeling to compliance-ready infrastructure and continuous monitoring.",
    icon: "ShieldCheck",
    capabilities: [
      "Application security review",
      "Threat modeling",
      "Identity & access management",
      "Compliance (SOC 2, ISO 27001, GDPR)",
      "Penetration testing coordination",
      "Continuous security monitoring",
    ],
    deliverables: ["Security architecture", "Compliance roadmap", "Hardened infrastructure", "Monitoring & alerting"],
  },
];
