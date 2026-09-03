import type { Metadata } from "next";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { SolutionsIndexGrid } from "@/components/solutions/solutions-index-grid";

export const metadata: Metadata = {
  title: "Solutions",
  description:
    "See how TECHBISS transforms real businesses — restaurants, retail, schools, hospitals and more — from offline operations into connected digital systems.",
};

export default function SolutionsPage() {
  return (
    <>
      <section className="pt-36 sm:pt-40 md:pt-44">
        <Container>
          <Reveal className="max-w-[720px]">
            <Eyebrow>Solutions</Eyebrow>
            <h1 className="mt-6 text-balance text-[38px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[54px] md:text-[64px]">
              Built around real businesses, not generic templates.
            </h1>
            <p className="mt-6 max-w-[56ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Every industry transforms differently. See how businesses like yours
              move from offline operations to a fully connected digital system —
              built on the same architecture that powers TECHBISS itself.
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="mt-20 pb-24 sm:mt-24 sm:pb-32">
        <Container>
          <SolutionsIndexGrid />
        </Container>
      </section>

      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[420px] w-[820px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(201,164,99,0.12),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[20ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Don&apos;t see your industry?
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="mx-auto mt-5 max-w-[54ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
              The same connected architecture applies to any business. Tell us how
              you operate today and we&apos;ll map your transformation.
            </p>
          </Reveal>
          <Reveal delay={0.2} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start a Project
            </Button>
            <Button href="/services" variant="secondary" size="lg" icon={false}>
              View All Services
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
