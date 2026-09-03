import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { CaseStudiesBrowser } from "@/components/work/CaseStudiesBrowser";
import { CtaBanner } from "@/components/home/CtaBanner";

export const metadata: Metadata = {
  title: "Work",
  description: "Case studies from TECHBISS engagements across fintech, e-commerce, healthcare, SaaS and enterprise.",
};

export default function WorkPage() {
  return (
    <>
      <PageHero
        eyebrow="Our Work"
        title="Outcomes, not just deliverables."
        description="A selection of engagements where we shipped measurable impact — not just a project on time and on budget."
      />
      <Section size="tight">
        <Container size="wide">
          <CaseStudiesBrowser />
        </Container>
      </Section>
      <CtaBanner />
    </>
  );
}
