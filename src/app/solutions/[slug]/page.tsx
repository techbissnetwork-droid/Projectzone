import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ChainFlow } from "@/components/solutions/chain-flow";
import { RelatedServices } from "@/components/solutions/related-services";
import { solutions, getSolution } from "@/lib/data/solutions";

export function generateStaticParams() {
  return solutions.map((solution) => ({ slug: solution.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const solution = getSolution(slug);
  if (!solution) return {};
  return {
    title: solution.name,
    description: solution.tagline,
  };
}

export default async function SolutionDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const solution = getSolution(slug);
  if (!solution) notFound();

  const currentIdx = solutions.findIndex((s) => s.slug === solution.slug);
  const moreIndustries = Array.from(
    { length: 3 },
    (_, i) => solutions[(currentIdx + i + 1) % solutions.length],
  );

  return (
    <>
      {/* Hero */}
      <section className="relative overflow-hidden pt-36 sm:pt-40 md:pt-44">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-20 h-[380px] w-[720px] -translate-x-1/2 rounded-full opacity-[0.16] blur-[100px]"
          style={{ background: solution.accent }}
        />
        <Container className="relative">
          <Reveal className="flex items-center gap-3">
            <span
              className="inline-flex rounded-full px-3 py-1 text-[12px] font-medium"
              style={{ backgroundColor: `${solution.accent}22`, color: solution.accent }}
            >
              {solution.name}
            </span>
            <Eyebrow>Solutions</Eyebrow>
          </Reveal>
          <Reveal delay={0.06}>
            <h1 className="mt-6 max-w-[20ch] text-balance text-[36px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[52px] md:text-[60px]">
              {solution.tagline}
            </h1>
          </Reveal>
          <Reveal delay={0.18} className="mt-9 flex flex-col gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start This Transformation
            </Button>
            <Button href="/solutions" variant="secondary" size="lg" icon={false}>
              All Industries
            </Button>
          </Reveal>
        </Container>
      </section>

      {/* Problem -> Solution -> System narrative */}
      <section className="mt-24 sm:mt-32">
        <Container>
          <div className="flex flex-col divide-y divide-[var(--color-border)] border-y border-[var(--color-border)]">
            {/* 01 Problem */}
            <Reveal className="grid grid-cols-1 gap-4 py-10 sm:grid-cols-[100px_1fr] sm:gap-8 sm:py-12">
              <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
                01 / Problem
              </span>
              <div>
                <h2 className="text-[22px] font-medium tracking-[-0.01em] text-[var(--color-ink)] sm:text-[26px]">
                  The business problem
                </h2>
                <p className="mt-4 max-w-[62ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
                  {solution.problem}
                </p>
              </div>
            </Reveal>

            {/* 02 Digital Solution */}
            <Reveal delay={0.06} className="grid grid-cols-1 gap-4 py-10 sm:grid-cols-[100px_1fr] sm:gap-8 sm:py-12">
              <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
                02 / Solution
              </span>
              <div>
                <h2 className="text-[22px] font-medium tracking-[-0.01em] text-[var(--color-ink)] sm:text-[26px]">
                  The digital transformation
                </h2>
                <p className="mt-4 max-w-[62ch] text-pretty text-[14.5px] leading-relaxed text-[var(--color-ink-faint)]">
                  How {solution.name.toLowerCase()} moves from its current state to a
                  fully connected digital operation.
                </p>
                <div className="mt-6 rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6 sm:p-8">
                  <ChainFlow solution={solution} />
                </div>
              </div>
            </Reveal>

            {/* 03 TECHBISS System */}
            <Reveal delay={0.12} className="grid grid-cols-1 gap-4 py-10 sm:grid-cols-[100px_1fr] sm:gap-8 sm:py-12">
              <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
                03 / System
              </span>
              <div>
                <h2 className="text-[22px] font-medium tracking-[-0.01em] text-[var(--color-ink)] sm:text-[26px]">
                  The TECHBISS system
                </h2>
                <p className="mt-4 max-w-[62ch] text-pretty text-[14.5px] leading-relaxed text-[var(--color-ink-faint)]">
                  The connected systems running underneath, built and maintained by
                  TECHBISS.
                </p>
                <RevealGroup className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2" stagger={0.05}>
                  {solution.systems.map((system) => (
                    <RevealItem key={system}>
                      <div className="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-4">
                        <span
                          className="size-2 shrink-0 rounded-full"
                          style={{ backgroundColor: solution.accent }}
                        />
                        <span className="text-[14.5px] font-medium text-[var(--color-ink)]">{system}</span>
                      </div>
                    </RevealItem>
                  ))}
                </RevealGroup>
              </div>
            </Reveal>
          </div>
        </Container>
      </section>

      {/* Related services */}
      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal>
            <Eyebrow>Related Services</Eyebrow>
            <h2 className="mt-6 max-w-[26ch] text-balance text-[26px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[34px]">
              The services behind this transformation.
            </h2>
          </Reveal>
          <div className="mt-10">
            <RelatedServices solution={solution} />
          </div>
        </Container>
      </section>

      {/* More industries */}
      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal className="flex flex-col items-end justify-between gap-6 sm:flex-row">
            <div className="max-w-[560px]">
              <Eyebrow>More Industries</Eyebrow>
              <h2 className="mt-6 text-balance text-[26px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[34px]">
                See how other businesses transform.
              </h2>
            </div>
          </Reveal>
          <div className="mt-10 flex flex-col divide-y divide-[var(--color-border)]">
            {moreIndustries.map((s, i) => (
              <Reveal key={s.slug} delay={i * 0.05}>
                <Link
                  href={`/solutions/${s.slug}`}
                  className="group flex items-center justify-between gap-6 py-6"
                >
                  <div className="flex items-center gap-4">
                    <span
                      className="inline-flex rounded-full px-3 py-1 text-[12px] font-medium"
                      style={{ backgroundColor: `${s.accent}22`, color: s.accent }}
                    >
                      {s.name}
                    </span>
                    <span className="hidden text-[14px] text-[var(--color-ink-muted)] sm:inline">
                      {s.tagline}
                    </span>
                  </div>
                  <ArrowUpRight className="size-4 shrink-0 text-[var(--color-ink-faint)] transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-[var(--color-ink)]" />
                </Link>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      {/* CTA */}
      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[420px] w-[820px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[22ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Ready to transform your {solution.name.toLowerCase()}?
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="mx-auto mt-5 max-w-[54ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
              Tell us where you are today and we&apos;ll map the exact path to a
              connected digital system.
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
