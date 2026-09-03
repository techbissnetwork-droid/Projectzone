import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { CaseStudyVisual } from "@/components/shared/CaseStudyVisual";
import { caseStudies } from "@/lib/data/caseStudies";
import { cn } from "@/lib/utils/cn";

const featured = caseStudies.slice(0, 3);

export function CaseStudiesPreview() {
  return (
    <section className="py-24 md:py-32">
      <Container>
        <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <SectionHeading
            eyebrow="Selected Work"
            title="Real businesses. Real digital transformations."
            className="md:max-w-xl"
          />
          <Reveal delay={0.15}>
            <Button href="/work" variant="secondary">
              View All Work
            </Button>
          </Reveal>
        </div>

        <div className="mt-16 flex flex-col gap-16 md:gap-24">
          {featured.map((study, i) => (
            <Reveal key={study.slug} delay={0.05}>
              <Link
                href={`/work#${study.slug}`}
                className={cn(
                  "group grid items-center gap-8 md:grid-cols-2 md:gap-14",
                )}
              >
                <div className={cn(i % 2 === 1 && "md:order-2")}>
                  <CaseStudyVisual
                    theme={study.visualTheme}
                    color={study.color}
                    label={study.brand}
                    className="transition-transform duration-500 group-hover:scale-[1.015]"
                  />
                </div>
                <div className={cn(i % 2 === 1 && "md:order-1")}>
                  <div className="flex items-center gap-3 text-eyebrow text-paper-faint">
                    <span>{study.businessType}</span>
                    <span>·</span>
                    <span>{study.year}</span>
                  </div>
                  <h3 className="text-h3 mt-4 font-medium text-paper">
                    {study.brand}
                  </h3>
                  <p className="mt-3 max-w-md text-[0.95rem] leading-relaxed text-paper-dim">
                    {study.tagline}
                  </p>
                  <div className="mt-6 flex flex-wrap gap-x-8 gap-y-3">
                    {study.results.slice(0, 2).map((r) => (
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
                  <span className="mt-7 inline-flex items-center gap-2 text-sm font-medium text-paper transition-colors group-hover:text-gold-bright">
                    Read the case study
                    <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                  </span>
                </div>
              </Link>
            </Reveal>
          ))}
        </div>
      </Container>
    </section>
  );
}
