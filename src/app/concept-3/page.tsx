import type { Metadata } from "next";
import Link from "next/link";
import { ArrowUpRight, Compass, TrendingUp, Boxes, Cpu } from "lucide-react";
import { company, services, trustStats, processSteps, caseStudies, techCapabilities, faqs } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Button } from "@/components/concept-3/Button";
import { Reveal, RevealGroup, RevealItem } from "@/components/concept-3/Reveal";
import { HeroPanel } from "@/components/concept-3/HeroPanel";
import { ServiceTabs } from "@/components/concept-3/ServiceTabs";
import { StatWidget } from "@/components/concept-3/StatWidget";
import { ProcessStepper } from "@/components/concept-3/ProcessStepper";
import { CaseStudyCard } from "@/components/concept-3/CaseStudyCard";
import { TechDashboard } from "@/components/concept-3/TechDashboard";
import { FaqAccordion } from "@/components/concept-3/FaqAccordion";
import { WhyUsParallax } from "@/components/concept-3/WhyUsParallax";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "Digital Experience",
  description:
    "TECHBISS Digital Experience — an interactive, dashboard-inspired product tour of premium websites, applications, mobile apps, and complete digital infrastructure.",
};

const statIcons = [Boxes, Cpu, TrendingUp, Compass];

export default function ConceptThreeHomePage() {
  return (
    <>
      {/* Hero */}
      <Section className="pb-12 pt-14 sm:pt-20 lg:pb-20 lg:pt-24" aria-label="Introduction">
        <div className="grid grid-cols-1 items-center gap-14 lg:grid-cols-2 lg:gap-10">
          <Reveal>
            <Eyebrow>Technology + Business Transformation</Eyebrow>
            <h1 className="font-display mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-[3.4rem]">
              We Build What Moves Business Forward.
            </h1>
            <p className="mt-6 max-w-xl text-base leading-relaxed text-slate-400 sm:text-lg">
              {company.description} Explore the platform below the way you'd explore a product — every card, tab,
              and panel is live.
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Button href="/concept-3/get-started" icon={ArrowUpRight}>
                Start Your Project
              </Button>
              <Button href="/concept-3/services" variant="secondary" icon={Compass} iconPosition="leading">
                Explore Our Services
              </Button>
            </div>
          </Reveal>
          <Reveal delay={0.15}>
            <HeroPanel />
          </Reveal>
        </div>
      </Section>

      {/* Interactive services */}
      <Section aria-label="Services">
        <Reveal>
          <SectionHeading
            eyebrow="What we build"
            title="One interactive catalog. Every capability you need."
            description="Filter by category or browse the full set — each card previews what's included before you click through."
          />
        </Reveal>
        <div className="mt-10">
          <ServiceTabs services={services.slice(0, 8)} />
        </div>
      </Section>

      {/* Trust stats */}
      <Section aria-label="Why TECHBISS" className="border-y border-white/5 bg-white/[0.015]">
        <RevealGroup className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {trustStats.map((stat, i) => {
            const Icon = statIcons[i % statIcons.length];
            return (
              <RevealItem key={stat.label}>
                <StatWidget label={stat.label} value={stat.value} icon={Icon} index={i} />
              </RevealItem>
            );
          })}
        </RevealGroup>
      </Section>

      {/* Scroll-driven differentiators */}
      <Section aria-label="Why teams choose TECHBISS">
        <Reveal>
          <SectionHeading
            eyebrow="Why TECHBISS"
            title="Built like a product company, not an agency"
            description="Each of these settles into place as you scroll — a small nod to how deliberately we build interfaces."
          />
        </Reveal>
        <div className="mt-10">
          <WhyUsParallax />
        </div>
      </Section>

      {/* Process teaser */}
      <Section aria-label="Our process" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading
            eyebrow="How we work"
            title="A clear, six-stage delivery process"
            description="Click through the first few stages — the full journey continues on the Process page."
          />
        </Reveal>
        <div className="mt-10">
          <ProcessStepper steps={processSteps.slice(0, 4)} />
        </div>
        <div className="mt-8">
          <Link
            href="/concept-3/process"
            className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-violet-300 hover:text-violet-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
          >
            See the full process <ArrowUpRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </div>
      </Section>

      {/* Case studies teaser */}
      <Section aria-label="Selected work">
        <Reveal>
          <SectionHeading
            eyebrow="Selected work"
            title="Digitization in practice"
            description="A look at the kinds of projects that come through our process — illustrative examples of scope and shape."
          />
        </Reveal>
        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {caseStudies.slice(0, 3).map((study, i) => (
            <CaseStudyCard key={study.slug} study={study} index={i} />
          ))}
        </div>
        <div className="mt-10 text-center">
          <Button href="/concept-3/portfolio" variant="secondary">
            View Full Portfolio
          </Button>
        </div>
      </Section>

      {/* Technology teaser */}
      <Section aria-label="Technology" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading
            eyebrow="Technology"
            title="A modern stack, chosen deliberately"
            description="Switch between categories to see the tools and platforms behind every delivery."
          />
        </Reveal>
        <div className="mt-10">
          <TechDashboard categories={techCapabilities} />
        </div>
      </Section>

      {/* FAQ teaser */}
      <Section aria-label="Frequently asked questions">
        <Reveal>
          <SectionHeading eyebrow="FAQ" title="Common questions, answered" align="center" />
        </Reveal>
        <div className="mx-auto mt-10 max-w-2xl">
          <FaqAccordion faqs={faqs.slice(0, 3)} />
        </div>
        <div className="mt-8 text-center">
          <Link
            href="/concept-3/contact"
            className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-violet-300 hover:text-violet-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
          >
            Have a different question? Contact us <ArrowUpRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </div>
      </Section>

      <CtaSection />
    </>
  );
}
