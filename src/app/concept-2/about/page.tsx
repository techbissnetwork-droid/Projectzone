import type { Metadata } from "next";
import { company, processSteps } from "@/lib/site-data";
import { Section } from "@/components/concept-2/Section";
import { PageHero } from "@/components/concept-2/PageHero";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { ProcessListItem } from "@/components/concept-2/ProcessListItem";
import { CtaSection } from "@/components/concept-2/CtaSection";
import { LinkButton } from "@/components/concept-2/Button";

export const metadata: Metadata = {
  title: "About",
  description: "TECHBISS builds the technology and business infrastructure companies need to move online with confidence.",
};

const differentiators = [
  {
    title: "One partner, not five vendors.",
    body: "Website, application, mobile, hosting, security, and email — one team accountable for how they work together.",
  },
  {
    title: "Business first, code second.",
    body: "Every technical decision is made in service of a business outcome, never for its own sake.",
  },
  {
    title: "Built to last past launch.",
    body: "We design for maintainability and support, not a project that goes quiet the day it ships.",
  },
];

export default function AboutPage() {
  return (
    <>
      <PageHero eyebrow="About TECHBISS" title="Technology, built around your business." description={company.description} />

      <Section>
        <div className="grid gap-12 lg:grid-cols-2 lg:gap-20">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Why it matters now</p>
            <h2 className={`${fontSerif} mt-4 text-4xl leading-tight text-neutral-900 sm:text-5xl`}>
              Offline businesses lose ground every quarter they wait.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">
              Customers now expect to find you, evaluate you, and transact with you online before they ever speak
              with you directly. {company.legalTagline} is our full name for a reason — we treat the technology and
              the business strategy as one project, not two.
            </p>
          </Reveal>
        </div>
      </Section>

      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Why choose us</p>
        </Reveal>
        <div className="mt-10 grid gap-10 border-t border-neutral-200 pt-10 sm:grid-cols-3">
          {differentiators.map((d, i) => (
            <Reveal key={d.title} delay={i * 0.05}>
              <h3 className={`${fontSerif} text-2xl leading-snug text-neutral-900`}>{d.title}</h3>
              <p className="mt-4 text-sm leading-relaxed text-neutral-600">{d.body}</p>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">The team</p>
          <h2 className={`${fontSerif} mt-4 max-w-2xl text-4xl text-neutral-900 sm:text-5xl`}>
            Designers, engineers, and strategists working as one.
          </h2>
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-neutral-600 sm:text-lg">
            Every engagement is staffed with the disciplines it actually needs — design, engineering, infrastructure,
            and strategy — rather than a single generalist stretched across a project. You work with the people
            building your product, not an account manager relaying messages.
          </p>
        </Reveal>
      </Section>

      <Section tone="off" border="top">
        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">How we work</p>
            <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>
              A repeatable, disciplined process.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <LinkButton href="/concept-2/process" variant="secondary">
              See the full process
            </LinkButton>
          </Reveal>
        </div>
        <div className="mt-6">
          {processSteps.slice(0, 3).map((step) => (
            <ProcessListItem key={step.step} step={step.step} title={step.title} description={step.description} />
          ))}
        </div>
      </Section>

      <CtaSection title="Let's build your next chapter." primaryLabel="Start Your Project" secondaryLabel="Meet our services" secondaryHref="/concept-2/services" />
    </>
  );
}
