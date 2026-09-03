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

const service = serviceBySlug("mobile-app-development")!;
const related = services.filter((s) => ["web-application-development", "website-development", "ssl-security"].includes(s.slug));
const DetailIcon = getIcon(service.icon);

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function MobileAppDevelopmentPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label={service.title}>
        <div className="grid grid-cols-1 items-center gap-14 lg:grid-cols-2 lg:gap-10">
          <Reveal>
            <Eyebrow>
              <DetailIcon className="h-3.5 w-3.5" aria-hidden="true" /> iOS & Android
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
            <SectionHeading eyebrow="What's included" title="One app, both platforms, no compromises" />
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
          <SectionHeading eyebrow="How it works" title="From concept to app store" />
        </Reveal>
        <div className="mt-10">
          <MiniProcess
            steps={[
              { title: "Product definition", description: "We define core flows, offline behavior, and the accounts and payments your app needs." },
              { title: "Design for iOS & Android", description: "Interfaces designed to feel native on both platforms from a shared design system." },
              { title: "Development & integration", description: "Cross-platform build wired into your existing backend and APIs." },
              { title: "Store submission & launch", description: "App Store and Play Store listings, review, and a coordinated release." },
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
        title="Ready to put your business in your customers' pockets?"
        description="Tell us about the app you're picturing — we'll map the fastest path to shipping it."
      />
    </>
  );
}
