"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import {
  UtensilsCrossed,
  ShoppingBag,
  GraduationCap,
  Stethoscope,
  Briefcase,
  Rocket,
  ArrowRight,
  ArrowDown,
  type LucideProps,
} from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/shared/Reveal";
import { solutions } from "@/lib/data/solutions";
import { cn } from "@/lib/utils/cn";

const icons: Record<string, React.ComponentType<LucideProps>> = {
  restaurants: UtensilsCrossed,
  retail: ShoppingBag,
  education: GraduationCap,
  healthcare: Stethoscope,
  "service-companies": Briefcase,
  startups: Rocket,
};

export function TransformationExamples() {
  const [active, setActive] = useState(0);
  const solution = solutions[active];
  const Icon = icons[solution.slug];

  return (
    <section className="py-24 md:py-32">
      <Container>
        <SectionHeading
          eyebrow="Real Businesses, Rebuilt Digitally"
          title="See the transformation, by business type."
          lead="Every industry hits the same wall offline. Here's what happens when TECHBISS removes it."
        />

        <Reveal delay={0.1} className="mt-12">
          <div className="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-2 sm:flex-wrap">
            {solutions.map((s, i) => {
              const TabIcon = icons[s.slug];
              return (
                <button
                  key={s.slug}
                  onClick={() => setActive(i)}
                  type="button"
                  className={cn(
                    "flex shrink-0 items-center gap-2 rounded-full border px-4 py-2.5 text-sm font-medium tracking-tight transition-colors duration-300",
                    active === i
                      ? "border-gold/50 bg-gold-dim text-gold-bright"
                      : "border-line-strong text-paper-dim hover:text-paper",
                  )}
                >
                  <TabIcon className="size-4" aria-hidden />
                  {s.business}
                </button>
              );
            })}
          </div>
        </Reveal>

        <AnimatePresence mode="wait">
          <motion.div
            key={solution.slug}
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] }}
            className="mt-8 overflow-hidden rounded-2xl border border-line bg-ink-raised-2"
          >
            <div className="flex items-center justify-between gap-4 border-b border-line px-6 py-5 sm:px-9">
              <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-full border border-line-strong text-paper-dim">
                  <Icon className="size-4" aria-hidden />
                </span>
                <p className="text-lg font-medium text-paper">{solution.tagline}</p>
              </div>
              <div className="hidden shrink-0 flex-col items-end sm:flex">
                <span className="text-2xl font-semibold text-gold-bright">
                  {solution.metric.value}
                </span>
                <span className="text-eyebrow text-paper-faint">
                  {solution.metric.label}
                </span>
              </div>
            </div>

            <div className="grid gap-0 md:grid-cols-[1fr_auto_1fr]">
              <div className="flex flex-col gap-4 px-6 py-8 sm:px-9">
                <span className="text-eyebrow text-paper-faint">
                  {solution.from}
                </span>
                <ul className="flex flex-col gap-3">
                  {solution.painPoints.map((p) => (
                    <li key={p} className="flex items-start gap-2.5 text-sm text-paper-dim">
                      <span className="mt-2 size-1 shrink-0 rounded-full bg-paper-faint" />
                      {p}
                    </li>
                  ))}
                </ul>
              </div>

              <div className="flex items-center justify-center border-y border-line py-4 md:border-x md:border-y-0 md:py-0">
                <span className="flex size-10 items-center justify-center rounded-full border border-line-strong text-gold-bright">
                  <ArrowDown className="size-4 md:hidden" aria-hidden />
                  <ArrowRight className="hidden size-4 md:block" aria-hidden />
                </span>
              </div>

              <div className="flex flex-col gap-4 bg-ink-raised-3/40 px-6 py-8 sm:px-9">
                <span className="text-eyebrow text-gold-bright">{solution.to}</span>
                <ul className="flex flex-col gap-3">
                  {solution.digitalFeatures.map((f) => (
                    <li key={f} className="flex items-start gap-2.5 text-sm text-paper">
                      <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-gold" />
                      {f}
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            <div className="flex items-center justify-between gap-4 border-t border-line px-6 py-5 sm:hidden sm:px-9">
              <span className="text-xl font-semibold text-gold-bright">
                {solution.metric.value}
              </span>
              <span className="text-eyebrow text-paper-faint">
                {solution.metric.label}
              </span>
            </div>
          </motion.div>
        </AnimatePresence>
      </Container>
    </section>
  );
}
