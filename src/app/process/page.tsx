import type { Metadata } from "next";
import { ShieldCheck, Layers, MessageCircle } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { ProcessJourney } from "@/components/process/ProcessJourney";
import { processSteps } from "@/lib/data/process";

export const metadata: Metadata = {
  title: "Process",
  description:
    "How TECHBISS takes a business from Discover to Grow — a transparent, five-stage process for building and launching your digital presence.",
};

const principles = [
  {
    icon: MessageCircle,
    title: "Transparent at every step",
    desc: "You always know what stage you're in, what's next, and what we need from you.",
  },
  {
    icon: Layers,
    title: "Built on real systems",
    desc: "No page builders held together with plugins — proper engineering, built to extend.",
  },
  {
    icon: ShieldCheck,
    title: "Nothing ships without you",
    desc: "Design and key milestones are reviewed and approved before we move forward.",
  },
];

export default function ProcessPage() {
  return (
    <>
      <PageHero
        eyebrow="Our Process"
        title="A clear path from idea to a growing digital business."
        lead="Scroll through the five stages every TECHBISS project moves through — the same process whether you're launching a website or an entire digital ecosystem."
      />

      <ProcessJourney />

      <section className="py-24 md:py-32">
        <Container>
          <SectionHeading
            eyebrow="Why It Works"
            title="A process built on trust, not guesswork."
            lead="Digital transformation fails when it's a black box. Ours isn't."
          />
          <div className="mt-14 grid gap-8 sm:grid-cols-3">
            {principles.map((p, i) => (
              <Reveal key={p.title} delay={0.08 * i}>
                <div className="flex flex-col gap-4">
                  <span className="flex size-11 items-center justify-center rounded-full border border-line-strong text-gold-bright">
                    <p.icon className="size-5" aria-hidden />
                  </span>
                  <h3 className="text-lg font-medium text-paper">{p.title}</h3>
                  <p className="text-sm leading-relaxed text-paper-dim">{p.desc}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-line bg-ink-raised py-20 md:py-28">
        <Container>
          <SectionHeading
            eyebrow="Typical Timeline"
            tone="signal"
            title="From first call to a live digital business."
          />
          <div className="mt-12 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-5">
            {processSteps.map((s) => (
              <div key={s.slug} className="flex flex-col gap-2 bg-ink-raised-2 p-6">
                <span className="text-eyebrow text-paper-faint">{s.index}</span>
                <span className="font-medium text-paper">{s.title}</span>
                <span className="mt-auto pt-3 text-sm text-signal-bright">{s.duration}</span>
              </div>
            ))}
          </div>
        </Container>
      </section>

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone="gold">Ready to Begin?</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Discovery starts with a single conversation.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/work" variant="secondary" size="lg">
              See What We&apos;ve Built
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}
