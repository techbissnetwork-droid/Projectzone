import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { Hammer, Layers } from "lucide-react";

export function TwoPaths() {
  return (
    <section className="py-24 sm:py-32">
      <Container>
        <Reveal className="mx-auto max-w-[640px] text-center">
          <Eyebrow className="justify-center">Two Ways to Start</Eyebrow>
          <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
            However you start, you land in the same ecosystem.
          </h2>
        </Reveal>

        <div className="mt-14 grid gap-5 lg:grid-cols-2">
          <Reveal delay={0.05}>
            <div className="group relative flex h-full flex-col justify-between overflow-hidden rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 transition-colors duration-300 hover:border-[var(--color-accent-soft)] sm:p-10">
              <div
                aria-hidden
                className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-[var(--color-accent-soft)] opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"
              />
              <div className="relative">
                <div className="flex size-11 items-center justify-center rounded-full border border-[var(--color-border-strong)]">
                  <Hammer className="size-5 text-[var(--color-accent-ink)]" strokeWidth={1.5} />
                </div>
                <h3 className="mt-6 text-[22px] font-medium tracking-[-0.01em] sm:text-[26px]">
                  Build Custom
                </h3>
                <p className="mt-3 max-w-[38ch] text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
                  Your business. Your technology. Built from zero. For businesses requiring
                  unique functionality, custom systems and full architectural control.
                </p>
              </div>
              <div className="relative mt-10">
                <Button href="/contact" variant="secondary">
                  Build With TECHBISS
                </Button>
              </div>
            </div>
          </Reveal>

          <Reveal delay={0.15}>
            <div className="group relative flex h-full flex-col justify-between overflow-hidden rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 transition-colors duration-300 hover:border-[var(--color-gold-soft)] sm:p-10">
              <div
                aria-hidden
                className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-[var(--color-gold-soft)] opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"
              />
              <div className="relative">
                <div className="flex size-11 items-center justify-center rounded-full border border-[var(--color-border-strong)]">
                  <Layers className="size-5 text-[var(--color-gold)]" strokeWidth={1.5} />
                </div>
                <h3 className="mt-6 text-[22px] font-medium tracking-[-0.01em] sm:text-[26px]">
                  Buy a Theme
                </h3>
                <p className="mt-3 max-w-[38ch] text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
                  Start with something already built. Choose a professionally designed
                  theme, customize it with your logo, colors and content, then launch it
                  under your own brand.
                </p>
              </div>
              <div className="relative mt-10">
                <Button href="/marketplace" variant="secondary">
                  Browse Marketplace
                </Button>
              </div>
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
