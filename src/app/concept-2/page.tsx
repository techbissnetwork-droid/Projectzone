import type { Metadata } from "next";
import { company, services, trustStats, processSteps, caseStudies, techCapabilities, faqs } from "@/lib/site-data";
import { Container } from "@/components/concept-2/Container";
import { Section } from "@/components/concept-2/Section";
import { Reveal, RevealWords } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { LinkButton } from "@/components/concept-2/Button";
import { ServiceRow } from "@/components/concept-2/ServiceRow";
import { StatBlock } from "@/components/concept-2/StatBlock";
import { ProcessListItem } from "@/components/concept-2/ProcessListItem";
import { CaseStudyRow } from "@/components/concept-2/CaseStudyRow";
import { CtaSection } from "@/components/concept-2/CtaSection";

export const metadata: Metadata = {
  title: "Ultra-Minimal Luxury",
  description: company.description,
};

export default function ConceptTwoHome() {
  return (
    <>
      {/* Hero — asymmetric editorial composition */}
      <section className="border-b border-neutral-200 pb-20 pt-16 sm:pb-28 sm:pt-24">
        <Container>
          <Reveal>
            <p className="text-xs font-medium uppercase tracking-[0.2em] text-neutral-500">
              {company.legalTagline}
            </p>
          </Reveal>

          <h1 className="mt-8 max-w-5xl">
            <RevealWords
              text="Precision Builds Trust."
              className={`${fontSerif} block text-6xl leading-[0.98] text-neutral-900 sm:text-8xl lg:text-[7rem]`}
            />
          </h1>

          <div className="mt-14 flex flex-col gap-10 lg:flex-row lg:items-end lg:justify-between">
            <Reveal delay={0.15} className="max-w-md lg:ml-24">
              <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">{company.description}</p>
            </Reveal>
            <Reveal delay={0.25}>
              <div className="flex flex-wrap gap-4">
                <LinkButton href="/concept-2/get-started">Start Your Project</LinkButton>
                <LinkButton href="/concept-2/services" variant="secondary">
                  Explore Our Services
                </LinkButton>
              </div>
            </Reveal>
          </div>
        </Container>
      </section>

      {/* Services */}
      <Section>
        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">What we do</p>
            <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>
              Nine disciplines. One accountable team.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <LinkButton href="/concept-2/services" variant="secondary">
              View all services
            </LinkButton>
          </Reveal>
        </div>

        <div className="mt-14">
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

      {/* Trust stats */}
      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">By the numbers</p>
        </Reveal>
        <div className="mt-10 grid grid-cols-2 gap-x-8 gap-y-12 lg:grid-cols-4">
          {trustStats.map((stat, i) => (
            <Reveal key={stat.label} delay={i * 0.05}>
              <StatBlock value={stat.value} label={stat.label} />
            </Reveal>
          ))}
        </div>
      </Section>

      {/* Process teaser */}
      <Section border="top">
        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">How we work</p>
            <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>
              A process built for certainty.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <LinkButton href="/concept-2/process" variant="secondary">
              See the full process
            </LinkButton>
          </Reveal>
        </div>
        <div className="mt-6">
          {processSteps.slice(0, 4).map((step, i) => (
            <Reveal key={step.step} delay={Math.min(i * 0.04, 0.2)}>
              <ProcessListItem step={step.step} title={step.title} description={step.description} />
            </Reveal>
          ))}
        </div>
      </Section>

      {/* Case studies teaser */}
      <Section tone="off" border="top">
        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Selected work</p>
            <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>
              Outcomes across industries.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <LinkButton href="/concept-2/portfolio" variant="secondary">
              View portfolio
            </LinkButton>
          </Reveal>
        </div>
        <div className="mt-6">
          {caseStudies.slice(0, 3).map((cs, i) => (
            <Reveal key={cs.slug} delay={Math.min(i * 0.05, 0.2)}>
              <CaseStudyRow caseStudy={cs} />
            </Reveal>
          ))}
        </div>
      </Section>

      {/* Technology teaser */}
      <Section border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Technology</p>
          <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>
            Modern infrastructure, chosen deliberately.
          </h2>
        </Reveal>
        <Reveal delay={0.1}>
          <div className="mt-10 flex flex-wrap gap-x-10 gap-y-4 border-t border-neutral-200 pt-10">
            {techCapabilities.map((cat) => (
              <span key={cat.category} className="text-sm text-neutral-700">
                {cat.category}
              </span>
            ))}
          </div>
        </Reveal>
        <Reveal delay={0.15}>
          <div className="mt-8">
            <LinkButton href="/concept-2/technology" variant="secondary">
              Explore our stack
            </LinkButton>
          </div>
        </Reveal>
      </Section>

      {/* FAQ teaser */}
      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Questions</p>
        </Reveal>
        <div className="mt-10 grid gap-10 sm:grid-cols-3">
          {faqs.slice(0, 3).map((faq, i) => (
            <Reveal key={faq.question} delay={i * 0.05}>
              <h3 className={`${fontSerif} text-xl text-neutral-900`}>{faq.question}</h3>
              <p className="mt-3 text-sm leading-relaxed text-neutral-600">{faq.answer}</p>
            </Reveal>
          ))}
        </div>
        <Reveal delay={0.2}>
          <div className="mt-10">
            <LinkButton href="/concept-2/contact" variant="secondary">
              All questions
            </LinkButton>
          </div>
        </Reveal>
      </Section>

      <CtaSection
        title="Ready to move your business forward?"
        description="Tell us where you are today. We'll tell you exactly what it takes to get where you're going."
        primaryLabel="Start Your Project"
        secondaryLabel="Talk to us"
        secondaryHref="/concept-2/contact"
      />
    </>
  );
}
