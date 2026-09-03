"use client";

import { useMemo, useState } from "react";
import { motion } from "framer-motion";
import type { CaseStudy } from "@/lib/site-data";
import { CaseStudyCard } from "./CaseStudyCard";
import { cn } from "@/lib/cn";

export function PortfolioGrid({ caseStudies }: { caseStudies: CaseStudy[] }) {
  const industries = useMemo(() => Array.from(new Set(caseStudies.map((c) => c.industry))), [caseStudies]);
  const [filter, setFilter] = useState<string | "All">("All");
  const filtered = filter === "All" ? caseStudies : caseStudies.filter((c) => c.industry === filter);

  return (
    <div>
      <div role="tablist" aria-label="Filter case studies by industry" className="flex flex-wrap gap-2">
        <button
          type="button"
          role="tab"
          aria-selected={filter === "All"}
          onClick={() => setFilter("All")}
          className={cn(
            "min-h-[44px] rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
            filter === "All" ? "border-white/20 bg-white text-[#0b0c14]" : "border-white/10 bg-white/[0.03] text-slate-400 hover:text-white"
          )}
        >
          All Industries
        </button>
        {industries.map((industry) => (
          <button
            key={industry}
            type="button"
            role="tab"
            aria-selected={filter === industry}
            onClick={() => setFilter(industry)}
            className={cn(
              "min-h-[44px] rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
              filter === industry ? "border-white/20 bg-white text-[#0b0c14]" : "border-white/10 bg-white/[0.03] text-slate-400 hover:text-white"
            )}
          >
            {industry}
          </button>
        ))}
      </div>

      <motion.div layout className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((study, i) => (
          <CaseStudyCard key={study.slug} study={study} index={i} />
        ))}
      </motion.div>
    </div>
  );
}
