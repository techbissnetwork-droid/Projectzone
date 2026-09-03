import type { Metadata } from "next";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { pillars, principles, capabilityStats } from "@/lib/data/company";

export const metadata: Metadata = {
  title: "About",
  description:
    "TECHBISS builds the entire digital presence of a business — start online, operate online, and grow online — as one connected engineering team.",
};

export default function AboutPage() {
  return (
    <>
      <section className="pt-36 sm:pt-40 md:pt-44">
        <Container>
          <Reveal>
            <Eyebrow>About TECHBISS</Eyebrow>
            <h1 className="mt-6 max-w-[22ch] text-balance text-[36px] font-medium leading-[1.08] tracking-[-0.02em] sm:text-[52px] md:text-[60px]">
              We don&apos;t just build websites.{" "}
              <span className="font-serif-display italic text-[var(--color-ink-muted)]">
                We build the entire digital presence of your business.
              </span>
            </h1>
            <p className="mt-7 max-w-[56ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Build from scratch. Buy ready-made. Make it yours. Every business
              starts somewhere different — our job is to give you a digital
              foundation that&apos;s actually engineered to last, and a team that
              stays with the system after it launches.
            </p>
          </Reveal>
        </Container>
      </section>

      {/* Capability stats */}
      <section className="mt-20 border-y border-[var(--color-border)] bg-[var(--color-bg-soft)] py-14 sm:mt-28 sm:py-16">
        <Container>
          <RevealGroup className="grid grid-cols-2 gap-y-10 sm:grid-cols-4" stagger={0.06}>
            {capabilityStats.map((s) => (
              <RevealItem key={s.label}>
                <div className="text-[32px] font-medium tracking-[-0.02em] text-[var(--color-ink)] sm:text-[42px]">
                  {s.stat}
                </div>
                <div className="mt-2 max-w-[18ch] text-[13px] text-[var(--color-ink-faint)]">
                  {s.label}
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      {/* Three pillars */}
      <section className="py-24 sm:py-32">
        <Container>
          <Reveal className="max-w-[640px]">
            <Eyebrow>How We Think About It</Eyebrow>
            <h2 className="mt-6 text-balance text-[30px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[42px]">
              Three stages of digital maturity. One connected platform.
            </h2>
          </Reveal>

          <RevealGroup className="mt-14 grid grid-cols-1 gap-6 lg:grid-cols-3" stagger={0.08}>
            {pillars.map((pillar, i) => (
              <RevealItem key={pillar.title}>
                <div className="flex h-full flex-col rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]/60 p-8">
                  <span className="font-mono-label text-[12px] text-[var(--color-accent-ink)]">
                    {String(i + 1).padStart(2, "0")}
                  </span>
                  <h3 className="mt-5 text-[22px] font-medium tracking-[-0.01em]">
                    {pillar.title}
                  </h3>
                  <p className="mt-3 flex-1 text-pretty text-[14px] leading-relaxed text-[var(--color-ink-muted)]">
                    {pillar.description}
                  </p>
                  <div className="mt-6 flex flex-wrap gap-1.5 border-t border-[var(--color-border)] pt-6">
                    {pillar.items.map((item) => (
                      <span
                        key={item}
                        className="rounded-full border border-[var(--color-border)] px-3 py-1 text-[11.5px] text-[var(--color-ink-faint)]"
                      >
                        {item}
                      </span>
                    ))}
                  </div>
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      {/* Principles */}
      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal className="max-w-[640px]">
            <Eyebrow>What We Value</Eyebrow>
            <h2 className="mt-6 text-balance text-[30px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[42px]">
              The principles behind every system we ship.
            </h2>
          </Reveal>

          <RevealGroup className="mt-14 grid grid-cols-1 gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3" stagger={0.06}>
            {principles.map((principle, i) => (
              <RevealItem key={principle.title}>
                <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
                  {String(i + 1).padStart(2, "0")}
                </span>
                <h3 className="mt-4 text-[17px] font-medium tracking-[-0.01em]">
                  {principle.title}
                </h3>
                <p className="mt-2.5 max-w-[38ch] text-pretty text-[14px] leading-relaxed text-[var(--color-ink-faint)]">
                  {principle.description}
                </p>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      {/* Team framing (no fabricated individuals) */}
      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <div className="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16">
            <Reveal>
              <Eyebrow>The Team</Eyebrow>
              <h2 className="mt-6 max-w-[16ch] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[38px]">
                Engineers, designers and strategists, working as one.
              </h2>
            </Reveal>
            <Reveal delay={0.08}>
              <p className="text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[16px]">
                TECHBISS is built around a single collective — product engineers,
                interface designers, infrastructure specialists and growth
                strategists — organized around your project rather than siloed by
                department. You work with one accountable team from the first
                discovery call through years of ongoing growth, not a rotating
                cast of subcontractors.
              </p>
              <p className="mt-4 text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[16px]">
                That team builds every custom project in{" "}
                <span className="text-[var(--color-ink)]">/services</span> and
                maintains every template in the{" "}
                <span className="text-[var(--color-ink)]">/marketplace</span> — so
                whichever path you choose, the same engineering standard applies.
              </p>
            </Reveal>
          </div>
        </Container>
      </section>

      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[400px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[20ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Let&apos;s build your digital presence.
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/marketplace" variant="secondary" size="lg">
              Browse Themes
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
