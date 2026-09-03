import type { Metadata } from "next";
import { pricingTiers, pricingNote, faqs } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { PricingCard } from "@/components/concept-1/PricingCard";
import { FaqAccordion } from "@/components/concept-1/FaqAccordion";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "Pricing",
  description:
    "TECHBISS pricing tiers — Launch, Growth, and Enterprise — illustrating typical project scope before a discovery call.",
};

const pricingFaqs = faqs.filter((faq) =>
  ["How long does a typical website project take?", "Do you offer support after launch?"].includes(
    faq.question
  )
);

export default function ConceptOnePricingPage() {
  return (
    <>
      <PageHero
        eyebrow="Pricing"
        title="Transparent tiers, tailored scope."
        description="Every business is different, so every quote is too. These tiers show the kind of scope typical at each stage of growth."
      />

      <Section className="pt-0">
        <div className="grid gap-6 lg:grid-cols-3">
          {pricingTiers.map((tier, index) => (
            <Reveal key={tier.name} delay={index * 0.06}>
              <PricingCard tier={tier} />
            </Reveal>
          ))}
        </div>
        <Reveal delay={0.2}>
          <p className="mx-auto mt-10 max-w-2xl text-center text-sm leading-relaxed text-neutral-500">
            {pricingNote}
          </p>
        </Reveal>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading eyebrow="Pricing Questions" title="Common questions about scope and cost." />
        <div className="mt-10 max-w-3xl">
          <FaqAccordion items={pricingFaqs} />
        </div>
      </Section>

      <CtaBanner
        title="Not sure which tier fits?"
        description="Tell us about your business and we'll recommend the right starting point — no pressure, just clarity."
      />
    </>
  );
}
