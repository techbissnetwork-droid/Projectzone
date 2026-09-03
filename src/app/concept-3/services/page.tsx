import type { Metadata } from "next";
import { services } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal } from "@/components/concept-3/Reveal";
import { ServiceTabs } from "@/components/concept-3/ServiceTabs";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Services",
  description:
    "Explore the full TECHBISS service catalog — websites, web and mobile applications, business digitization, hosting, security, and business email.",
};

export default function ServicesPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Services overview">
        <Reveal className="max-w-3xl">
          <Eyebrow>Service catalog</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Every capability, organized like a product.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            Filter by category to explore exactly what you need, or browse the full catalog below. Every card
            expands on hover to preview what's included, and every card links to full detail.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Full service catalog" className="pt-0">
        <ServiceTabs services={services} />
      </Section>

      <CtaSection
        title="Not sure which service fits?"
        description="Tell us what you're trying to accomplish and we'll recommend the right scope — no pressure, no guesswork."
      />
    </>
  );
}
