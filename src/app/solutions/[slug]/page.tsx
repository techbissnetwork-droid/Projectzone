import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import * as Icons from "lucide-react";
import { ArrowRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { solutions, getSolution } from "@/lib/data/solutions";

export function generateStaticParams() {
  return solutions.map((s) => ({ slug: s.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const solution = getSolution(slug);
  if (!solution) return {};
  return { title: solution.name, description: solution.headline };
}

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export default async function SolutionDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const solution = getSolution(slug);
  if (!solution) notFound();

  return (
    <>
      <section className="border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20">
        <Container wide>
          <Link
            href="/solutions"
            className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
          >
            <Icons.ArrowLeft className="size-3.5" />
            All Solutions
          </Link>
          <div className="mt-8 flex items-start gap-5">
            <span className="hidden size-14 shrink-0 items-center justify-center rounded-2xl border border-line-dark bg-ink-900 text-gold-400 sm:flex">
              <Icon name={solution.icon} className="size-6" />
            </span>
            <div>
              <Eyebrow>{solution.eyebrow}</Eyebrow>
              <h1 className="mt-4 max-w-2xl text-balance text-[34px] font-medium leading-[1.08] tracking-[-0.02em] text-paper-50 sm:text-[50px]">
                {solution.headline}
              </h1>
              <div className="mt-8">
                <Button href="/contact" arrow>
                  Start This Project
                </Button>
              </div>
            </div>
          </div>
        </Container>
      </section>

      <Section>
        <div className="grid gap-8 lg:grid-cols-3">
          {[
            { label: "Business Problem", copy: solution.problem },
            { label: "Digital Opportunity", copy: solution.opportunity },
            { label: "TECHBISS Solution", copy: solution.solution, accent: true },
          ].map((block, i) => (
            <Reveal
              key={block.label}
              delay={i * 0.08}
              className={
                block.accent
                  ? "rounded-2xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-7"
                  : "rounded-2xl border border-line-dark bg-ink-900/40 p-7"
              }
            >
              <div
                className={`font-mono-label text-[11px] uppercase ${
                  block.accent ? "text-gold-400" : "text-paper-50/40"
                }`}
              >
                {block.label}
              </div>
              <p className="mt-4 text-[15px] leading-relaxed text-paper-50/80">{block.copy}</p>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal>
          <Eyebrow>The Expected System</Eyebrow>
          <h2 className="mt-5 max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            What gets built.
          </h2>
        </Reveal>
        <div className="mt-10 flex flex-wrap items-center gap-3">
          {solution.system.map((item, i) => (
            <Reveal key={item} delay={i * 0.05} className="flex items-center gap-3">
              <span className="rounded-full border border-line-dark-strong bg-ink-950/50 px-4 py-2.5 text-[13px] font-medium text-paper-50/80">
                {item}
              </span>
              {i < solution.system.length - 1 && (
                <ArrowRight className="size-4 shrink-0 text-paper-50/25" />
              )}
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal>
          <Eyebrow>The Transformation</Eyebrow>
        </Reveal>
        <div className="mt-8 grid gap-3">
          {solution.transformation.map((t, i) => (
            <Reveal
              key={i}
              delay={i * 0.06}
              className="flex flex-col items-start gap-3 rounded-xl border border-line-dark bg-ink-900/30 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
              <span className="text-[14px] text-paper-50/50">{t.from}</span>
              <ArrowRight className="hidden size-4 text-gold-400 sm:block" />
              <span className="text-[14px] font-medium text-paper-50">{t.to}</span>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Ready to bring {solution.name.toLowerCase()} online?
          </h2>
          <div className="flex flex-wrap justify-center gap-3">
            <Button href="/contact" size="lg" arrow>
              Start Project
            </Button>
            <Button href="/marketplace" size="lg" variant="ghost">
              Browse Ready-Made Themes
            </Button>
          </div>
        </Reveal>
      </Section>
    </>
  );
}
