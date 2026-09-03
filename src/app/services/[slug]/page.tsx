import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import * as Icons from "lucide-react";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { services, getService } from "@/lib/data/services";
import { Check } from "lucide-react";

export function generateStaticParams() {
  return services.map((s) => ({ slug: s.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const service = getService(slug);
  if (!service) return {};
  return {
    title: service.name,
    description: service.summary,
  };
}

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export default async function ServiceDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const service = getService(slug);
  if (!service) notFound();

  const related = services.filter((s) => service.related.includes(s.slug));

  return (
    <>
      <section className="border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20">
        <Container wide>
          <Link
            href="/services"
            className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
          >
            <Icons.ArrowLeft className="size-3.5" />
            All Services
          </Link>
          <div className="mt-8 flex items-start gap-5">
            <span className="hidden size-14 shrink-0 items-center justify-center rounded-2xl border border-line-dark bg-ink-900 text-gold-400 sm:flex">
              <Icon name={service.icon} className="size-6" />
            </span>
            <div>
              <Eyebrow>{service.eyebrow}</Eyebrow>
              <h1 className="mt-4 max-w-2xl text-balance text-[34px] font-medium leading-[1.08] tracking-[-0.02em] text-paper-50 sm:text-[50px]">
                {service.headline}
              </h1>
              <p className="mt-6 max-w-xl text-[16px] leading-relaxed text-paper-50/55">
                {service.summary}
              </p>
              <div className="mt-8 flex flex-wrap gap-3">
                <Button href="/contact" arrow>
                  Start This Project
                </Button>
                <Button href="/marketplace" variant="ghost">
                  See Ready-Made Options
                </Button>
              </div>
            </div>
          </div>
        </Container>
      </section>

      <Section>
        <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <div className="font-mono-label text-[11px] uppercase text-paper-50/40">
              The Problem
            </div>
            <p className="mt-4 text-[19px] leading-relaxed text-paper-50/80 sm:text-[22px]">
              {service.problem}
            </p>
          </Reveal>
          <Reveal delay={0.1}>
            <div className="font-mono-label text-[11px] uppercase text-gold-400">
              The TECHBISS Solution
            </div>
            <p className="mt-4 text-[19px] leading-relaxed text-paper-50/80 sm:text-[22px]">
              {service.solution}
            </p>
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal>
          <Eyebrow>What&rsquo;s Included</Eyebrow>
          <h2 className="mt-5 max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Benefits built into every {service.name.toLowerCase()} engagement.
          </h2>
        </Reveal>
        <div className="mt-12 grid gap-3 sm:grid-cols-2">
          {service.benefits.map((b, i) => (
            <Reveal key={b} delay={i * 0.05} className="flex items-start gap-3 rounded-xl border border-line-dark bg-ink-950/40 p-5">
              <Check className="mt-0.5 size-4 shrink-0 text-gold-400" />
              <span className="text-[14px] leading-relaxed text-paper-50/75">{b}</span>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal>
          <Eyebrow>Our Process</Eyebrow>
          <h2 className="mt-5 max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            How we deliver it.
          </h2>
        </Reveal>
        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {service.process.map((step, i) => (
            <Reveal key={step.title} delay={i * 0.06} className="rounded-xl border border-line-dark bg-ink-900/40 p-5">
              <div className="font-mono-label text-[11px] text-gold-400">
                {String(i + 1).padStart(2, "0")}
              </div>
              <div className="mt-3 text-[15px] font-medium text-paper-50">{step.title}</div>
              <p className="mt-2 text-[12.5px] leading-relaxed text-paper-50/50">{step.detail}</p>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <div className="grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:items-center">
          <Reveal>
            <Eyebrow>Technology</Eyebrow>
            <h2 className="mt-5 text-[24px] font-medium leading-tight tracking-tight text-paper-50">
              {service.outcome}
            </h2>
          </Reveal>
          <Reveal delay={0.1} className="flex flex-wrap gap-2.5">
            {service.technology.map((t) => (
              <span
                key={t}
                className="font-mono-label rounded-full border border-line-dark-strong px-3.5 py-2 text-[11px] uppercase text-paper-50/65"
              >
                {t}
              </span>
            ))}
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal>
          <Eyebrow>Related Services</Eyebrow>
        </Reveal>
        <div className="mt-8 grid gap-4 sm:grid-cols-3">
          {related.map((r, i) => (
            <Reveal key={r.slug} delay={i * 0.06}>
              <Link
                href={`/services/${r.slug}`}
                className="group flex items-center justify-between rounded-xl border border-line-dark bg-ink-900/40 p-5 transition-colors hover:border-line-dark-strong"
              >
                <div className="flex items-center gap-3">
                  <Icon name={r.icon} className="size-4.5 text-gold-400" />
                  <span className="text-[14px] font-medium text-paper-50">{r.name}</span>
                </div>
                <Icons.ArrowUpRight className="size-4 text-paper-50/30 transition-colors group-hover:text-gold-400" />
              </Link>
            </Reveal>
          ))}
        </div>
      </Section>
    </>
  );
}
