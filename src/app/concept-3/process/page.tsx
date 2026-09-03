import type { Metadata } from "next";
import { processSteps } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal } from "@/components/concept-3/Reveal";
import { ProcessStepper } from "@/components/concept-3/ProcessStepper";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Process",
  description:
    "The six-stage TECHBISS delivery process — from discovery and design through development, QA, launch, and ongoing support.",
};

export default function ProcessPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Our process">
        <Reveal className="max-w-3xl">
          <Eyebrow>Our process</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            A process built for clarity, not surprises.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            Every engagement — website, application, mobile app, or full digitization — runs through the same six
            stages. Click through them below.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Process stages" className="pt-0">
        <ProcessStepper steps={processSteps} />
      </Section>

      <Section aria-label="Why our process works" className="border-y border-white/5 bg-white/[0.015]">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:items-center lg:gap-16">
          <Reveal>
            <SectionHeading
              eyebrow="Why it works"
              title="Structure without rigidity"
              description="Every stage has a clear owner and a clear output, but the process flexes to your project's scope — a six-page website and a full digitization roadmap both move through the same stages at their own pace."
            />
          </Reveal>
          <Reveal delay={0.1}>
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6 sm:p-8">
              <ul className="flex flex-col gap-3 text-sm text-slate-400">
                <li>— You always know which stage your project is in</li>
                <li>— Review points at design and pre-launch keep you in control</li>
                <li>— Testing and QA happen before launch, not after complaints</li>
                <li>— Support continues after go-live, not just until invoice</li>
              </ul>
            </div>
          </Reveal>
        </div>
      </Section>

      <CtaSection
        title="Ready to start stage one?"
        description="Discovery starts with a short conversation about where your business is headed."
      />
    </>
  );
}
