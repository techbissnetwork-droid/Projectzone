import type { Metadata } from "next";
import { techCapabilities } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal } from "@/components/concept-3/Reveal";
import { TechDashboard } from "@/components/concept-3/TechDashboard";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Technology",
  description:
    "The technology stack behind TECHBISS delivery — frontend, backend, mobile, cloud infrastructure, security, and business systems.",
};

export default function TechnologyPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Technology">
        <Reveal className="max-w-3xl">
          <Eyebrow>Technology</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            A stack chosen for longevity, not trend.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            The technology behind a project matters less as a checklist and more as a decision: every tool below
            was chosen because it holds up in production, scales with your business, and doesn&apos;t lock you into a
            single vendor. Explore the categories below.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Technology dashboard" className="pt-0">
        <TechDashboard categories={techCapabilities} />
      </Section>

      <Section aria-label="Why technology choices matter" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading
            eyebrow="Why it matters"
            title="Technology decisions are business decisions"
            description="The right stack keeps your systems fast, secure, and maintainable years after launch. The wrong one creates technical debt that slows every future change. We choose tools your business can grow into, backed by active communities and long-term support — not whatever is newest."
            align="center"
          />
        </Reveal>
      </Section>

      <CtaSection
        title="Curious how this stack fits your project?"
        description="We'll walk through the specific technology choices for your scope during discovery."
      />
    </>
  );
}
