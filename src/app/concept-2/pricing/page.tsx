import type { Metadata } from "next";
import { pricingTiers, pricingNote, faqs } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { PricingColumn } from "@/components/concept-2/PricingColumn";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Pricing",
  description: "Three typical engagement tiers — Launch, Growth, and Enterprise — scoped precisely to your business.",
};

const relevantQuestions = new Set([
  "How long does a typical website project take?",
  "Do you offer support after launch?",
]);
const pricingFaqs = faqs.filter((f) => relevantQuestions.has(f.question));

export default function PricingPage() {
  return (
    <>
      <PageHero
        eyebrow="Pricing"
        title="Scoped to the business you're building."
        description="These tiers illustrate typical scope. Your exact package is defined after a short discovery conversation."
      />

      <Section>
        <div className="grid gap-6 lg:grid-cols-3">
          {pricingTiers.map((tier) => (
            <Reveal key={tier.name} className="h-full">
              <PricingColumn tier={tier} />
            </Reveal>
          ))}
        </div>
        <Reveal delay={0.1}>
          <p className="mt-10 max-w-2xl text-sm leading-relaxed text-neutral-500">{pricingNote}</p>
        </Reveal>
      </Section>

      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Pricing questions</p>
        </Reveal>
        <div className="mt-10 grid gap-10 sm:grid-cols-2">
          {pricingFaqs.map((faq) => (
            <Reveal key={faq.question}>
              <h3 className={`${fontSerif} text-xl text-neutral-900`}>{faq.question}</h3>
              <p className="mt-3 text-sm leading-relaxed text-neutral-600">{faq.answer}</p>
            </Reveal>
          ))}
        </div>
      </Section>

      <CtaSection
        title="Let's scope your project precisely."
        primaryLabel="Start Your Project"
        secondaryLabel="Talk to us first"
        secondaryHref="/concept-2/contact"
      />
    </>
  );
}
