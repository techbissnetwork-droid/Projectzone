"use client";

import Link from "next/link";
import * as Icons from "lucide-react";
import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { solutions } from "@/lib/data/solutions";
import { motion } from "framer-motion";

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

const featured = solutions.slice(0, 5);

export function SolutionsTeaser() {
  return (
    <section className="border-b border-line-dark bg-ink-900/40 py-24 sm:py-32">
      <Container wide>
        <Reveal className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <div>
            <Eyebrow>Solutions By Industry</Eyebrow>
            <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
              Real businesses. Real transformation.
            </h2>
          </div>
          <Button href="/solutions" arrow variant="ghost" className="shrink-0">
            All Solutions
          </Button>
        </Reveal>

        <RevealGroup className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {featured.map((s) => (
            <motion.div key={s.slug} variants={revealItem}>
              <Link
                href={`/solutions/${s.slug}`}
                className="group flex h-full flex-col justify-between rounded-2xl border border-line-dark bg-ink-950/40 p-6 transition-colors hover:border-line-dark-strong"
              >
                <div>
                  <span className="flex size-10 items-center justify-center rounded-lg border border-line-dark bg-ink-900 text-gold-400">
                    <Icon name={s.icon} className="size-4.5" />
                  </span>
                  <h3 className="mt-5 text-[16px] font-medium text-paper-50">{s.name}</h3>
                  <p className="mt-2 text-[13px] leading-relaxed text-paper-50/45">
                    {s.transformation[0].from} → {s.transformation[0].to}
                  </p>
                </div>
                <Icons.ArrowUpRight className="mt-6 size-4 text-paper-50/30 transition-colors group-hover:text-gold-400" />
              </Link>
            </motion.div>
          ))}
        </RevealGroup>
      </Container>
    </section>
  );
}
