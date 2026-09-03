import type { Metadata } from "next";
import Link from "next/link";
import {
  UtensilsCrossed,
  ShoppingBag,
  GraduationCap,
  Stethoscope,
  Briefcase,
  Rocket,
  type LucideProps,
} from "lucide-react";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { solutions } from "@/lib/data/solutions";
import { getServiceBySlug } from "@/lib/data/services";

export const metadata: Metadata = {
  title: "Solutions",
  description:
    "Digital transformation solutions organized around real business types — restaurants, retail, education, healthcare, service companies and startups.",
};

const icons: Record<string, React.ComponentType<LucideProps>> = {
  restaurants: UtensilsCrossed,
  retail: ShoppingBag,
  education: GraduationCap,
  healthcare: Stethoscope,
  "service-companies": Briefcase,
  startups: Rocket,
};

export default function SolutionsPage() {
  return (
    <>
      <PageHero
        eyebrow="Solutions"
        title="Digital transformation, built around your industry."
        lead="Every business hits the same wall offline, in its own way. Here's how TECHBISS removes it — by industry, not by guesswork."
        stats={[
          { value: "6", label: "Industries mapped" },
          { value: "12", label: "Services, combined" },
        ]}
      />

      <div className="border-y border-line bg-ink-raised-2/60 py-5">
        <Container>
          <div className="no-scrollbar flex gap-2 overflow-x-auto">
            {solutions.map((s) => {
              const Icon = icons[s.slug];
              return (
                <a
                  key={s.slug}
                  href={`#${s.slug}`}
                  className="flex shrink-0 items-center gap-2 rounded-full border border-line-strong px-4 py-2 text-sm text-paper-dim transition-colors hover:border-gold/50 hover:text-paper"
                >
                  <Icon className="size-3.5" aria-hidden />
                  {s.business}
                </a>
              );
            })}
          </div>
        </Container>
      </div>

      {solutions.map((solution, i) => {
        const Icon = icons[solution.slug];
        const usedServices = solution.servicesUsed
          .map((slug) => getServiceBySlug(slug))
          .filter(Boolean);

        return (
          <section
            key={solution.slug}
            id={solution.slug}
            className={
              i % 2 === 1
                ? "scroll-mt-24 border-b border-line bg-ink-raised py-20 md:py-28"
                : "scroll-mt-24 border-b border-line py-20 md:py-28"
            }
          >
            <Container>
              <div className="flex flex-col justify-between gap-8 md:flex-row md:items-end">
                <Reveal>
                  <div className="flex items-center gap-4">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-full border border-gold/40 bg-gold-dim text-gold-bright">
                      <Icon className="size-5" aria-hidden />
                    </span>
                    <div>
                      <Eyebrow tone="gold">
                        {solution.from} → {solution.to}
                      </Eyebrow>
                      <h2 className="text-h2 mt-2 font-medium text-paper">
                        {solution.business}
                      </h2>
                    </div>
                  </div>
                </Reveal>
                <Reveal delay={0.1}>
                  <div className="rounded-xl border border-line-strong px-6 py-4 text-center md:text-right">
                    <p className="text-2xl font-semibold text-gold-bright">
                      {solution.metric.value}
                    </p>
                    <p className="mt-0.5 text-xs text-paper-faint">
                      {solution.metric.label}
                    </p>
                  </div>
                </Reveal>
              </div>

              <Reveal delay={0.06}>
                <p className="text-lead mt-8 max-w-2xl text-balance text-paper-dim">
                  {solution.narrative}
                </p>
              </Reveal>

              <div className="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
                <Reveal delay={0.1}>
                  <Eyebrow tone="neutral">The Problem</Eyebrow>
                  <ul className="mt-5 flex flex-col gap-3">
                    {solution.painPoints.map((p) => (
                      <li
                        key={p}
                        className="flex items-start gap-2.5 text-sm text-paper-dim"
                      >
                        <span className="mt-1.5 size-1 shrink-0 rounded-full bg-paper-faint" />
                        {p}
                      </li>
                    ))}
                  </ul>
                </Reveal>

                <Reveal delay={0.16}>
                  <Eyebrow tone="gold">The Digital System</Eyebrow>
                  <ul className="mt-5 flex flex-col gap-3">
                    {solution.digitalFeatures.map((f) => (
                      <li
                        key={f}
                        className="flex items-start gap-2.5 text-sm text-paper"
                      >
                        <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-gold" />
                        {f}
                      </li>
                    ))}
                  </ul>
                </Reveal>

                <Reveal delay={0.22}>
                  <Eyebrow tone="signal">Services Used</Eyebrow>
                  <div className="mt-5 flex flex-wrap gap-2">
                    {usedServices.map((s) =>
                      s!.hasDetailPage ? (
                        <Link
                          key={s!.slug}
                          href={`/services/${s!.slug}`}
                          className="rounded-full border border-line-strong px-3.5 py-1.5 text-sm text-paper-dim transition-colors hover:border-signal/50 hover:text-signal-bright"
                        >
                          {s!.name}
                        </Link>
                      ) : (
                        <span
                          key={s!.slug}
                          className="rounded-full border border-line-strong px-3.5 py-1.5 text-sm text-paper-dim"
                        >
                          {s!.name}
                        </span>
                      ),
                    )}
                  </div>
                  <Button
                    href="/contact"
                    variant="ghost"
                    className="mt-6 px-0"
                  >
                    Start a similar project
                  </Button>
                </Reveal>
              </div>
            </Container>
          </section>
        );
      })}

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone="gold">Don&apos;t See Your Industry?</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Every business is a digital business waiting to happen.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Tell Us About Your Business
            </Button>
            <Button href="/work" variant="secondary" size="lg">
              See Our Work
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}
