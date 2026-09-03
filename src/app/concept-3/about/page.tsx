import type { Metadata } from "next";
import { Target, Users, Rocket, ShieldCheck, Layers, Clock } from "lucide-react";
import { company, processSteps } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal, RevealGroup, RevealItem } from "@/components/concept-3/Reveal";
import { ProcessStepper } from "@/components/concept-3/ProcessStepper";
import { CtaSection } from "@/components/concept-3/CtaSection";

export const metadata: Metadata = {
  title: "About",
  description:
    "TECHBISS helps businesses move from offline to online — learn what we do, who we work with, and why digital transformation matters now.",
};

const differentiators = [
  {
    title: "Outcome-first strategy",
    description: "We start every engagement with your business goals, then work backward to the right technology — never the reverse.",
    icon: Target,
  },
  {
    title: "One accountable team",
    description: "Design, engineering, infrastructure, and security are coordinated by the same team from discovery through launch.",
    icon: Layers,
  },
  {
    title: "Security by default",
    description: "SSL, hardened configuration, and monitoring are part of the build, not an optional add-on you have to remember to ask for.",
    icon: ShieldCheck,
  },
  {
    title: "Built for how you work",
    description: "Every platform we ship is shaped around your real workflows and team, not a generic template your business has to adapt to.",
    icon: Users,
  },
  {
    title: "Momentum after launch",
    description: "Post-launch support windows and ongoing plans mean your systems keep improving as your business grows.",
    icon: Rocket,
  },
  {
    title: "Clear timelines",
    description: "You always know what stage a project is in and what happens next — no black-box delivery.",
    icon: Clock,
  },
];

export default function AboutPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="About TECHBISS">
        <Reveal className="max-w-3xl">
          <Eyebrow>About TECHBISS</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Technology and business transformation, under one roof.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">{company.description}</p>
          <p className="mt-4 text-base leading-relaxed text-slate-400">
            Most businesses don&apos;t need more software — they need a coordinated path from where they are today to a
            complete, credible digital presence. That&apos;s the gap TECHBISS fills: one team covering websites, web
            and mobile applications, and the infrastructure — domains, hosting, security, and email — that keeps
            it all running.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Why digital transformation matters now" className="border-y border-white/5 bg-white/[0.015]">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <SectionHeading
              eyebrow="Why now"
              title="Digital-first is no longer optional"
              description="Customers now expect a business to be reachable, credible, and secure online before they ever call or visit. Businesses without a coordinated digital presence lose trust before the conversation even starts."
            />
          </Reveal>
          <Reveal delay={0.1}>
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6 sm:p-8">
              <h3 className="font-display text-lg font-semibold text-white">Who we help</h3>
              <p className="mt-3 text-sm leading-relaxed text-slate-400">
                We work with businesses at every stage of digital maturity — from teams with no online presence at
                all, to established organizations modernizing legacy systems and adding new digital products
                across retail, healthcare, logistics, hospitality, professional services, and beyond.
              </p>
              <p className="mt-3 text-sm leading-relaxed text-slate-400">
                The starting point differs; the approach doesn&apos;t. Every engagement begins with understanding your
                business before we design or build anything.
              </p>
            </div>
          </Reveal>
        </div>
      </Section>

      <Section aria-label="Why choose TECHBISS">
        <Reveal>
          <SectionHeading eyebrow="Why choose us" title="What makes the difference" align="center" />
        </Reveal>
        <RevealGroup className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {differentiators.map((item) => {
            const Icon = item.icon;
            return (
              <RevealItem key={item.title}>
                <div className="h-full rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-lg shadow-black/10 transition-colors hover:border-white/20">
                  <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                  </span>
                  <h3 className="font-display mt-4 text-lg font-semibold text-white">{item.title}</h3>
                  <p className="mt-2 text-sm text-slate-400">{item.description}</p>
                </div>
              </RevealItem>
            );
          })}
        </RevealGroup>
      </Section>

      <Section aria-label="Team and culture" className="border-y border-white/5 bg-white/[0.015]">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:items-center lg:gap-16">
          <Reveal>
            <SectionHeading
              eyebrow="How we're organized"
              title="Specialists, not generalists"
              description="Every engagement is staffed with dedicated design, engineering, and infrastructure specialists — not a single generalist stretched across every discipline. Product strategists scope the work, engineers build and harden it, and a support team stays on after launch."
            />
          </Reveal>
          <Reveal delay={0.1}>
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6 sm:p-8">
              <h3 className="font-display text-lg font-semibold text-white">How we operate</h3>
              <ul className="mt-4 flex flex-col gap-3 text-sm text-slate-400">
                <li>— Discovery-led scoping before any design or code work begins</li>
                <li>— Named points of contact throughout delivery, not a rotating queue</li>
                <li>— Documentation and training handed over at launch, not withheld</li>
                <li>— Ongoing support plans for teams who want a long-term technology partner</li>
              </ul>
            </div>
          </Reveal>
        </div>
      </Section>

      <Section aria-label="Our process">
        <Reveal>
          <SectionHeading eyebrow="Our process" title="How an engagement unfolds" align="center" />
        </Reveal>
        <div className="mt-10">
          <ProcessStepper steps={processSteps} />
        </div>
      </Section>

      <CtaSection
        title="Let's talk about where your business is headed."
        description="A short discovery conversation is the fastest way to see how TECHBISS fits your plans."
      />
    </>
  );
}
