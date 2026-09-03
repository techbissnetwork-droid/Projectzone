import { Container } from "@/components/ui/container";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";

export function FinalCta() {
  return (
    <section className="relative overflow-hidden border-t border-[var(--color-border)] py-28 sm:py-40">
      <div
        aria-hidden
        className="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[900px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
      />
      <Container className="relative text-center">
        <Reveal>
          <h2 className="mx-auto max-w-[18ch] text-balance text-[36px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[56px] md:text-[68px]">
            Ready to take your business online?
          </h2>
        </Reveal>
        <Reveal delay={0.1}>
          <p className="mx-auto mt-6 max-w-[54ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
            Tell us what you&apos;re building. We&apos;ll help you choose the right
            technology — whether you want something completely custom or want to start
            with a ready-made theme.
          </p>
        </Reveal>
        <Reveal delay={0.2} className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <Button href="/contact" size="lg">
            Start Your Project
          </Button>
          <Button href="/marketplace" variant="secondary" size="lg">
            Browse Themes
          </Button>
          <Button href="/contact" variant="ghost" size="lg" icon={false}>
            Talk to TECHBISS
          </Button>
        </Reveal>
      </Container>
    </section>
  );
}
