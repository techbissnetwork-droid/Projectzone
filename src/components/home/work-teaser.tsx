import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { caseStudies } from "@/lib/data/work";

export function WorkTeaser() {
  return (
    <section className="border-b border-line-dark bg-ink-900/40 py-24 sm:py-32">
      <Container wide>
        <Reveal className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <div>
            <Eyebrow>Selected Work</Eyebrow>
            <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
              Businesses we&rsquo;ve taken online.
            </h2>
          </div>
          <Button href="/work" arrow variant="ghost" className="shrink-0">
            View All Work
          </Button>
        </Reveal>

        <div className="mt-14 grid gap-5 lg:grid-cols-3">
          {caseStudies.map((cs, i) => (
            <Reveal key={cs.slug} delay={i * 0.08}>
              <Link
                href={`/work/${cs.slug}`}
                className="group flex h-full flex-col justify-between overflow-hidden rounded-2xl border border-line-dark bg-ink-950/40 transition-colors hover:border-line-dark-strong"
              >
                <div
                  className="flex aspect-[4/3] items-end p-6"
                  style={{
                    background: `linear-gradient(135deg, ${cs.accent}26, transparent 65%)`,
                  }}
                >
                  <span className="font-mono-label rounded-full border border-line-dark-strong bg-ink-950/70 px-2.5 py-1 text-[10px] uppercase text-paper-50/70">
                    {cs.industry} · {cs.year}
                  </span>
                </div>
                <div className="flex flex-1 flex-col gap-3 border-t border-line-dark p-6">
                  <h3 className="text-[19px] font-medium text-paper-50">{cs.client}</h3>
                  <p className="line-clamp-2 text-[13.5px] leading-relaxed text-paper-50/50">
                    {cs.summary}
                  </p>
                  <span className="mt-2 flex items-center gap-1.5 text-[13px] font-medium text-paper-50/70 group-hover:text-gold-400">
                    Read case study
                    <ArrowUpRight className="size-3.5" />
                  </span>
                </div>
              </Link>
            </Reveal>
          ))}
        </div>
      </Container>
    </section>
  );
}
