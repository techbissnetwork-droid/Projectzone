import type { ProcessStep } from "@/lib/types";

export const processSteps: ProcessStep[] = [
  {
    number: "01",
    title: "Discover",
    description:
      "We embed with your team to understand the business goals, technical constraints and success metrics before a single line of code is written.",
    deliverables: ["Stakeholder interviews", "Technical audit", "Success metrics", "Risk assessment"],
    duration: "1–2 weeks",
  },
  {
    number: "02",
    title: "Design",
    description:
      "Product strategy, information architecture and interface design converge into a validated prototype your users can react to before we build.",
    deliverables: ["Product strategy", "Prototypes", "Design system", "Validation testing"],
    duration: "2–4 weeks",
  },
  {
    number: "03",
    title: "Build",
    description:
      "Engineering moves in focused, demoable sprints — with continuous integration, automated testing and full visibility into progress at every stage.",
    deliverables: ["Sprint delivery", "CI/CD pipeline", "Automated testing", "Weekly demos"],
    duration: "6–16 weeks",
  },
  {
    number: "04",
    title: "Launch",
    description:
      "A structured go-live plan — staged rollout, monitoring, rollback procedures and team enablement — so launch day is uneventful by design.",
    deliverables: ["Staged rollout plan", "Monitoring & alerting", "Rollback procedures", "Team enablement"],
    duration: "1–2 weeks",
  },
  {
    number: "05",
    title: "Grow",
    description:
      "Post-launch, we stay close to the data — iterating on performance, conversion and reliability as your product and user base scale.",
    deliverables: ["Performance monitoring", "Iterative optimization", "Quarterly roadmap", "Ongoing support"],
    duration: "Ongoing",
  },
];
