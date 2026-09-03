import type { Metadata } from "next";
import { Quote } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { CaseStudyVisual } from "@/components/shared/CaseStudyVisual";
import { caseStudies } from "@/lib/data/caseStudies";
import { getServiceBySlug } from "@/lib/data/services";
import { cn } from "@/lib/utils/cn";

export const metadata: Metadata = {
  title: "Work",
  description:
    "Real businesses TECHBISS has taken from offline to fully digital — the problems, the systems we built, and the measurable results.",
};

const [featured, ...rest] = caseStudies;

export default function WorkPage() {
  return (
    <>
      <PageHero
        eyebrow="Selected Work"
        title="Real businesses. Real digital transformations."
        lead="Every project here started offline, or held back by systems that couldn't grow. Here's what changed — and what it delivered."
        stats={[
          { value: "6", label: "Featured transformations" },
          { value: "3.1×", label: "Avg. growth metric" },
        ]}
      />

      <section className="border-t border-line py-16 md:py-20" id={featured.slug}>
        <Container>
          <Reveal>
            <div className="overflow-hidden rounded-2xl border border-line">
              <CaseStudyVisual
                theme={featured.visualTheme}
                color={featured.color}
                label={featured.brand}
                className="aspect-[16/9] rounded-none border-0 sm:aspect-[21/9]"
              />
            </div>
          </Reveal>

          <div className="mt-10 grid gap-10 lg:grid-cols-[1.4fr_1fr] lg:gap-16">
            <div>
              <Reveal>
                <div className="flex flex-wrap items-center gap-3 text-eyebrow text-paper-faint">
                  <span className="rounded-full border border-gold/40 bg-gold-dim px-3 py-1 text-gold-bright">
                    Featured
                  </span>
                  <span>{featured.businessType}</span>
                  <span>·</span>
                  <span>{featured.year}</span>
                </div>
                <h2 className="text-h2 mt-4 font-medium text-paper">
                  {featured.brand}
                </h2>
                <p className="text-lead mt-3 max-w-xl text-balance text-paper-dim">
                  {featured.tagline}
                </p>
              </Reveal>

              <Reveal delay={0.1}>
                <div className="mt-8 grid gap-8 sm:grid-cols-2">
                  <div>
                    <Eyebrow tone="neutral">The Problem</Eyebrow>
                    <p className="mt-3 text-sm leading-relaxed text-paper-dim">
                      {featured.problem}
                    </p>
                  </div>
                  <div>
                    <Eyebrow tone="gold">The Solution</Eyebrow>
                    <p className="mt-3 text-sm leading-relaxed text-paper-dim">
                      {featured.solution}
                    </p>
                  </div>
                </div>
              </Reveal>

              <Reveal delay={0.16}>
                <blockquote className="mt-10 border-l-2 border-gold/50 pl-6">
                  <Quote className="size-5 text-gold-bright" aria-hidden />
                  <p className="mt-3 text-lg italic leading-relaxed text-paper">
                    &ldquo;{featured.quote.text}&rdquo;
                  </p>
                  <footer className="mt-3 text-sm text-paper-faint">
                    {featured.quote.author}, {featured.quote.role}
                  </footer>
                </blockquote>
              </Reveal>
            </div>

            <Reveal delay={0.12}>
              <div className="flex flex-col gap-8 rounded-2xl border border-line bg-ink-raised-2 p-8">
                <div>
                  <Eyebrow tone="signal">Results</Eyebrow>
                  <div className="mt-5 flex flex-col gap-5">
                    {featured.results.map((r) => (
                      <div key={r.label}>
                        <p className="text-2xl font-semibold text-gold-bright">
                          {r.value}
                        </p>
                        <p className="mt-0.5 text-xs text-paper-faint">{r.label}</p>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="border-t border-line pt-6">
                  <Eyebrow tone="neutral">Technologies</Eyebrow>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {featured.technologies.map((t) => (
                      <span
                        key={t}
                        className="rounded-full border border-line-strong px-3 py-1 text-xs text-paper-dim"
                      >
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            </Reveal>
          </div>
        </Container>
      </section>

      {rest.map((study, i) => (
        <section
          key={study.slug}
          id={study.slug}
          className={cn(
            "scroll-mt-24 border-t border-line py-20 md:py-28",
            i % 2 === 0 && "bg-ink-raised",
          )}
        >
          <Container>
            <div
              className={cn(
                "grid items-center gap-10 lg:grid-cols-2 lg:gap-16",
              )}
            >
              <Reveal className={cn(i % 2 === 1 && "lg:order-2")}>
                <CaseStudyVisual
                  theme={study.visualTheme}
                  color={study.color}
                  label={study.brand}
                />
              </Reveal>

              <div className={cn(i % 2 === 1 && "lg:order-1")}>
                <Reveal>
                  <div className="flex items-center gap-3 text-eyebrow text-paper-faint">
                    <span>{study.businessType}</span>
                    <span>·</span>
                    <span>{study.year}</span>
                  </div>
                  <h2 className="text-h3 mt-3 font-medium text-paper">
                    {study.brand}
                  </h2>
                </Reveal>

                <Reveal delay={0.08}>
                  <p className="mt-4 text-sm leading-relaxed text-paper-dim">
                    <span className="text-paper">Problem — </span>
                    {study.problem}
                  </p>
                  <p className="mt-3 text-sm leading-relaxed text-paper-dim">
                    <span
                      className={cn(
                        study.color === "gold" ? "text-gold-bright" : "text-signal-bright",
                      )}
                    >
                      Solution —{" "}
                    </span>
                    {study.solution}
                  </p>
                </Reveal>

                <Reveal delay={0.14}>
                  <div className="mt-6 flex flex-wrap gap-x-8 gap-y-4 border-y border-line py-5">
                    {study.results.map((r) => (
                      <div key={r.label}>
                        <p
                          className={cn(
                            "text-xl font-semibold",
                            study.color === "gold" ? "text-gold-bright" : "text-signal-bright",
                          )}
                        >
                          {r.value}
                        </p>
                        <p className="mt-0.5 text-xs text-paper-faint">{r.label}</p>
                      </div>
                    ))}
                  </div>
                </Reveal>

                <Reveal delay={0.18}>
                  <div className="mt-5 flex flex-wrap gap-2">
                    {study.services.map((slug) => {
                      const s = getServiceBySlug(slug);
                      if (!s) return null;
                      return (
                        <span
                          key={slug}
                          className="rounded-full border border-line-strong px-3 py-1 text-xs text-paper-faint"
                        >
                          {s.name}
                        </span>
                      );
                    })}
                  </div>
                  <p className="mt-6 text-sm italic leading-relaxed text-paper-dim">
                    &ldquo;{study.quote.text}&rdquo;
                    <span className="mt-1 block not-italic text-xs text-paper-faint">
                      — {study.quote.author}, {study.quote.role}
                    </span>
                  </p>
                </Reveal>
              </div>
            </div>
          </Container>
        </section>
      ))}

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone="gold">Your Business Could Be Next</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Let&apos;s build something worth featuring here.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/process" variant="secondary" size="lg">
              See How We Work
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}
