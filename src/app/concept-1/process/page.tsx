import type { Metadata } from "next";
import { processSteps } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { ProcessTimeline } from "@/components/concept-1/ProcessTimeline";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "Process",
  description:
    "The six-stage TECHBISS process — discovery, design, development, testing, launch, and ongoing support.",
};

export default function ConceptOneProcessPage() {
  return (
    <>
      <PageHero
        eyebrow="Our Process"
        title="A disciplined path from idea to impact."
        description="Six stages, applied consistently across every engagement — so you always know what's happening next, and why."
      />

      <Section className="pt-0">
        <ProcessTimeline steps={processSteps} />
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="Why It Works"
          title="Structure creates speed, not slowness."
          description="A defined process removes ambiguity — for us and for you. Because every stage has a clear owner and a clear output, decisions get made faster, rework gets avoided, and your project stays on schedule without sacrificing quality. It's the same discipline whether we're building a six-page website or a full digitization roadmap."
        />
      </Section>

      <CtaBanner
        title="Ready to start at stage one?"
        description="Discovery costs you nothing but a conversation. Let's find out what your project actually needs."
      />
    </>
  );
}
