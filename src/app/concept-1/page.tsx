import type { Metadata } from "next";
import { ArrowRight, Sparkles } from "lucide-react";
import {
  company,
  services,
  trustStats,
  processSteps,
  caseStudies,
  techCapabilities,
  faqs,
} from "@/lib/site-data";
import { Container } from "@/components/concept-1/Container";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { Button } from "@/components/concept-1/Button";
import { HeroBackground } from "@/components/concept-1/HeroBackground";
import { ServiceCard } from "@/components/concept-1/ServiceCard";
import { StatCounter } from "@/components/concept-1/StatCounter";
import { CaseStudyCard } from "@/components/concept-1/CaseStudyCard";
import { CtaBanner } from "@/components/concept-1/CtaBanner";
import Link from "next/link";

export const metadata: Metadata = {
  title: "Future Luxury",
  description:
    "TECHBISS builds premium websites, applications, and complete digital infrastructure for businesses ready to scale — the Future Luxury design concept.",
};

const homeServices = services.slice(0, 8);
const homeProcessSteps = processSteps.slice(0, 4);
const homeCaseStudies = caseStudies.slice(0, 3);
const homeFaqs = faqs.slice(0, 3);

export default function ConceptOneHomePage() {
  return (
    <>
      <section className="relative flex min-h-[92vh] items-center overflow-hidden pt-28">
        <HeroBackground />
        <Container className="relative">
          <Reveal>
            <Eyebrow>{company.legalTagline}</Eyebrow>
          </Reveal>
          <Reveal delay={0.08}>
            <h1 className="mt-8 max-w-5xl text-5xl font-semibold leading-[1.02] tracking-tight text-neutral-50 sm:text-7xl lg:text-8xl">
              We build what{" "}
              <span className="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-400 bg-clip-text text-transparent">
                moves business
              </span>{" "}
              forward.
            </h1>
          </Reveal>
          <Reveal delay={0.16}>
            <p className="mt-8 max-w-2xl text-lg leading-relaxed text-neutral-400 sm:text-xl">
              {company.description} TECHBISS is where technology and business transformation
              meet — websites, applications, and infrastructure engineered for companies ready
              to operate at the next level.
            </p>
          </Reveal>
          <Reveal delay={0.24}>
            <div className="mt-11 flex flex-wrap items-center gap-4">
              <Button href="/concept-1/get-started" variant="primary">
                Start Your Project
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Button>
              <Button href="/concept-1/services" variant="secondary">
                Explore Our Services
              </Button>
            </div>
          </Reveal>
        </Container>
      </section>

      {/* Trust stats */}
      <Section className="pt-0">
        <Reveal>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {trustStats.map((stat) => (
              <StatCounter key={stat.label} label={stat.label} value={stat.value} />
            ))}
          </div>
        </Reveal>
      </Section>

      {/* Services grid */}
      <Section id="services">
        <SectionHeading
          eyebrow="What We Do"
          title="A complete digital foundation, under one roof."
          description="From your first website to full-scale digitization, every service is built to work together — not as disconnected vendors."
        />
        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {homeServices.map((service, index) => (
            <Reveal key={service.slug} delay={(index % 3) * 0.06}>
              <ServiceCard service={service} className="h-full" />
            </Reveal>
          ))}
        </div>
        <Reveal className="mt-12 flex justify-center">
          <Button href="/concept-1/services" variant="secondary">
            View All Services
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </Reveal>
      </Section>

      {/* Process teaser */}
      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="How We Work"
          title="A disciplined process, built for confident outcomes."
          description="Every engagement follows the same rigorous path from discovery to long-term support — no guesswork, no surprises."
        />
        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {homeProcessSteps.map((step, index) => (
            <Reveal key={step.step} delay={index * 0.06}>
              <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <span className="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-3xl font-semibold text-transparent">
                  {step.step}
                </span>
                <h3 className="mt-4 text-base font-semibold tracking-tight text-neutral-50">
                  {step.title}
                </h3>
                <p className="mt-2 text-sm leading-relaxed text-neutral-400">{step.description}</p>
              </div>
            </Reveal>
          ))}
        </div>
        <Reveal className="mt-12 flex justify-center">
          <Button href="/concept-1/process" variant="secondary">
            See Our Full Process
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </Reveal>
      </Section>

      {/* Case studies teaser */}
      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="Proof In Practice"
          title="Real problems, engineered solutions."
          description="A look at the kinds of digital transformations we deliver across industries."
        />
        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {homeCaseStudies.map((study, index) => (
            <Reveal key={study.slug} delay={index * 0.06}>
              <CaseStudyCard study={study} className="h-full" />
            </Reveal>
          ))}
        </div>
        <Reveal className="mt-12 flex justify-center">
          <Button href="/concept-1/portfolio" variant="secondary">
            View Full Portfolio
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </Reveal>
      </Section>

      {/* Technology teaser */}
      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="Under The Hood"
          title="Built on a modern, scalable technology stack."
          align="center"
          className="mx-auto"
        />
        <Reveal className="mt-14 flex flex-wrap items-center justify-center gap-3">
          {techCapabilities.map((category) => (
            <span
              key={category.category}
              className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-2.5 text-sm font-medium text-neutral-200 backdrop-blur-xl"
            >
              <Sparkles className="h-3.5 w-3.5 text-cyan-300" aria-hidden="true" />
              {category.category}
            </span>
          ))}
        </Reveal>
        <Reveal className="mt-10 flex justify-center" delay={0.1}>
          <Button href="/concept-1/technology" variant="secondary">
            Explore Our Technology
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </Reveal>
      </Section>

      {/* FAQ teaser */}
      <Section className="border-t border-white/5">
        <SectionHeading eyebrow="Common Questions" title="Answers before you ask." />
        <div className="mt-12 grid gap-6 sm:grid-cols-3">
          {homeFaqs.map((faq, index) => (
            <Reveal key={faq.question} delay={index * 0.06}>
              <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <h3 className="text-base font-semibold tracking-tight text-neutral-50">
                  {faq.question}
                </h3>
                <p className="mt-3 text-sm leading-relaxed text-neutral-400">{faq.answer}</p>
              </div>
            </Reveal>
          ))}
        </div>
        <Reveal className="mt-10 flex justify-center" delay={0.1}>
          <Link
            href="/concept-1/contact"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-200 underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded-full"
          >
            See all frequently asked questions
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>
      </Section>

      <CtaBanner />
    </>
  );
}
