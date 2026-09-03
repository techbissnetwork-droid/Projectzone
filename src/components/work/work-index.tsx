import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal } from "@/components/ui/reveal";
import type { CaseStudy } from "@/lib/data/work";

export function FeaturedCase({ project }: { project: CaseStudy }) {
  return (
    <Reveal>
      <Link
        href={`/work/${project.slug}`}
        className="group relative block overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8 transition-colors duration-300 hover:border-[var(--color-border-strong)] sm:p-12 lg:p-16"
      >
        <div
          aria-hidden
          className="pointer-events-none absolute -right-24 -top-24 size-[420px] rounded-full blur-[100px] transition-opacity duration-500 group-hover:opacity-80"
          style={{ background: project.accent, opacity: 0.16 }}
        />
        <div
          aria-hidden
          className="pointer-events-none absolute right-8 top-8 select-none font-serif-display text-[220px] leading-none text-[var(--color-ink)] opacity-[0.03] sm:text-[280px]"
        >
          01
        </div>

        <div className="relative">
          <div className="flex flex-wrap items-center gap-3">
            <Eyebrow>Featured Case Study</Eyebrow>
          </div>

          <h2 className="mt-7 max-w-[20ch] text-balance text-[30px] font-medium leading-[1.08] tracking-[-0.02em] sm:text-[44px] md:text-[54px]">
            {project.title}
          </h2>

          <p className="mt-5 max-w-[62ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
            {project.summary}
          </p>

          <div className="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-[var(--color-ink-faint)]">
            <span className="font-mono-label uppercase" style={{ color: project.accent }}>
              {project.category}
            </span>
            <span>{project.client}</span>
            <span>{project.industry}</span>
            <span>{project.year}</span>
          </div>

          <div className="mt-10 flex flex-wrap items-end justify-between gap-8">
            <div className="flex flex-wrap gap-8 sm:gap-12">
              {project.results.slice(0, 3).map((r) => (
                <div key={r.label}>
                  <div
                    className="text-[26px] font-medium tracking-[-0.01em] sm:text-[32px]"
                    style={{ color: project.accent }}
                  >
                    {r.stat}
                  </div>
                  <div className="mt-1 max-w-[16ch] text-[12px] text-[var(--color-ink-faint)]">
                    {r.label}
                  </div>
                </div>
              ))}
            </div>

            <span className="inline-flex shrink-0 items-center gap-2 rounded-full border border-[var(--color-border-strong)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors duration-300 group-hover:border-[var(--color-ink)]">
              Read the case study
              <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
            </span>
          </div>

          <div className="mt-8 flex flex-wrap gap-2 border-t border-[var(--color-border)] pt-6">
            {project.tech.map((t) => (
              <span
                key={t}
                className="rounded-full border border-[var(--color-border)] px-3 py-1 text-[11.5px] text-[var(--color-ink-faint)]"
              >
                {t}
              </span>
            ))}
          </div>
        </div>
      </Link>
    </Reveal>
  );
}

export function CaseRow({
  project,
  index,
  align,
}: {
  project: CaseStudy;
  index: number;
  align: "up" | "down";
}) {
  return (
    <Reveal
      delay={0.05 * (index % 3)}
      className={align === "down" ? "lg:mt-16" : ""}
    >
      <Link
        href={`/work/${project.slug}`}
        className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]/60 p-7 transition-all duration-300 hover:border-[var(--color-border-strong)] hover:bg-[var(--color-surface)] sm:p-8"
      >
        <div
          aria-hidden
          className="pointer-events-none absolute -right-16 -top-16 size-[220px] rounded-full blur-[70px] opacity-0 transition-opacity duration-500 group-hover:opacity-70"
          style={{ background: project.accent }}
        />

        <div className="relative flex items-start justify-between gap-4">
          <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
            {String(index + 1).padStart(2, "0")}
          </span>
          <ArrowUpRight className="size-5 shrink-0 text-[var(--color-ink-faint)] transition-all duration-300 group-hover:translate-x-1 group-hover:-translate-y-1 group-hover:text-[var(--color-ink)]" />
        </div>

        <div className="relative mt-4 flex flex-wrap items-center gap-3">
          <span
            className="font-mono-label text-[11px] uppercase"
            style={{ color: project.accent }}
          >
            {project.category}
          </span>
          <span className="text-[12px] text-[var(--color-ink-faint)]">{project.year}</span>
        </div>

        <h3 className="relative mt-3 text-[20px] font-medium leading-snug tracking-[-0.01em] text-[var(--color-ink)] transition-colors group-hover:text-[var(--color-accent-ink)] sm:text-[22px]">
          {project.title}
        </h3>

        <p className="relative mt-2 text-[13.5px] leading-relaxed text-[var(--color-ink-faint)]">
          {project.client}
        </p>

        <p className="relative mt-4 flex-1 text-pretty text-[13.5px] leading-relaxed text-[var(--color-ink-muted)]">
          {project.problem}
        </p>

        <div className="relative mt-6 flex items-end justify-between gap-4 border-t border-[var(--color-border)] pt-5">
          <div>
            <div className="text-[22px] font-medium" style={{ color: project.accent }}>
              {project.results[0].stat}
            </div>
            <div className="mt-1 text-[11.5px] text-[var(--color-ink-faint)]">
              {project.results[0].label}
            </div>
          </div>
          <div className="flex flex-wrap justify-end gap-1.5">
            {project.tech.slice(0, 2).map((t) => (
              <span
                key={t}
                className="rounded-full border border-[var(--color-border)] px-2.5 py-1 text-[10.5px] text-[var(--color-ink-faint)]"
              >
                {t}
              </span>
            ))}
          </div>
        </div>
      </Link>
    </Reveal>
  );
}
