"use client";

import { motion } from "motion/react";
import { Container } from "@/components/ui/Container";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { AnimatedHeadline } from "@/components/shared/AnimatedHeadline";

export function PageHero({
  eyebrow,
  title,
  lead,
  tone = "gold",
  stats,
  align = "center",
}: {
  eyebrow: string;
  title: string;
  lead?: string;
  tone?: "gold" | "signal";
  stats?: { value: string; label: string }[];
  align?: "center" | "left";
}) {
  return (
    <section className="relative overflow-hidden pb-16 pt-36 sm:pt-40 md:pb-20 md:pt-48">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[640px]"
        style={{
          background:
            tone === "gold"
              ? "radial-gradient(50% 40% at 50% 0%, rgba(201,168,118,0.10), transparent)"
              : "radial-gradient(50% 40% at 50% 0%, rgba(127,166,217,0.10), transparent)",
        }}
      />
      <Container
        className={`flex flex-col gap-7 ${align === "center" ? "items-center text-center" : "items-start text-left"}`}
      >
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6 }}>
          <Eyebrow tone={tone}>{eyebrow}</Eyebrow>
        </motion.div>

        <AnimatedHeadline
          text={title}
          as="h1"
          delay={0.08}
          className={`text-h1 text-balance font-medium text-paper ${align === "center" ? "max-w-4xl" : "max-w-3xl"}`}
        />

        {lead && (
          <motion.p
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.5 }}
            className={`text-lead text-balance text-paper-dim ${align === "center" ? "max-w-2xl" : "max-w-xl"}`}
          >
            {lead}
          </motion.p>
        )}

        {stats && (
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.65 }}
            className={`mt-4 flex flex-wrap gap-x-10 gap-y-6 ${align === "center" ? "justify-center" : "justify-start"}`}
          >
            {stats.map((s) => (
              <div key={s.label} className={align === "center" ? "text-center" : "text-left"}>
                <p className="text-h3 font-medium text-paper">{s.value}</p>
                <p className="text-eyebrow mt-1 text-paper-faint">{s.label}</p>
              </div>
            ))}
          </motion.div>
        )}
      </Container>
    </section>
  );
}
