import type { Metadata } from "next";
import { ArrowUpRight, Compass } from "lucide-react";
import { serviceBySlug, services } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Button } from "@/components/concept-3/Button";
import { Reveal } from "@/components/concept-3/Reveal";
import { ServiceHeroWidget } from "@/components/concept-3/ServiceHeroWidget";
import { FeatureChecklist } from "@/components/concept-3/FeatureChecklist";
import { MiniProcess } from "@/components/concept-3/MiniProcess";
import { RelatedServices } from "@/components/concept-3/RelatedServices";
import { CtaSection } from "@/components/concept-3/CtaSection";
import { getIcon } from "@/components/concept-3/icon-map";

const service = serviceBySlug("business-digitization")!;
const related = services.filter((s) => ["website-development", "domain-hosting", "business-email"].includes(s.slug));
const DetailIcon = getIcon(service.icon);

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function BusinessDigitizationPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label={service.title}>
        <div className="grid grid-cols-1 items-center gap-14 lg:grid-cols-2 lg:gap-10">
          <Reveal>
            <Eyebrow>
              <DetailIcon className="h-3.5 w-3.5" aria-hidden="true" /> Full Transformation
            </Eyebrow>
            <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
              {service.title}
            </h1>
            <p className="mt-6 text-lg leading-relaxed text-slate-400">{service.longDescription}</p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Button href="/concept-3/get-started" icon={ArrowUpRight}>
                Start Your Project
              </Button>
              <Button href="/concept-3/pricing" variant="secondary" icon={Compass} iconPosition="leading">
                See Pricing
              </Button>
            </div>
          </Reveal>
          <Reveal delay={0.15}>
            <ServiceHeroWidget slug={service.slug} />
          </Reveal>
        </div>
      </Section>

      <Section aria-label="What's included" className="border-y border-white/5 bg-white/[0.015]">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <SectionHeading eyebrow="What's included" title="Zero to fully digital, coordinated end to end" />
          </Reveal>
          <Reveal delay={0.1}>
            <FeatureChecklist items={service.features} />
          </Reveal>
        </div>
      </Section>

      <Section aria-label="Deliverables">
        <Reveal>
          <SectionHeading eyebrow="Deliverables" title="What you walk away with" />
        </Reveal>
        <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
          {service.deliverables.map((d) => (
            <div key={d} className="rounded-xl border border-white/10 bg-white/[0.03] px-5 py-4 text-sm text-slate-300">
              {d}
            </div>
          ))}
        </div>
      </Section>

      <Section aria-label="How it works" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading eyebrow="How it works" title="A phased rollout, not a big-bang switch" />
        </Reveal>
        <div className="mt-10">
          <MiniProcess
            steps={[
              { title: "Digital readiness audit", description: "We assess what exists today across systems, workflows, and skills." },
              { title: "Roadmap & prioritization", description: "A phased plan sequenced by impact, so early wins fund later phases." },
              { title: "Phased rollout", description: "Systems and tools go live in stages to minimize disruption to daily operations." },
              { title: "Training & handover", description: "Staff onboarding and a 90-day support plan to make the transition stick." },
            ]}
          />
        </div>
      </Section>

      <Section aria-label="Related services">
        <Reveal>
          <SectionHeading eyebrow="Pairs well with" title="Related services" />
        </Reveal>
        <div className="mt-8">
          <RelatedServices services={related} />
        </div>
      </Section>

      <CtaSection
        title="Starting from zero online presence?"
        description="That's exactly what this service is built for. Let's map your roadmap."
      />
    </>
  );
}
