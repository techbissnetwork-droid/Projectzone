"use client";

import * as React from "react";
import { RevealGroup } from "@/components/ui/Reveal";
import { CaseStudyCard } from "@/components/work/CaseStudyCard";
import { cn } from "@/lib/utils";
import { caseStudies } from "@/lib/data/caseStudies";

const industries = ["All", ...Array.from(new Set(caseStudies.map((s) => s.industry)))];

export function CaseStudiesBrowser() {
  const [industry, setIndustry] = React.useState("All");
  const filtered = industry === "All" ? caseStudies : caseStudies.filter((s) => s.industry === industry);

  return (
    <div>
      <div className="flex flex-wrap justify-center gap-2">
        {industries.map((i) => (
          <button
            key={i}
            type="button"
            onClick={() => setIndustry(i)}
            className={cn(
              "focus-ring rounded-full border px-3.5 py-1.5 text-sm transition-colors",
              industry === i
                ? "border-(--color-accent) bg-(--color-accent)/12 text-(--color-accent-2)"
                : "border-(--color-border-strong) text-(--color-ink-muted) hover:text-(--color-ink)",
            )}
          >
            {i}
          </button>
        ))}
      </div>

      <RevealGroup key={industry} className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((study) => (
          <CaseStudyCard key={study.slug} study={study} />
        ))}
      </RevealGroup>
    </div>
  );
}
