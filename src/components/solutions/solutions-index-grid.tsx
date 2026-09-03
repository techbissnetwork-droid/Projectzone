import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Reveal } from "@/components/ui/reveal";
import { ChainFlow } from "@/components/solutions/chain-flow";
import { solutions } from "@/lib/data/solutions";

export function SolutionsIndexGrid() {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {solutions.map((solution, i) => (
        <Reveal key={solution.slug} delay={Math.min(i * 0.04, 0.24)}>
          <Link
            href={`/solutions/${solution.slug}`}
            className="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-7 transition-colors duration-300 hover:border-white/25 sm:p-8"
          >
            <div
              aria-hidden
              className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"
              style={{ background: solution.accent }}
            />
            <div className="relative flex items-start justify-between gap-4">
              <div>
                <span
                  className="inline-flex rounded-full px-3 py-1 text-[12px] font-medium"
                  style={{ backgroundColor: `${solution.accent}22`, color: solution.accent }}
                >
                  {solution.name}
                </span>
                <h2 className="mt-4 text-[19px] font-medium tracking-[-0.01em] text-[var(--color-ink)] sm:text-[22px]">
                  {solution.tagline}
                </h2>
              </div>
              <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[var(--color-border-strong)] text-[var(--color-ink-faint)] transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:border-[var(--color-ink)] group-hover:text-[var(--color-ink)]">
                <ArrowUpRight className="size-4" />
              </span>
            </div>

            <p className="relative mt-3 max-w-[48ch] text-[13.5px] leading-relaxed text-[var(--color-ink-faint)]">
              {solution.problem}
            </p>

            <div className="relative mt-6 border-t border-[var(--color-border)] pt-6">
              <ChainFlow solution={solution} size="sm" />
            </div>
          </Link>
        </Reveal>
      ))}
    </div>
  );
}
