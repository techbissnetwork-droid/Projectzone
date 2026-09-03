"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { TrendingUp } from "lucide-react";
import type { CaseStudy } from "@/lib/site-data";
import { cn } from "@/lib/cn";

// Deterministic per-card sparkline points so the "Project Health" accent
// varies visually across cards without implying real measured data.
function sparkPoints(seed: number) {
  const base = [40, 55, 48, 62, 58, 70, 66, 78];
  return base.map((v, i) => v + ((seed + i) % 5) * 3);
}

function Sparkline({ seed }: { seed: number }) {
  const points = sparkPoints(seed);
  const max = Math.max(...points);
  const min = Math.min(...points);
  const w = 100;
  const h = 28;
  const path = points
    .map((p, i) => {
      const x = (i / (points.length - 1)) * w;
      const y = h - ((p - min) / (max - min || 1)) * h;
      return `${i === 0 ? "M" : "L"}${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(" ");

  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="h-7 w-24" aria-hidden="true">
      <path d={path} fill="none" stroke="url(#spark-gradient)" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
      <defs>
        <linearGradient id="spark-gradient" x1="0" x2="1" y1="0" y2="0">
          <stop offset="0%" stopColor="#a78bfa" />
          <stop offset="100%" stopColor="#34d399" />
        </linearGradient>
      </defs>
    </svg>
  );
}

export function CaseStudyCard({ study, index = 0 }: { study: CaseStudy; index?: number }) {
  const [expanded, setExpanded] = useState(false);

  return (
    <motion.div
      layout
      className={cn(
        "group relative flex flex-col rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-lg shadow-black/10 transition-colors hover:border-white/20",
        index % 2 === 1 && "lg:translate-y-4"
      )}
    >
      <div className="flex items-center justify-between gap-3">
        <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-violet-300">
          {study.industry}
        </span>
        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500" title="Illustrative product-feel accent, not measured data">
          <TrendingUp className="h-3 w-3 text-emerald-400" aria-hidden="true" />
          Illustrative
        </span>
      </div>

      <h3 className="font-display mt-4 text-lg font-semibold text-white">{study.title}</h3>
      <p className="mt-2 text-sm text-slate-400">{study.summary}</p>

      <div className="mt-4 flex flex-wrap gap-1.5">
        {study.services.map((s) => (
          <span key={s} className="rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1 text-[11px] text-slate-400">
            {s}
          </span>
        ))}
      </div>

      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        aria-expanded={expanded}
        className="mt-5 flex min-h-[44px] w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-4 py-3 text-left transition-colors hover:bg-white/[0.05] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
      >
        <span className="text-xs font-semibold text-slate-300">Project health (illustrative)</span>
        <Sparkline seed={index * 7 + 3} />
      </button>

      {expanded ? (
        <p className="mt-3 rounded-xl bg-emerald-400/5 px-4 py-3 text-xs leading-relaxed text-emerald-200/90">
          {study.outcome}
        </p>
      ) : null}
    </motion.div>
  );
}
