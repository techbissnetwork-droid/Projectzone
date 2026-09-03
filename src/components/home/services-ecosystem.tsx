"use client";

import { useState } from "react";
import Link from "next/link";
import * as Icons from "lucide-react";
import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import { services } from "@/lib/data/services";
import { cn } from "@/lib/utils";
import { motion, AnimatePresence } from "framer-motion";

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export function ServicesEcosystem() {
  const [active, setActive] = useState(services[0].slug);
  const current = services.find((s) => s.slug === active)!;

  return (
    <section className="border-b border-line-dark bg-ink-900/40 py-24 sm:py-32">
      <Container wide>
        <Reveal>
          <Eyebrow>Services Ecosystem</Eyebrow>
          <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
            Every service your digital operation needs, connected.
          </h2>
        </Reveal>

        <div className="mt-14 grid gap-3 lg:grid-cols-[0.85fr_1.15fr] lg:gap-8">
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-2">
            {services.map((service) => (
              <button
                key={service.slug}
                onMouseEnter={() => setActive(service.slug)}
                onFocus={() => setActive(service.slug)}
                onClick={() => setActive(service.slug)}
                className={cn(
                  "flex flex-col items-start gap-3 rounded-xl border px-4 py-4 text-left transition-colors",
                  active === service.slug
                    ? "border-gold-500/40 bg-gold-500/[0.06]"
                    : "border-line-dark bg-ink-950/30 hover:border-line-dark-strong"
                )}
              >
                <Icon
                  name={service.icon}
                  className={cn(
                    "size-5",
                    active === service.slug ? "text-gold-400" : "text-paper-50/60"
                  )}
                />
                <span className="text-[13px] font-medium text-paper-50/85">{service.name}</span>
              </button>
            ))}
          </div>

          <AnimatePresence mode="wait">
            <motion.div
              key={current.slug}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
              className="flex flex-col justify-between rounded-2xl border border-line-dark bg-ink-950/50 p-8"
            >
              <div>
                <div className="font-mono-label text-[11px] uppercase text-gold-400">
                  {current.eyebrow}
                </div>
                <h3 className="mt-3 text-[24px] font-medium leading-tight tracking-tight text-paper-50">
                  {current.headline}
                </h3>
                <p className="mt-3 text-[14px] leading-relaxed text-paper-50/55">
                  {current.summary}
                </p>

                <div className="mt-6 flex flex-wrap gap-2">
                  {current.technology.map((t) => (
                    <span
                      key={t}
                      className="font-mono-label rounded-full border border-line-dark px-2.5 py-1 text-[10px] uppercase text-paper-50/50"
                    >
                      {t}
                    </span>
                  ))}
                </div>
              </div>

              <div className="mt-8 flex items-center justify-between border-t border-line-dark pt-6">
                <div className="text-[13px] text-paper-50/45">{current.outcome}</div>
                <Link
                  href={`/services/${current.slug}`}
                  className="flex shrink-0 items-center gap-1.5 text-[13px] font-medium text-paper-50 hover:text-gold-400"
                >
                  Learn more
                  <Icons.ArrowUpRight className="size-3.5" />
                </Link>
              </div>
            </motion.div>
          </AnimatePresence>
        </div>
      </Container>
    </section>
  );
}
