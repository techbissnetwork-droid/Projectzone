import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight, ArrowLeft } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow, Badge } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { caseStudies, getCaseStudy } from "@/lib/data/work";

export function generateStaticParams() {
  return caseStudies.map((c) => ({ slug: c.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const project = getCaseStudy(slug);
  if (!project) return { title: "Case Study" };
  return {
    title: project.title,
    description: project.summary,
    openGraph: {
      title: `${project.title} — TECHBISS`,
      description: project.summary,
    },
  };
}

export default async function CaseStudyPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const project = getCaseStudy(slug);
  if (!project) notFound();

  const related = caseStudies
    .filter((c) => c.slug !== project.slug)
    .filter((c) => c.category === project.category || c.industry === project.industry)
    .concat(caseStudies.filter((c) => c.slug !== project.slug))
    .filter((c, i, arr) => arr.findIndex((x) => x.slug === c.slug) === i)
    .slice(0, 3);

  return (
    <>
      <section className="relative overflow-hidden pt-36 sm:pt-40 md:pt-44">
        <div
          aria-hidden
          className="pointer-events-none absolute -right-32 top-0 size-[520px] rounded-full blur-[120px]"
          style={{ background: project.accent, opacity: 0.14 }}
        />
        <Container className="relative">
          <Reveal>
            <Link
              href="/work"
              className="inline-flex items-center gap-2 text-[13px] font-medium text-[var(--color-ink-faint)] transition-colors hover:text-[var(--color-ink)]"
            >
              <ArrowLeft className="size-3.5" />
              All work
            </Link>
          </Reveal>

          <Reveal delay={0.05} className="mt-8 flex flex-wrap items-center gap-3">
            <Badge tone="accent">{project.category}</Badge>
            <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">
              {project.year}
            </span>
          </Reveal>

          <Reveal delay={0.1}>
            <h1 className="mt-6 max-w-[22ch] text-balance text-[34px] font-medium leading-[1.06] tracking-[-0.02em] sm:text-[52px] md:text-[62px]">
              {project.title}
            </h1>
          </Reveal>

          <Reveal delay={0.15} className="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-[14px] text-[var(--color-ink-muted)]">
            <span className="font-medium text-[var(--color-ink)]">{project.client}</span>
            <span className="text-[var(--color-ink-faint)]">{project.industry}</span>
          </Reveal>

          <Reveal delay={0.2}>
            <p className="mt-7 max-w-[64ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              {project.summary}
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="mt-20 sm:mt-28">
        <Container>
          <div className="grid grid-cols-1 gap-10 border-t border-[var(--color-border)] pt-14 sm:pt-16 lg:grid-cols-2 lg:gap-16">
            <Reveal>
              <Eyebrow index="01">The Problem</Eyebrow>
              <p className="mt-6 text-pretty text-[18px] font-medium leading-relaxed tracking-[-0.01em] text-[var(--color-ink)] sm:text-[22px]">
                {project.problem}
              </p>
            </Reveal>
            <Reveal delay={0.08}>
              <Eyebrow index="02">The Solution</Eyebrow>
              <p className="mt-6 text-pretty text-[18px] font-medium leading-relaxed tracking-[-0.01em] text-[var(--color-ink)] sm:text-[22px]">
                {project.solution}
              </p>
            </Reveal>
          </div>
        </Container>
      </section>

      <section className="mt-20 sm:mt-28">
        <Container>
          <Reveal>
            <Eyebrow>Technology &amp; Systems</Eyebrow>
          </Reveal>
          <RevealGroup className="mt-6 flex flex-wrap gap-2.5" stagger={0.04}>
            {project.tech.map((t) => (
              <RevealItem key={t}>
                <span className="inline-flex rounded-full border border-[var(--color-border-strong)] px-4 py-2 text-[13px] text-[var(--color-ink-muted)]">
                  {t}
                </span>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      <section className="mt-20 border-y border-[var(--color-border)] bg-[var(--color-bg-soft)] py-16 sm:mt-28 sm:py-20">
        <Container>
          <Reveal>
            <Eyebrow>Results</Eyebrow>
          </Reveal>
          <RevealGroup className="mt-8 grid grid-cols-1 gap-10 sm:grid-cols-3" stagger={0.08}>
            {project.results.map((r) => (
              <RevealItem key={r.label}>
                <div
                  className="text-[44px] font-medium leading-none tracking-[-0.02em] sm:text-[56px]"
                  style={{ color: project.accent }}
                >
                  {r.stat}
                </div>
                <div className="mt-3 max-w-[24ch] text-[14px] text-[var(--color-ink-muted)]">
                  {r.label}
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      <section className="py-24 sm:py-32">
        <Container>
          <Reveal className="flex flex-col items-end justify-between gap-6 sm:flex-row">
            <div className="max-w-[560px]">
              <Eyebrow>More Work</Eyebrow>
              <h2 className="mt-6 text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[38px]">
                Related case studies
              </h2>
            </div>
          </Reveal>

          <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
            {related.map((c, i) => (
              <Reveal key={c.slug} delay={i * 0.06}>
                <Link
                  href={`/work/${c.slug}`}
                  className="group flex h-full flex-col rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]/60 p-6 transition-colors duration-300 hover:border-[var(--color-border-strong)] hover:bg-[var(--color-surface)]"
                >
                  <span
                    className="font-mono-label text-[11px] uppercase"
                    style={{ color: c.accent }}
                  >
                    {c.category}
                  </span>
                  <h3 className="mt-3 flex-1 text-[17px] font-medium leading-snug tracking-[-0.01em] text-[var(--color-ink)] transition-colors group-hover:text-[var(--color-accent-ink)]">
                    {c.title}
                  </h3>
                  <span className="mt-5 inline-flex items-center gap-1.5 text-[12.5px] font-medium text-[var(--color-ink-faint)] transition-colors group-hover:text-[var(--color-ink)]">
                    Read case study
                    <ArrowUpRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                  </span>
                </Link>
              </Reveal>
            ))}
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
              Ready for a transformation like this?
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
            <Button href="/work" variant="secondary" size="lg">
              See More Work
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
