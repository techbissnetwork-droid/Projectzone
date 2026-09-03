"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, AnimatePresence } from "motion/react";
import { ArrowRight, ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { ServiceIcon } from "@/components/shared/ServiceIcon";
import { Reveal } from "@/components/shared/Reveal";
import { detailedServices } from "@/lib/data/services";
import { cn } from "@/lib/utils/cn";

function ServiceVisual({ index }: { index: number }) {
  const service = detailedServices[index];
  const isGold = service.color === "gold";

  return (
    <div className="relative flex aspect-[16/11] w-full items-center justify-center overflow-hidden rounded-xl border border-line bg-ink-raised-2">
      <div
        aria-hidden
        className="absolute inset-0"
        style={{
          backgroundImage:
            "linear-gradient(var(--color-line) 1px, transparent 1px), linear-gradient(90deg, var(--color-line) 1px, transparent 1px)",
          backgroundSize: "28px 28px",
        }}
      />
      <motion.div
        aria-hidden
        animate={{ rotate: 360 }}
        transition={{ duration: 26, repeat: Infinity, ease: "linear" }}
        className={cn(
          "absolute size-40 rounded-full border border-dashed sm:size-48",
          isGold ? "border-gold/25" : "border-signal/25",
        )}
      />
      <motion.div
        aria-hidden
        animate={{ rotate: -360 }}
        transition={{ duration: 34, repeat: Infinity, ease: "linear" }}
        className={cn(
          "absolute size-28 rounded-full border sm:size-32",
          isGold ? "border-gold/15" : "border-signal/15",
        )}
      />
      <div
        className={cn(
          "relative flex size-16 items-center justify-center rounded-2xl border sm:size-20",
          isGold
            ? "border-gold/40 bg-gold-dim text-gold-bright"
            : "border-signal/40 bg-signal-dim text-signal-bright",
        )}
      >
        <ServiceIcon name={service.icon} className="size-7 sm:size-8" aria-hidden />
      </div>
      {[0, 1, 2].map((d) => (
        <motion.span
          key={d}
          aria-hidden
          className={cn(
            "absolute size-1.5 rounded-full",
            isGold ? "bg-gold-bright" : "bg-signal-bright",
          )}
          style={{
            top: `${28 + d * 18}%`,
            left: `${18 + d * 30}%`,
          }}
          animate={{ opacity: [0.15, 1, 0.15], scale: [0.8, 1.3, 0.8] }}
          transition={{
            duration: 2.4,
            repeat: Infinity,
            delay: d * 0.5,
            ease: "easeInOut",
          }}
        />
      ))}
    </div>
  );
}

function ServiceDetailPanel({ index }: { index: number }) {
  const service = detailedServices[index];
  return (
    <motion.div
      key={service.slug}
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -8 }}
      transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
      className="flex flex-col gap-6"
    >
      <ServiceVisual index={index} />
      <div>
        <span className="text-eyebrow text-paper-faint">{service.category}</span>
        <h3 className="text-h3 mt-2 font-medium text-paper">{service.fullName}</h3>
        <p className="mt-3 text-[0.95rem] leading-relaxed text-paper-dim">
          {service.shortDescription}
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
        {service.technologies.slice(0, 4).map((t) => (
          <span
            key={t}
            className="text-eyebrow rounded-full border border-line-strong px-3 py-1.5 text-[0.65rem] text-paper-dim"
          >
            {t}
          </span>
        ))}
      </div>
      <Link
        href={`/services/${service.slug}`}
        className="group inline-flex w-fit items-center gap-2 text-sm font-medium text-paper transition-colors hover:text-gold-bright"
      >
        Explore {service.name}
        <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
      </Link>
    </motion.div>
  );
}

export function ServicesShowcase() {
  const [active, setActive] = useState(0);

  return (
    <section className="py-24 md:py-32">
      <Container>
        <SectionHeading
          eyebrow="The Digital Ecosystem"
          title="Everything your business needs online."
          lead="Nine disciplines, one connected team. Explore each part of the ecosystem — or let us build the whole thing."
        />

        <div className="mt-14 grid gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)] lg:gap-16">
          <Reveal delay={0.1}>
            <div className="flex flex-col divide-y divide-line border-y border-line">
              {detailedServices.map((service, i) => (
                <button
                  key={service.slug}
                  type="button"
                  onMouseEnter={() => setActive(i)}
                  onFocus={() => setActive(i)}
                  onClick={() => setActive(i)}
                  aria-expanded={active === i}
                  className={cn(
                    "group flex items-center justify-between gap-4 py-4 text-left transition-colors sm:py-5",
                    active === i ? "text-paper" : "text-paper-dim hover:text-paper",
                  )}
                >
                  <span className="flex items-center gap-4">
                    <span
                      className={cn(
                        "flex size-10 shrink-0 items-center justify-center rounded-full border transition-colors duration-300",
                        active === i
                          ? "border-gold/50 bg-gold-dim text-gold-bright"
                          : "border-line-strong text-paper-faint",
                      )}
                    >
                      <ServiceIcon name={service.icon} className="size-4" aria-hidden />
                    </span>
                    <span className="text-lg font-medium tracking-tight sm:text-xl">
                      {service.fullName}
                    </span>
                  </span>
                  <ArrowRight
                    className={cn(
                      "size-5 shrink-0 transition-all duration-300",
                      active === i
                        ? "translate-x-0 opacity-100 text-gold-bright"
                        : "-translate-x-1 opacity-0 group-hover:translate-x-0 group-hover:opacity-60",
                    )}
                  />
                </button>
              ))}
            </div>
          </Reveal>

          <Reveal delay={0.18}>
            <div className="lg:sticky lg:top-28">
              <AnimatePresence mode="wait">
                <ServiceDetailPanel index={active} />
              </AnimatePresence>
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
