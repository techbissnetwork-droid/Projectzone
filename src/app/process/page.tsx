import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Accordion } from "@/components/ui/Accordion";
import { ProcessTimeline } from "@/components/process/ProcessTimeline";
import { CtaBanner } from "@/components/home/CtaBanner";
import { generalFaqs } from "@/lib/data/faqs";

export const metadata: Metadata = {
  title: "Process",
  description: "Discover, design, build, launch, grow — the five-phase process behind every TECHBISS engagement.",
};

export default function ProcessPage() {
  return (
    <>
      <PageHero
        eyebrow="Our Process"
        title="A disciplined process, built for momentum."
        description="No black boxes between kickoff and launch. Every phase has a clear owner, a defined deliverable and a demoable outcome."
      />

      <Section size="tight">
        <Container size="narrow">
          <ProcessTimeline />
        </Container>
      </Section>

      <Section theme="light">
        <Container size="narrow">
          <SectionHeading eyebrow="FAQ" title="Questions about how we work." />
          <div className="mt-10">
            <Accordion items={generalFaqs} />
          </div>
        </Container>
      </Section>

      <CtaBanner />
    </>
  );
}
