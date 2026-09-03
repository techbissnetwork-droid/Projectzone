import type { Metadata } from "next";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { FeaturedCase, CaseRow } from "@/components/work/work-index";
import { caseStudies } from "@/lib/data/work";

export const metadata: Metadata = {
  title: "Work",
  description:
    "Real businesses TECHBISS has taken online — e-commerce, healthcare, education, hospitality and more, each with the problem, the build, and the results.",
};

export default function WorkPage() {
  const [featured, ...rest] = caseStudies;

  return (
    <>
      <section className="pt-36 sm:pt-40 md:pt-44">
        <Container>
          <Reveal>
            <Eyebrow>Selected Work</Eyebrow>
            <h1 className="mt-6 max-w-[18ch] text-balance text-[38px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[56px] md:text-[64px]">
              Real businesses. Real transformation.
            </h1>
            <p className="mt-6 max-w-[60ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Six businesses, six different starting points — every one moved from
              disconnected, offline operations to a fast, reliable digital system built
              around how they actually work.
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="mt-14 sm:mt-20">
        <Container>
          <FeaturedCase project={featured} />

          <div className="mt-8 grid grid-cols-1 gap-6 sm:mt-10 lg:grid-cols-2 lg:gap-8">
            {rest.map((project, i) => (
              <CaseRow
                key={project.slug}
                project={project}
                index={i + 1}
                align={i % 2 === 0 ? "up" : "down"}
              />
            ))}
          </div>
        </Container>
      </section>

      <section className="mt-24 border-t border-[var(--color-border)] py-24 sm:mt-32 sm:py-32">
        <Container className="text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[20ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Your business could be the next case study.
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/process" variant="secondary" size="lg">
              See How We Work
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
