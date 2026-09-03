import type { Metadata } from "next";
import { services } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { ServiceRow } from "@/components/concept-2/ServiceRow";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Services",
  description:
    "Nine disciplines covering the full path from offline to online — website, application, mobile, infrastructure, and support.",
};

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="Everything a modern business needs, in one place."
        description="From a single premium website to a complete digitization roadmap, every service below can stand alone or combine into one coordinated engagement."
      />

      <Section>
        <div>
          {services.map((service, i) => (
            <Reveal key={service.slug} delay={Math.min(i * 0.03, 0.2)}>
              <ServiceRow
                index={String(i + 1).padStart(2, "0")}
                title={service.title}
                description={service.shortDescription}
                href={service.hasDetailPage ? `/concept-2/services/${service.slug}` : "/concept-2/contact"}
              />
            </Reveal>
          ))}
        </div>
      </Section>

      <Section tone="off" border="top">
        <div className="grid gap-12 lg:grid-cols-2">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">How we work with you</p>
            <h2 className={`${fontSerif} mt-4 text-4xl text-neutral-900 sm:text-5xl`}>
              One scope, one team, one point of contact.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">
              Most projects combine two or three of these services — a website with hosting and email, or a web
              application alongside a full digitization roadmap. We scope everything together during discovery so
              nothing is handled in isolation, and you always know who is responsible for what.
            </p>
          </Reveal>
        </div>
      </Section>

      <CtaSection
        title="Not sure which service you need?"
        primaryLabel="Talk to us"
        primaryHref="/concept-2/contact"
        secondaryLabel="See pricing"
        secondaryHref="/concept-2/pricing"
      />
    </>
  );
}
