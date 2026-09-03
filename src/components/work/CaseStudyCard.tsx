"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowUpRight } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { revealItem } from "@/components/ui/Reveal";
import type { CaseStudy } from "@/lib/types";

export function CaseStudyCard({ study }: { study: CaseStudy }) {
  return (
    <motion.div variants={revealItem}>
      <Link
        href={`/work/${study.slug}`}
        className="focus-ring group flex h-full flex-col overflow-hidden rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
      >
        <div
          className="relative flex h-48 flex-col justify-between p-5"
          style={{ background: `linear-gradient(135deg, ${study.gradient[0]}, ${study.gradient[1]})` }}
        >
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_0%,rgba(255,255,255,0.18),transparent_55%)] transition-transform duration-500 group-hover:scale-110" />
          <div className="relative flex items-center justify-between">
            <Badge className="border-white/20 bg-black/25 text-white backdrop-blur">{study.industry}</Badge>
            <ArrowUpRight className="h-4 w-4 text-white/80 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
          </div>
          <p className="relative text-sm font-medium text-white/90">{study.client}</p>
        </div>
        <div className="flex flex-1 flex-col p-6">
          <h3 className="text-lg font-medium leading-snug text-(--color-ink)">{study.title}</h3>
          <p className="mt-2.5 flex-1 text-sm leading-relaxed text-(--color-ink-muted) line-clamp-2">{study.summary}</p>
          <div className="mt-5 flex items-center gap-4 border-t border-(--color-border) pt-4">
            {study.results.slice(0, 2).map((r) => (
              <div key={r.label}>
                <p className="text-base font-medium text-(--color-ink)">{r.value}</p>
                <p className="text-xs text-(--color-ink-faint)">{r.label}</p>
              </div>
            ))}
          </div>
        </div>
      </Link>
    </motion.div>
  );
}
