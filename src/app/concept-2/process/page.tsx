import type { Metadata } from "next";
import { processSteps } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { ProcessListItem } from "@/components/concept-2/ProcessListItem";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Process",
  description: "Six stages, from discovery to ongoing support, applied to every engagement.",
};

export default function ProcessPage() {
  return (
    <>
      <PageHero
        eyebrow="Process"
        title="Six stages. No surprises."
        description="Every engagement — regardless of size — follows the same disciplined sequence, so you always know what happens next."
      />

      <Section>
        <div>
          {processSteps.map((step, i) => (
            <Reveal key={step.step} delay={Math.min(i * 0.03, 0.2)}>
              <ProcessListItem step={step.step} title={step.title} description={step.description} />
            </Reveal>
          ))}
        </div>
      </Section>

      <Section tone="off" border="top">
        <div className="grid gap-12 lg:grid-cols-2">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Why it works</p>
            <h2 className={`${fontSerif} mt-4 text-4xl text-neutral-900 sm:text-5xl`}>Structure removes guesswork.</h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">
              A fixed process doesn&apos;t make projects rigid — it makes them predictable. Because every engagement
              moves through the same six stages, you always know what&apos;s been decided, what&apos;s in progress,
              and what happens next, regardless of how large or small the project is.
            </p>
          </Reveal>
        </div>
      </Section>

      <CtaSection
        title="See what stage one looks like."
        primaryLabel="Start Your Project"
        secondaryLabel="Explore services"
        secondaryHref="/concept-2/services"
      />
    </>
  );
}
