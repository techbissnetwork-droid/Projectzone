import type { Metadata } from "next";
import Link from "next/link";
import * as Icons from "lucide-react";
import { Section, Eyebrow } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { MotionDiv } from "@/components/ui/motion-div";
import { solutions } from "@/lib/data/solutions";

export const metadata: Metadata = {
  title: "Solutions",
  description:
    "Business problem, digital opportunity, TECHBISS solution — industry-specific digital transformation for restaurants, retail, schools, hospitals and more.",
};

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export default function SolutionsPage() {
  return (
    <>
      <PageHero
        eyebrow="Solutions"
        title="Digital transformation, mapped to your industry."
        subtitle="Every business is different. We start from the real problem your industry faces, not a generic template."
      />

      <Section>
        <RevealGroup className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {solutions.map((s) => (
            <MotionDiv key={s.slug} variants={revealItem}>
              <Link
                href={`/solutions/${s.slug}`}
                className="group flex h-full flex-col justify-between rounded-2xl border border-line-dark bg-ink-900/40 p-7 transition-colors hover:border-line-dark-strong"
              >
                <div>
                  <span className="flex size-11 items-center justify-center rounded-xl border border-line-dark bg-ink-950 text-gold-400">
                    <Icon name={s.icon} className="size-5" />
                  </span>
                  <h3 className="mt-6 text-[18px] font-medium text-paper-50">{s.name}</h3>
                  <p className="mt-2 text-[13.5px] leading-relaxed text-paper-50/50">
                    {s.problem}
                  </p>
                </div>
                <span className="mt-7 flex items-center gap-1.5 text-[13px] font-medium text-paper-50/60 group-hover:text-gold-400">
                  See the solution
                  <Icons.ArrowUpRight className="size-3.5" />
                </span>
              </Link>
            </MotionDiv>
          ))}
        </RevealGroup>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <Eyebrow className="justify-center">Don&rsquo;t see your industry?</Eyebrow>
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            We build for businesses that don&rsquo;t fit a template.
          </h2>
          <Button href="/contact" size="lg" arrow>
            Talk to TECHBISS
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
