import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, Check } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
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
  const cs = getCaseStudy(slug);
  if (!cs) return {};
  return { title: cs.client, description: cs.summary };
}

export default async function CaseStudyPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const cs = getCaseStudy(slug);
  if (!cs) notFound();

  return (
    <>
      <section className="border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20">
        <Container wide>
          <Link
            href="/work"
            className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
          >
            <ArrowLeft className="size-3.5" />
            All Work
          </Link>
          <Eyebrow className="mt-8">
            {cs.industry} · {cs.year}
          </Eyebrow>
          <h1 className="mt-5 max-w-2xl text-balance text-[38px] font-medium leading-[1.06] tracking-[-0.02em] text-paper-50 sm:text-[56px]">
            {cs.client}
          </h1>
          <p className="mt-6 max-w-xl text-[16px] leading-relaxed text-paper-50/55">
            {cs.summary}
          </p>
        </Container>
      </section>

      <div
        className="h-[280px] border-b border-line-dark sm:h-[420px]"
        style={{
          background: `linear-gradient(135deg, ${cs.accent}2e, transparent 70%), radial-gradient(circle at 25% 30%, ${cs.accent}22, transparent 60%)`,
        }}
      />

      <Section>
        <div className="grid gap-10 lg:grid-cols-3">
          <Reveal>
            <div className="font-mono-label text-[11px] uppercase text-paper-50/40">Problem</div>
            <p className="mt-4 text-[15px] leading-relaxed text-paper-50/75">{cs.problem}</p>
          </Reveal>
          <Reveal delay={0.06}>
            <div className="font-mono-label text-[11px] uppercase text-paper-50/40">Strategy</div>
            <p className="mt-4 text-[15px] leading-relaxed text-paper-50/75">{cs.strategy}</p>
          </Reveal>
          <Reveal delay={0.12}>
            <div className="font-mono-label text-[11px] uppercase text-gold-400">Solution</div>
            <p className="mt-4 text-[15px] leading-relaxed text-paper-50/75">{cs.solution}</p>
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <div className="grid gap-10 lg:grid-cols-[1fr_1.2fr]">
          <Reveal>
            <Eyebrow>Results</Eyebrow>
            <div className="mt-6 flex flex-col gap-4">
              {cs.result.map((r) => (
                <div key={r} className="flex items-start gap-3">
                  <Check className="mt-0.5 size-4 shrink-0 text-gold-400" />
                  <span className="text-[15px] leading-relaxed text-paper-50/80">{r}</span>
                </div>
              ))}
            </div>
          </Reveal>
          <Reveal delay={0.1}>
            <Eyebrow>Technology</Eyebrow>
            <div className="mt-6 flex flex-wrap gap-2.5">
              {cs.technology.map((t) => (
                <span
                  key={t}
                  className="font-mono-label rounded-full border border-line-dark-strong px-3.5 py-2 text-[11px] uppercase text-paper-50/65"
                >
                  {t}
                </span>
              ))}
            </div>
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Ready to build something like this?
          </h2>
          <Button href="/contact" size="lg" arrow>
            Start Your Project
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
