import type { Metadata } from "next";
import { caseStudies } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section } from "@/components/concept-1/Section";
import { PortfolioFilter } from "@/components/concept-1/PortfolioFilter";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "Portfolio",
  description:
    "A look at the kinds of digital transformations TECHBISS delivers across retail, healthcare, logistics, and hospitality.",
};

export default function ConceptOnePortfolioPage() {
  return (
    <>
      <PageHero
        eyebrow="Portfolio"
        title="Where technology meets real business outcomes."
        description="Every engagement starts with a business problem, not a template. Here's a look at the kinds of work we take on across industries."
      />

      <Section className="pt-0">
        <PortfolioFilter studies={caseStudies} />
      </Section>

      <CtaBanner
        title="Your project could be next."
        description="Tell us what you're working on and we'll show you how we'd approach it."
      />
    </>
  );
}
