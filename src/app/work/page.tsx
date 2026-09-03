import type { Metadata } from "next";
import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Section } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal } from "@/components/ui/reveal";
import { caseStudies } from "@/lib/data/work";

export const metadata: Metadata = {
  title: "Work",
  description:
    "Case studies of businesses TECHBISS has taken from offline operations to complete digital ecosystems.",
};

export default function WorkPage() {
  return (
    <>
      <PageHero
        eyebrow="Selected Work"
        title="Businesses we've taken online."
        subtitle="Each project is a different business, problem and outcome — not a template applied twice."
      />

      <Section className="!pt-0">
        <div className="flex flex-col divide-y divide-line-dark border-t border-line-dark">
          {caseStudies.map((cs) => (
            <Reveal key={cs.slug}>
              <Link
                href={`/work/${cs.slug}`}
                className="group grid gap-8 py-14 sm:grid-cols-[auto_1fr_auto] sm:items-center"
              >
                <div
                  className="hidden aspect-square w-40 shrink-0 rounded-2xl sm:block"
                  style={{
                    background: `linear-gradient(135deg, ${cs.accent}33, transparent 70%)`,
                    border: "1px solid var(--line-dark)",
                  }}
                />
                <div>
                  <span className="font-mono-label text-[11px] uppercase text-gold-400">
                    {cs.industry} · {cs.year}
                  </span>
                  <h2 className="mt-3 text-[28px] font-medium tracking-tight text-paper-50 sm:text-[36px]">
                    {cs.client}
                  </h2>
                  <p className="mt-3 max-w-xl text-[14.5px] leading-relaxed text-paper-50/50">
                    {cs.summary}
                  </p>
                </div>
                <span className="flex size-12 shrink-0 items-center justify-center rounded-full border border-line-dark-strong text-paper-50 transition-colors group-hover:border-gold-400 group-hover:text-gold-400">
                  <ArrowUpRight className="size-5" />
                </span>
              </Link>
            </Reveal>
          ))}
        </div>
      </Section>
    </>
  );
}
