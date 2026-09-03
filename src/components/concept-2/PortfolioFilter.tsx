"use client";

import { useMemo, useState } from "react";
import { CaseStudyRow } from "@/components/concept-2/CaseStudyRow";
import { cn } from "@/lib/cn";
import type { CaseStudy } from "@/lib/site-data";

export function PortfolioFilter({ caseStudies }: { caseStudies: CaseStudy[] }) {
  const industries = useMemo(
    () => ["All", ...Array.from(new Set(caseStudies.map((c) => c.industry)))],
    [caseStudies]
  );
  const [active, setActive] = useState("All");

  const filtered = active === "All" ? caseStudies : caseStudies.filter((c) => c.industry === active);

  return (
    <div>
      <div
        role="group"
        aria-label="Filter case studies by industry"
        className="flex flex-wrap gap-x-8 gap-y-3 border-b border-neutral-200 pb-8"
      >
        {industries.map((industry) => (
          <button
            key={industry}
            type="button"
            onClick={() => setActive(industry)}
            aria-pressed={active === industry}
            className={cn(
              "rounded-sm text-sm uppercase tracking-[0.15em] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900",
              active === industry ? "text-neutral-900" : "text-neutral-400 hover:text-neutral-700"
            )}
          >
            {industry}
          </button>
        ))}
      </div>
      <div className="mt-2">
        {filtered.map((cs) => (
          <CaseStudyRow key={cs.slug} caseStudy={cs} />
        ))}
      </div>
    </div>
  );
}
