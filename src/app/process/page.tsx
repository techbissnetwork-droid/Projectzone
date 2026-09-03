import type { Metadata } from "next";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { ProcessJourney } from "@/components/process/process-journey";

export const metadata: Metadata = {
  title: "Process",
  description:
    "The six-stage TECHBISS process — Discover, Choose/Design, Build, Brand, Launch, Grow — from first conversation to a digital system that keeps improving.",
};

export default function ProcessPage() {
  return (
    <>
      <section className="pt-36 sm:pt-40 md:pt-44">
        <Container>
          <Reveal>
            <Eyebrow>The Process</Eyebrow>
            <h1 className="mt-6 max-w-[18ch] text-balance text-[38px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[56px] md:text-[64px]">
              Discover. Build. Brand. Launch. Grow.
            </h1>
            <p className="mt-6 max-w-[60ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Every project moves through the same six stages — whether you&apos;re
              building something completely custom or starting from a marketplace
              theme. Scroll through to see how a business goes from idea to a system
              that keeps growing.
            </p>
          </Reveal>
        </Container>
      </section>

      <div className="mt-14 sm:mt-20">
        <ProcessJourney />
      </div>

      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[400px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[20ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Ready to start Stage One?
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/work" variant="secondary" size="lg">
              See the Results
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
