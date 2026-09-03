import type { Metadata } from "next";
import { ShieldCheck, Layers, Rocket, Clock3 } from "lucide-react";
import { Section, Eyebrow } from "@/components/concept-3/Section";
import { Reveal, RevealGroup, RevealItem } from "@/components/concept-3/Reveal";
import { InquiryForm } from "@/components/concept-3/InquiryForm";

export const metadata: Metadata = {
  title: "Get Started",
  description: "Start your TECHBISS project — tell us about your business, budget, and goals, and we'll follow up with next steps.",
};

const recap = [
  { title: "One accountable team", description: "Design, engineering, and infrastructure coordinated end to end.", icon: Layers },
  { title: "Security built in", description: "SSL, hardening, and monitoring are standard on every project.", icon: ShieldCheck },
  { title: "Momentum after launch", description: "Support windows and ongoing plans keep systems improving.", icon: Rocket },
  { title: "Clear timelines", description: "You always know what stage your project is in.", icon: Clock3 },
];

export default function GetStartedPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Get started">
        <Reveal className="max-w-3xl">
          <Eyebrow>Project inquiry</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Let&apos;s scope your project.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            A few details now saves back-and-forth later. Fill in what you know — budget range and scope can
            always be refined together during discovery.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Project inquiry form" className="pt-0">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-[1.3fr_1fr] lg:gap-12">
          <Reveal>
            <InquiryForm />
          </Reveal>

          <Reveal delay={0.1}>
            <div className="flex flex-col gap-6">
              <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <h2 className="font-display text-base font-semibold text-white">What happens after you submit</h2>
                <ol className="mt-4 flex flex-col gap-3 text-sm text-slate-400">
                  <li>
                    <span className="font-semibold text-slate-200">1.</span> We respond within [Add response
                    time — e.g. 2 business days] to confirm we&apos;ve received your brief.
                  </li>
                  <li>
                    <span className="font-semibold text-slate-200">2.</span> If it&apos;s a fit, we schedule a short
                    discovery call to clarify scope and budget.
                  </li>
                  <li>
                    <span className="font-semibold text-slate-200">3.</span> You receive a scoped proposal
                    tailored to your goals — no generic package pushed on you.
                  </li>
                </ol>
              </div>

              <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <h2 className="font-display text-base font-semibold text-white">Why TECHBISS</h2>
                <RevealGroup className="mt-4 flex flex-col gap-4">
                  {recap.map((item) => {
                    const Icon = item.icon;
                    return (
                      <RevealItem key={item.title}>
                        <div className="flex items-start gap-3">
                          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
                            <Icon className="h-4 w-4" aria-hidden="true" />
                          </span>
                          <div>
                            <p className="text-sm font-semibold text-white">{item.title}</p>
                            <p className="mt-0.5 text-xs text-slate-400">{item.description}</p>
                          </div>
                        </div>
                      </RevealItem>
                    );
                  })}
                </RevealGroup>
              </div>
            </div>
          </Reveal>
        </div>
      </Section>
    </>
  );
}
