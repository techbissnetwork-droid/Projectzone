import type { Metadata } from "next";
import { caseStudies } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { PortfolioFilter } from "@/components/concept-2/PortfolioFilter";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Portfolio",
  description: "Selected engagements across retail, healthcare, logistics, and hospitality.",
};

export default function PortfolioPage() {
  return (
    <>
      <PageHero
        eyebrow="Portfolio"
        title="A record of outcomes, not just output."
        description="Each engagement below reflects a specific business problem and the digital infrastructure built to solve it."
      />

      <Section>
        <PortfolioFilter caseStudies={caseStudies} />
      </Section>

      <CtaSection
        title="Your project could be next."
        primaryLabel="Start Your Project"
        secondaryLabel="See our services"
        secondaryHref="/concept-2/services"
      />
    </>
  );
}
