import type { Metadata } from "next";
import { services } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { ServiceCard } from "@/components/concept-1/ServiceCard";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "Services",
  description:
    "Explore the full range of TECHBISS services — websites, applications, mobile, digitization, hosting, security, and business email.",
};

export default function ConceptOneServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="Every capability your business needs to operate online."
        description="From your first website to a full-scale digital transformation, our services are designed to work together as one coordinated system — not a patchwork of vendors."
      />

      <Section className="pt-0">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service, index) => (
            <Reveal key={service.slug} delay={(index % 3) * 0.06}>
              <ServiceCard service={service} className="h-full" />
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="How We Work With You"
          title="One point of contact. Full accountability."
          description="Every engagement starts with discovery, moves through design and development with your visibility at each stage, and continues into testing, launch, and ongoing support — coordinated by one team so nothing falls between the cracks."
        />
        <div className="mt-12 grid gap-6 sm:grid-cols-3">
          <Reveal>
            <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
              <h3 className="text-base font-semibold tracking-tight text-neutral-50">
                Scoped around your goals
              </h3>
              <p className="mt-2 text-sm leading-relaxed text-neutral-400">
                We start with what success looks like for your business, then choose the right
                combination of services to get there — not the other way around.
              </p>
            </div>
          </Reveal>
          <Reveal delay={0.06}>
            <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
              <h3 className="text-base font-semibold tracking-tight text-neutral-50">
                Delivered as one system
              </h3>
              <p className="mt-2 text-sm leading-relaxed text-neutral-400">
                Your website, hosting, security, and email are architected to work together from
                day one, avoiding the friction of stitching together separate vendors later.
              </p>
            </div>
          </Reveal>
          <Reveal delay={0.12}>
            <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
              <h3 className="text-base font-semibold tracking-tight text-neutral-50">
                Supported after launch
              </h3>
              <p className="mt-2 text-sm leading-relaxed text-neutral-400">
                Launch is a milestone, not an ending. Every project includes a post-launch
                support window, with ongoing plans available as you grow.
              </p>
            </div>
          </Reveal>
        </div>
      </Section>

      <CtaBanner />
    </>
  );
}
