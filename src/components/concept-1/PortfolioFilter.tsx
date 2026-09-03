"use client";

import { useMemo, useState } from "react";
import type { CaseStudy } from "@/lib/site-data";
import { CaseStudyCard } from "@/components/concept-1/CaseStudyCard";
import { Reveal } from "@/components/concept-1/Reveal";
import { cn } from "@/lib/cn";

export function PortfolioFilter({ studies }: { studies: CaseStudy[] }) {
  const industries = useMemo(
    () => ["All", ...Array.from(new Set(studies.map((study) => study.industry)))],
    [studies]
  );
  const [active, setActive] = useState("All");

  const filtered = useMemo(
    () => (active === "All" ? studies : studies.filter((study) => study.industry === active)),
    [active, studies]
  );

  return (
    <div>
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter case studies by industry">
        {industries.map((industry) => {
          const isActive = industry === active;
          return (
            <button
              key={industry}
              type="button"
              aria-pressed={isActive}
              onClick={() => setActive(industry)}
              className={cn(
                "rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70",
                isActive
                  ? "bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500 text-neutral-950"
                  : "border border-white/10 bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-neutral-50"
              )}
            >
              {industry}
            </button>
          );
        })}
      </div>

      <div className="mt-10 grid gap-6 sm:grid-cols-2">
        {filtered.map((study, index) => (
          <Reveal key={study.slug} delay={index * 0.05}>
            <CaseStudyCard study={study} className="h-full" />
          </Reveal>
        ))}
      </div>
    </div>
  );
}
