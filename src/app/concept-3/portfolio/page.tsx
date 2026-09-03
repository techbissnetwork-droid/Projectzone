import type { Metadata } from "next";
import { caseStudies } from "@/lib/site-data";
import { Section, Eyebrow } from "@/components/concept-3/Section";
import { Reveal } from "@/components/concept-3/Reveal";
import { PortfolioGrid } from "@/components/concept-3/PortfolioGrid";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Portfolio",
  description:
    "Illustrative examples of the kinds of digital transformation projects TECHBISS delivers — filterable by industry.",
};

export default function PortfolioPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Portfolio">
        <Reveal className="max-w-3xl">
          <Eyebrow>Portfolio</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            The shape of the work we do.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            These are illustrative project profiles representing the type, scope, and service mix of engagements we
            run — filter by industry to see relevant examples. Outcome figures are placeholders pending verified
            client metrics.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Case studies" className="pt-0">
        <PortfolioGrid caseStudies={caseStudies} />
      </Section>

      <CtaSection
        title="See your business's project profile here next."
        description="Tell us what you're building and we'll show you how a similar engagement typically runs."
      />
    </>
  );
}
