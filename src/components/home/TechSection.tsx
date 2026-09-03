"use client";

import { motion } from "motion/react";
import { ShieldCheck, TrendingUp, Zap, Activity, Cloud, Smartphone } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/shared/Reveal";

const stack = [
  "Next.js",
  "React",
  "Node.js",
  "PostgreSQL",
  "AWS",
  "Cloudflare",
  "Stripe",
  "Docker",
  "TypeScript",
  "Redis",
];

const capabilities = [
  {
    label: "Secure",
    desc: "Encrypted by default",
    icon: ShieldCheck,
    visual: (
      <div className="flex items-center gap-1.5">
        {[0, 1, 2].map((i) => (
          <motion.span
            key={i}
            className="size-1.5 rounded-full bg-signal-bright"
            animate={{ opacity: [0.25, 1, 0.25] }}
            transition={{ duration: 1.8, repeat: Infinity, delay: i * 0.25 }}
          />
        ))}
      </div>
    ),
  },
  {
    label: "Scalable",
    desc: "Grows with demand",
    icon: TrendingUp,
    visual: (
      <div className="flex h-4 items-end gap-1">
        {[40, 65, 50, 85, 70].map((h, i) => (
          <motion.span
            key={i}
            className="w-1 rounded-full bg-signal-bright/70"
            style={{ height: `${h}%` }}
            animate={{ height: [`${h * 0.5}%`, `${h}%`] }}
            transition={{ duration: 1.6, repeat: Infinity, repeatType: "reverse", delay: i * 0.1 }}
          />
        ))}
      </div>
    ),
  },
  {
    label: "Fast",
    desc: "Sub-second loads",
    icon: Zap,
    visual: <span className="font-mono text-xs text-signal-bright">&lt;50ms</span>,
  },
  {
    label: "Reliable",
    desc: "Always available",
    icon: Activity,
    visual: <span className="font-mono text-xs text-signal-bright">99.99%</span>,
  },
  {
    label: "Cloud-ready",
    desc: "Built for the edge",
    icon: Cloud,
    visual: (
      <div className="relative flex h-4 w-10 items-center">
        <span className="absolute inset-x-0 h-px bg-line-strong" />
        {[0, 0.5, 1].map((p, i) => (
          <motion.span
            key={i}
            className="absolute size-1.5 rounded-full bg-signal-bright"
            style={{ left: `${p * 100}%` }}
            animate={{ opacity: [0.2, 1, 0.2] }}
            transition={{ duration: 2, repeat: Infinity, delay: i * 0.3 }}
          />
        ))}
      </div>
    ),
  },
  {
    label: "Mobile-ready",
    desc: "Every screen size",
    icon: Smartphone,
    visual: <span className="font-mono text-xs text-signal-bright">100%</span>,
  },
];

export function TechSection() {
  return (
    <section className="border-y border-line bg-ink-raised py-24 md:py-32">
      <Container>
        <SectionHeading
          eyebrow="Infrastructure You Can Trust"
          tone="signal"
          title="Secure, scalable and fast — by default."
          lead="Every TECHBISS build runs on modern, production-grade infrastructure. No shortcuts, no fragile stacks."
        />

        <Reveal delay={0.1}>
          <div className="mt-14 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3 lg:grid-cols-6">
            {capabilities.map((cap) => (
              <div
                key={cap.label}
                className="flex flex-col gap-4 bg-ink-raised-2 p-6"
              >
                <cap.icon className="size-5 text-signal-bright" aria-hidden />
                <div>
                  <p className="font-medium text-paper">{cap.label}</p>
                  <p className="mt-0.5 text-xs text-paper-faint">{cap.desc}</p>
                </div>
                <div className="mt-auto pt-2">{cap.visual}</div>
              </div>
            ))}
          </div>
        </Reveal>

        <Reveal delay={0.2}>
          <div className="mask-fade-x mt-16 overflow-hidden">
            <div className="marquee-track flex w-max items-center gap-10">
              {[...stack, ...stack].map((tech, i) => (
                <span
                  key={`${tech}-${i}`}
                  className="text-eyebrow shrink-0 text-paper-faint"
                >
                  {tech}
                </span>
              ))}
            </div>
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
