"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, AnimatePresence } from "framer-motion";
import { ArrowRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal } from "@/components/ui/reveal";
import { cn } from "@/lib/utils";
import { solutions } from "@/lib/data/solutions";

const featured = solutions.filter((s) =>
  ["restaurant", "retail", "school", "hospital", "service-business"].includes(s.slug),
);

export function BusinessTransformations() {
  const [active, setActive] = useState(0);
  const current = featured[active];

  return (
    <section className="py-24 sm:py-32">
      <Container>
        <Reveal className="mx-auto max-w-[640px] text-center">
          <Eyebrow className="justify-center">Business Transformation</Eyebrow>
          <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
            Existing business. Digital business.
          </h2>
        </Reveal>

        <Reveal delay={0.1} className="mt-12 flex flex-wrap justify-center gap-2">
          {featured.map((s, i) => (
            <button
              key={s.slug}
              onClick={() => setActive(i)}
              className={cn(
                "rounded-full border px-4 py-2 text-[13px] font-medium transition-colors duration-300",
                active === i
                  ? "border-[var(--color-ink)] bg-[var(--color-ink)] text-[var(--color-bg)]"
                  : "border-[var(--color-border-strong)] text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]",
              )}
            >
              {s.name}
            </button>
          ))}
        </Reveal>

        <div className="mt-12 rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 sm:p-12">
          <AnimatePresence mode="wait">
            <motion.div
              key={current.slug}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -12 }}
              transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
            >
              <div className="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                <div className="max-w-[440px]">
                  <span
                    className="inline-flex rounded-full px-3 py-1 text-[12px] font-medium"
                    style={{ backgroundColor: `${current.accent}22`, color: current.accent }}
                  >
                    {current.name}
                  </span>
                  <p className="mt-4 text-[17px] leading-relaxed text-[var(--color-ink)] sm:text-[19px]">
                    {current.problem}
                  </p>
                </div>
                <Link
                  href={`/solutions/${current.slug}`}
                  className="inline-flex shrink-0 items-center gap-1.5 text-[13px] font-medium text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]"
                >
                  View Solution
                  <ArrowRight className="size-3.5" />
                </Link>
              </div>

              <div className="mt-10 flex flex-wrap items-center gap-2.5">
                {current.chain.map((step, i) => (
                  <div key={step} className="flex items-center gap-2.5">
                    <span
                      className={cn(
                        "rounded-full border px-3.5 py-2 text-[12.5px] font-medium",
                        i === 0
                          ? "border-[var(--color-border-strong)] text-[var(--color-ink-faint)]"
                          : "border-transparent text-[var(--color-ink)]",
                      )}
                      style={i !== 0 ? { backgroundColor: `${current.accent}18` } : undefined}
                    >
                      {step}
                    </span>
                    {i < current.chain.length - 1 && (
                      <ArrowRight className="size-3.5 shrink-0 text-[var(--color-ink-faint)]" />
                    )}
                  </div>
                ))}
              </div>
            </motion.div>
          </AnimatePresence>
        </div>
      </Container>
    </section>
  );
}
