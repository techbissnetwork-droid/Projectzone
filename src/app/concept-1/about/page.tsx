import type { Metadata } from "next";
import { ShieldCheck, Handshake, Layers, Rocket, ArrowRight } from "lucide-react";
import { company, processSteps } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { Button } from "@/components/concept-1/Button";
import { CtaBanner } from "@/components/concept-1/CtaBanner";

export const metadata: Metadata = {
  title: "About",
  description:
    "TECHBISS helps businesses move from offline to online — learn who we are, how we work, and why digital transformation matters now.",
};

const differentiators = [
  {
    icon: ShieldCheck,
    title: "Security by default",
    description:
      "Every project ships with SSL, hardened configurations, and access controls built in — not bolted on after launch.",
  },
  {
    icon: Layers,
    title: "End-to-end ownership",
    description:
      "Design, engineering, infrastructure, and support — one accountable team, not a chain of handoffs between vendors.",
  },
  {
    icon: Handshake,
    title: "Long-term partnership",
    description:
      "We stay engaged after launch, monitoring, maintaining, and evolving your platform as your business grows.",
  },
  {
    icon: Rocket,
    title: "Built to perform",
    description:
      "Every build is engineered for speed, reliability, and clarity — technology that earns trust the moment customers arrive.",
  },
];

export default function ConceptOneAboutPage() {
  return (
    <>
      <PageHero
        eyebrow="About TECHBISS"
        title="Technology and business transformation, under one team."
        description={company.description}
      />

      <Section className="pt-0">
        <div className="grid gap-12 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <SectionHeading
              eyebrow="Why It Matters Now"
              title="Being offline is no longer neutral — it's a disadvantage."
              description="Customers research, compare, and buy digitally by default. Businesses without a credible, secure, well-built digital presence are quietly losing ground to competitors who have one. We exist to close that gap — fast, and properly."
            />
          </Reveal>
          <Reveal delay={0.08}>
            <div className="rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-xl">
              <h3 className="text-lg font-semibold tracking-tight text-neutral-50">
                What we actually do
              </h3>
              <p className="mt-4 text-sm leading-relaxed text-neutral-400">
                We design and build premium websites, web and mobile applications, and the
                infrastructure behind them — domains, hosting, security, and business email —
                then plan complete digitization roadmaps for businesses moving from offline
                operations to a fully connected digital footprint.
              </p>
              <p className="mt-4 text-sm leading-relaxed text-neutral-400">
                Who we help: businesses of every size, from those launching their first website
                to established organizations coordinating a full-scale transformation across
                web, mobile, and internal systems.
              </p>
            </div>
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading
          eyebrow="Why Choose Us"
          title="What sets a TECHBISS engagement apart."
          align="center"
          className="mx-auto"
        />
        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {differentiators.map((item, index) => (
            <Reveal key={item.title} delay={index * 0.06}>
              <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                  <item.icon className="h-5 w-5 text-neutral-100" aria-hidden="true" />
                </div>
                <h3 className="mt-5 text-base font-semibold tracking-tight text-neutral-50">
                  {item.title}
                </h3>
                <p className="mt-2 text-sm leading-relaxed text-neutral-400">
                  {item.description}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-white/5">
        <div className="grid gap-12 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <SectionHeading
              eyebrow="Our Team"
              title="Designers, engineers, and strategists — working as one team."
              description="Every engagement is staffed with the disciplines it needs: product strategists to define the right scope, designers to shape the experience, engineers to build it correctly, and support specialists to keep it running. No juniors learning on your project, no outsourced black boxes — just people who take ownership of the outcome."
            />
          </Reveal>
          <Reveal delay={0.08}>
            <div className="rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-xl">
              <h3 className="text-lg font-semibold tracking-tight text-neutral-50">
                How an engagement runs
              </h3>
              <ol className="mt-5 space-y-4">
                {processSteps.slice(0, 4).map((step) => (
                  <li key={step.step} className="flex gap-4">
                    <span className="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 bg-white/5 text-xs font-semibold text-neutral-200">
                      {step.step}
                    </span>
                    <div>
                      <p className="text-sm font-medium text-neutral-100">{step.title}</p>
                      <p className="mt-1 text-xs leading-relaxed text-neutral-500">
                        {step.description}
                      </p>
                    </div>
                  </li>
                ))}
              </ol>
              <Button href="/concept-1/process" variant="ghost" className="mt-6 px-0">
                See our full process
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Button>
            </div>
          </Reveal>
        </div>
      </Section>

      <CtaBanner
        title="Let's build your next chapter."
        description="Whether you're launching your first digital presence or transforming an entire organization, we're ready to start the conversation."
      />
    </>
  );
}
