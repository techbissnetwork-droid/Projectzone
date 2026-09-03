import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { RevealGroup } from "@/components/ui/Reveal";
import { SolutionCard } from "@/components/solutions/SolutionCard";
import { CaseStudyHighlight } from "@/components/home/CaseStudyHighlight";
import { CtaBanner } from "@/components/home/CtaBanner";
import { solutions } from "@/lib/data/solutions";

export const metadata: Metadata = {
  title: "Solutions",
  description: "Industry-specific technology solutions for enterprise, startups, e-commerce, fintech, healthcare and SaaS.",
};

export default function SolutionsPage() {
  return (
    <>
      <PageHero
        eyebrow="Solutions"
        title="Built for how your industry actually works."
        description="Generic platforms create generic outcomes. We tailor architecture, compliance and workflow to the realities of your industry from day one."
      />

      <Section size="tight">
        <Container>
          <RevealGroup className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {solutions.map((solution) => (
              <SolutionCard key={solution.slug} solution={solution} />
            ))}
          </RevealGroup>
        </Container>
      </Section>

      <Section theme="light">
        <Container>
          <SectionHeading
            eyebrow="Why it matters"
            title="Compliance and scale aren't afterthoughts."
            description="Every industry solution starts with the regulatory, operational and technical constraints that are unique to it — not a template we hope fits."
          />
        </Container>
      </Section>

      <CaseStudyHighlight />
      <CtaBanner />
    </>
  );
}
