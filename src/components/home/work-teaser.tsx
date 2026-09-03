import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { caseStudies } from "@/lib/data/work";

const featured = caseStudies.slice(0, 3);

export function WorkTeaser() {
  return (
    <section className="py-24 sm:py-32">
      <Container>
        <div className="flex flex-col items-end justify-between gap-6 sm:flex-row">
          <Reveal className="max-w-[560px]">
            <Eyebrow>Selected Work</Eyebrow>
            <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
              Real businesses. Real transformation.
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="hidden shrink-0 sm:block">
            <Button href="/work" variant="secondary">
              View All Work
            </Button>
          </Reveal>
        </div>

        <div className="mt-12 flex flex-col divide-y divide-[var(--color-border)]">
          {featured.map((project, i) => (
            <Reveal key={project.slug} delay={i * 0.06}>
              <Link
                href={`/work/${project.slug}`}
                className="group grid grid-cols-1 items-center gap-6 py-8 sm:grid-cols-[100px_1fr_auto] sm:gap-8"
              >
                <span className="font-mono-label text-[13px] text-[var(--color-ink-faint)]">
                  {String(i + 1).padStart(2, "0")} / {project.year}
                </span>
                <div>
                  <div className="flex flex-wrap items-center gap-3">
                    <h3 className="text-[19px] font-medium tracking-[-0.01em] text-[var(--color-ink)] transition-colors group-hover:text-[var(--color-accent-ink)] sm:text-[24px]">
                      {project.title}
                    </h3>
                  </div>
                  <p className="mt-2 max-w-[60ch] text-[13.5px] text-[var(--color-ink-faint)]">
                    {project.category} · {project.client}
                  </p>
                </div>
                <div className="flex items-center gap-6 sm:justify-end">
                  <div className="hidden gap-6 sm:flex">
                    {project.results.slice(0, 2).map((r) => (
                      <div key={r.label} className="text-right">
                        <div className="text-[18px] font-medium" style={{ color: project.accent }}>
                          {r.stat}
                        </div>
                        <div className="text-[11px] text-[var(--color-ink-faint)]">{r.label}</div>
                      </div>
                    ))}
                  </div>
                  <ArrowUpRight className="size-5 shrink-0 text-[var(--color-ink-faint)] transition-all duration-300 group-hover:translate-x-1 group-hover:-translate-y-1 group-hover:text-[var(--color-ink)]" />
                </div>
              </Link>
            </Reveal>
          ))}
        </div>

        <Reveal className="mt-10 sm:hidden">
          <Button href="/work" variant="secondary" className="w-full">
            View All Work
          </Button>
        </Reveal>
      </Container>
    </section>
  );
}
