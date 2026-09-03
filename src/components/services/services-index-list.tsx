import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Reveal } from "@/components/ui/reveal";
import { services } from "@/lib/data/services";

export function ServicesIndexList() {
  return (
    <div className="flex flex-col divide-y divide-[var(--color-border)] border-t border-[var(--color-border)]">
      {services.map((service, i) => (
        <Reveal key={service.slug} delay={Math.min(i * 0.04, 0.3)}>
          <Link
            href={`/services/${service.slug}`}
            className="group relative grid grid-cols-1 items-center gap-4 overflow-hidden py-8 transition-colors duration-300 sm:grid-cols-[88px_1fr_auto] sm:gap-8 sm:py-9"
          >
            <div
              aria-hidden
              className="pointer-events-none absolute -right-24 top-1/2 size-72 -translate-y-1/2 rounded-full opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"
              style={{ background: service.accent }}
            />

            <span
              className="relative font-serif-display text-[40px] italic leading-none text-[var(--color-ink-faint)] transition-colors duration-300 group-hover:text-[var(--color-ink-muted)] sm:text-[46px]"
              aria-hidden
            >
              {service.index}
            </span>

            <div className="relative">
              <div className="flex items-center gap-2.5">
                <span className="size-1.5 shrink-0 rounded-full" style={{ backgroundColor: service.accent }} />
                <h2 className="text-[19px] font-medium tracking-[-0.01em] text-[var(--color-ink)] transition-colors duration-300 group-hover:text-[var(--color-accent-ink)] sm:text-[23px]">
                  {service.name}
                </h2>
              </div>
              <p className="mt-2 max-w-[52ch] text-pretty text-[14px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[15px]">
                {service.tagline}
              </p>
              <div className="mt-4 flex flex-wrap gap-1.5">
                {service.tech.slice(0, 3).map((t) => (
                  <span
                    key={t}
                    className="rounded-full border border-[var(--color-border)] bg-white/[0.03] px-2.5 py-1 text-[11px] text-[var(--color-ink-faint)]"
                  >
                    {t}
                  </span>
                ))}
              </div>
            </div>

            <div className="relative flex items-center justify-start gap-3 sm:justify-end">
              <span className="hidden text-[13px] text-[var(--color-ink-faint)] transition-colors duration-300 group-hover:text-[var(--color-ink-muted)] sm:inline">
                View Service
              </span>
              <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[var(--color-border-strong)] text-[var(--color-ink-faint)] transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:border-[var(--color-ink)] group-hover:text-[var(--color-ink)]">
                <ArrowUpRight className="size-4" />
              </span>
            </div>
          </Link>
        </Reveal>
      ))}
    </div>
  );
}
