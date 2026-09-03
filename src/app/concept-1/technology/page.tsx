import type { Metadata } from "next";
import { techCapabilities } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { TechTabs } from "@/components/concept-1/TechTabs";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "Technology",
  description:
    "The technology stack behind TECHBISS builds — frontend, backend, mobile, cloud, security, and business systems.",
};

export default function ConceptOneTechnologyPage() {
  return (
    <>
      <PageHero
        eyebrow="Technology"
        title="A modern stack, chosen for the long run."
        description="We build on technology that scales with your business, not against it — proven frameworks, secure infrastructure, and tooling that keeps performance high as usage grows."
      />

      <Section className="pt-0">
        <Reveal>
          <TechTabs categories={techCapabilities} />
        </Reveal>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="Why It Matters"
          title="The stack you can't see is the one that protects you."
          description="A modern technology foundation isn't a marketing detail — it's what determines whether your platform stays fast as traffic grows, stays secure as threats evolve, and stays maintainable as your team changes. We choose proven, well-supported technologies over trends, so what we build for you keeps working long after launch."
        />
      </Section>

      <CtaBanner
        title="Curious how this applies to your project?"
        description="We'll walk you through the exact stack we'd recommend for your goals, budget, and timeline."
      />
    </>
  );
}
