import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { processStages } from "@/lib/data/process";

export function ProcessTeaser() {
  return (
    <section className="py-24 sm:py-32">
      <Container>
        <div className="flex flex-col items-end justify-between gap-6 sm:flex-row">
          <Reveal className="max-w-[560px]">
            <Eyebrow>The Process</Eyebrow>
            <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
              Discover. Build. Brand. Launch. Grow.
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="hidden shrink-0 sm:block">
            <Button href="/process" variant="secondary">
              See the Full Process
            </Button>
          </Reveal>
        </div>

        <RevealGroup className="mt-14 grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 lg:grid-cols-6" stagger={0.06}>
          {processStages.map((stage) => (
            <RevealItem key={stage.index}>
              <span className="font-mono-label text-[12px] text-[var(--color-accent-ink)]">
                {stage.index}
              </span>
              <h3 className="mt-3 text-[16px] font-medium">{stage.title}</h3>
              <p className="mt-2 text-[13px] leading-relaxed text-[var(--color-ink-faint)]">
                {stage.description}
              </p>
            </RevealItem>
          ))}
        </RevealGroup>

        <Reveal className="mt-10 sm:hidden">
          <Button href="/process" variant="secondary" className="w-full">
            See the Full Process
          </Button>
        </Reveal>
      </Container>
    </section>
  );
}
