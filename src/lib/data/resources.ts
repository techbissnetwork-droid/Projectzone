import type { Article } from "@/lib/types";

export const articles: Article[] = [
  {
    slug: "state-of-enterprise-modernization-2026",
    title: "The State of Enterprise Modernization, 2026",
    category: "Report",
    excerpt:
      "We surveyed 400 technology leaders on modernization budgets, AI adoption and the platforms they're betting on this year.",
    readTime: "9 min read",
    date: "Feb 12, 2026",
    author: "TECHBISS Research",
    content: [
      "Enterprise modernization budgets have shifted decisively toward platform consolidation. Where 2023-2024 saw organizations layering point solutions on top of legacy cores, 2026 leaders are prioritizing fewer, deeper platform investments that reduce integration overhead.",
      "AI adoption has moved from experimentation to operating requirement: 78% of surveyed leaders now consider applied AI capability a baseline expectation for new platform investments, not a differentiator.",
      "The organizations reporting the strongest ROI share one trait — they treated modernization as a phased, reversible program rather than a single high-risk cutover. Strangler-fig migration patterns and parallel-run validation were cited by 61% of high performers as critical to their success.",
      "Security and compliance requirements are increasingly shaping architecture decisions earlier in the process, not bolted on at the end. Teams that involved security architects from day one shipped 40% faster on average than teams that engaged security only before launch.",
    ],
  },
  {
    slug: "choosing-a-cloud-architecture-that-scales",
    title: "Choosing a Cloud Architecture That Actually Scales",
    category: "Guide",
    excerpt:
      "A practical framework for evaluating cloud architecture decisions against real growth scenarios, not hypothetical ones.",
    readTime: "11 min read",
    date: "Jan 28, 2026",
    author: "Daniel Kessler",
    content: [
      "Most architecture decisions fail not because the technology was wrong, but because the growth scenario it was designed for never matched reality. Before choosing between monolith, microservices or a modular monolith, map your actual growth curve — not the one in your pitch deck.",
      "For teams under 20 engineers, a well-structured modular monolith consistently outperforms premature microservices in delivery speed and operational simplicity. The complexity tax of distributed systems only pays off once team boundaries genuinely require independent deployment cadences.",
      "Design for statelessness early, even if you don't need horizontal scale yet — it's far cheaper to build in from day one than retrofit under load. Pair this with infrastructure-as-code from the first commit, not after your first outage.",
      "Finally, instrument before you need to. Observability retrofitted during an incident is observability built under duress; observability built during calm periods is a system you can actually trust when it matters.",
    ],
  },
  {
    slug: "design-systems-that-survive-contact-with-reality",
    title: "Design Systems That Survive Contact With Reality",
    category: "Playbook",
    excerpt:
      "Why most design systems stall after the first six months — and the operating model that keeps them alive.",
    readTime: "8 min read",
    date: "Jan 14, 2026",
    author: "Mei Lin Zhao",
    content: [
      "Design systems don't fail because of bad components — they fail because of missing ownership. The teams that sustain a design system past year one treat it as a product with its own roadmap, not a side project bolted onto someone's existing role.",
      "Token architecture matters more than component count. A system with 40 well-structured tokens and 30 components will outlast a system with 300 components and no token discipline, because it can absorb rebrands and platform changes without a rewrite.",
      "Governance doesn't mean gatekeeping. The highest-adoption systems we've built pair a lightweight contribution model with a fast design-review SLA, so product teams extend the system instead of working around it.",
      "Measure adoption, not existence. A design system nobody uses is a maintenance liability, not an asset — track component usage in production, not just in Figma.",
    ],
  },
  {
    slug: "ai-agents-in-production-what-actually-works",
    title: "AI Agents in Production: What Actually Works",
    category: "Insight",
    excerpt: "Lessons from shipping applied AI systems into regulated, high-stakes production environments.",
    readTime: "10 min read",
    date: "Dec 19, 2025",
    author: "Camila Duarte",
    content: [
      "The gap between an AI demo and an AI system your business can depend on is almost entirely about evaluation, not model choice. Teams that invest early in a rigorous evaluation harness ship more reliable systems, regardless of which model they start with.",
      "Human-in-the-loop isn't a fallback for a system that isn't good enough yet — for regulated and high-stakes workflows, it's a permanent architectural decision. Design the escalation path as a first-class feature, not an afterthought.",
      "Cost discipline compounds. Systems that route intelligently between smaller and larger models based on task complexity routinely cut inference cost by 60-80% with no measurable quality loss for the majority of requests.",
      "Finally, treat prompts and agent behavior as versioned, tested artifacts — not configuration you tweak in production. The teams with the fewest AI incidents are the ones who test agent behavior with the same rigor as application code.",
    ],
  },
  {
    slug: "webinar-scaling-multi-tenant-saas",
    title: "Webinar: Scaling Multi-Tenant SaaS Without a Rewrite",
    category: "Webinar",
    excerpt: "Our VP of Engineering walks through the architecture decisions that let Voltage Analytics scale 60x.",
    readTime: "42 min watch",
    date: "Dec 3, 2025",
    author: "Idris Osei",
    content: [
      "This session breaks down the real architecture decisions behind scaling a single-tenant SaaS platform to over 12,000 organizations without a ground-up rewrite.",
      "Topics covered include tenant isolation strategies, incremental migration sequencing, usage-based billing architecture, and how to keep shipping new features for existing customers while the migration is underway.",
      "We also cover the organizational side: how to sequence the migration across engineering, sales and customer success so growth never has to pause for infrastructure work.",
      "Reach out to our team if you'd like the full slide deck and migration checklist referenced in this session.",
    ],
  },
  {
    slug: "security-by-default-a-practical-checklist",
    title: "Security by Default: A Practical Checklist",
    category: "Guide",
    excerpt: "The security fundamentals every product team should have in place before their next major release.",
    readTime: "7 min read",
    date: "Nov 22, 2025",
    author: "TECHBISS Security",
    content: [
      "Security debt compounds faster than technical debt because it's invisible until it isn't. This checklist covers the fundamentals every product team should verify before a major release: authentication hardening, secrets management, dependency scanning and least-privilege access.",
      "Start with identity: enforce MFA for all privileged access, rotate service credentials automatically, and audit third-party OAuth scopes quarterly. Most breaches we've reviewed trace back to an overprivileged credential, not a novel exploit.",
      "Automate what you can verify: dependency and container scanning in CI catches the majority of known vulnerabilities before they reach production, at a fraction of the cost of a post-incident response.",
      "Finally, practice your incident response before you need it. Tabletop exercises twice a year consistently cut real incident response time in half for the teams we work with.",
    ],
  },
];

export const articleCategories = ["All", "Guide", "Insight", "Playbook", "Report", "Webinar"] as const;
