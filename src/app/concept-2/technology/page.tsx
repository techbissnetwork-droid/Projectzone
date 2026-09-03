import type { Metadata } from "next";
import { techCapabilities } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Technology",
  description: "The frameworks, infrastructure, and security standards behind every TECHBISS engagement.",
};

export default function TechnologyPage() {
  return (
    <>
      <PageHero
        eyebrow="Technology"
        title="Chosen deliberately. Not by default."
        description="Every layer of our stack is selected for stability, performance, and longevity — not novelty."
      />

      <Section>
        <div>
          {techCapabilities.map((cat, i) => (
            <Reveal key={cat.category} delay={Math.min(i * 0.03, 0.2)}>
              <div className="grid grid-cols-1 gap-3 border-b border-neutral-200 py-8 sm:grid-cols-[280px_1fr] sm:gap-8">
                <h3 className={`${fontSerif} text-2xl text-neutral-900`}>{cat.category}</h3>
                <p className="text-sm leading-relaxed text-neutral-600 sm:text-base">{cat.items.join(", ")}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section tone="off" border="top">
        <div className="grid gap-12 lg:grid-cols-2">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Why it matters</p>
            <h2 className={`${fontSerif} mt-4 text-4xl text-neutral-900 sm:text-5xl`}>
              Technology choices outlive the project.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">
              The right framework or hosting provider today should still be the right choice in three years. We favor
              mature, well-supported technology over trends, so what we build for you keeps working — and keeps
              being maintainable — long after launch.
            </p>
          </Reveal>
        </div>
      </Section>

      <CtaSection
        title="Ask us about your specific stack."
        primaryLabel="Talk to us"
        primaryHref="/concept-2/contact"
        secondaryLabel="See our services"
        secondaryHref="/concept-2/services"
      />
    </>
  );
}
