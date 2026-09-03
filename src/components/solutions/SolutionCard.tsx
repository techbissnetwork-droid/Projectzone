"use client";

import { motion } from "framer-motion";
import { Check } from "lucide-react";
import Link from "next/link";
import { Icon } from "@/lib/icon-map";
import { revealItem } from "@/components/ui/Reveal";
import type { Solution } from "@/lib/types";

export function SolutionCard({ solution }: { solution: Solution }) {
  return (
    <motion.div
      variants={revealItem}
      className="group flex flex-col rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-7 transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
    >
      <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-(--radius-md) bg-[linear-gradient(135deg,rgba(75,91,255,0.12),rgba(23,195,255,0.12))] text-(--color-accent)">
        <Icon name={solution.icon} className="h-5 w-5" />
      </div>
      <h3 className="text-lg font-medium text-(--color-ink)">{solution.name}</h3>
      <p className="mt-0.5 text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">{solution.audience}</p>
      <p className="mt-3 text-sm leading-relaxed text-(--color-ink-muted)">{solution.description}</p>

      <ul className="mt-5 flex flex-col gap-2.5">
        {solution.highlights.map((h) => (
          <li key={h} className="flex items-start gap-2 text-sm text-(--color-ink)">
            <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-(--color-accent-2)" />
            {h}
          </li>
        ))}
      </ul>

      <div className="mt-6 flex items-center justify-between border-t border-(--color-border) pt-5">
        <div>
          <p className="text-xl font-medium text-(--color-ink)">{solution.stat.value}</p>
          <p className="text-xs text-(--color-ink-faint)">{solution.stat.label}</p>
        </div>
        <Link
          href="/contact"
          className="focus-ring text-sm font-medium text-(--color-accent-2) opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        >
          Talk to us →
        </Link>
      </div>
    </motion.div>
  );
}
