import type { Metadata } from "next";
import { pricingTiers, pricingNote, faqs } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal, RevealGroup, RevealItem } from "@/components/concept-3/Reveal";
import { PricingCard } from "@/components/concept-3/PricingCard";
import { FaqAccordion } from "@/components/concept-3/FaqAccordion";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Pricing",
  description:
    "TECHBISS pricing tiers — Launch, Growth, and Enterprise — illustrating typical project scope. Final packages are scoped after a discovery call.",
};

const pricingFaqs = faqs.filter((f) =>
  ["How long does a typical website project take?", "Do you offer support after launch?"].includes(f.question)
);

export default function PricingPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Pricing">
        <Reveal className="max-w-3xl">
          <Eyebrow>Pricing</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Three starting points. One scoped quote.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            These tiers show typical scope for businesses at different stages — pick the closest fit and we&apos;ll
            refine it together during discovery.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Pricing tiers" className="pt-0">
        <RevealGroup className="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
          {pricingTiers.map((tier) => (
            <RevealItem key={tier.name} className="h-full">
              <PricingCard tier={tier} />
            </RevealItem>
          ))}
        </RevealGroup>

        <Reveal delay={0.1}>
          <p className="mx-auto mt-12 max-w-2xl rounded-xl border border-white/10 bg-white/[0.03] px-6 py-4 text-center text-sm text-slate-400">
            {pricingNote}
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Pricing FAQ" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading eyebrow="Pricing FAQ" title="Common questions about cost & scope" align="center" />
        </Reveal>
        <div className="mx-auto mt-10 max-w-2xl">
          <FaqAccordion faqs={pricingFaqs} />
        </div>
      </Section>

      <CtaSection
        title="Get a quote scoped to your business."
        description="A short discovery call is all it takes to move from tier to tailored proposal."
      />
    </>
  );
}
