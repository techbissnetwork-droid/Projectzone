import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { ChevronRight, Quote } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Badge } from "@/components/ui/Badge";
import { Reveal } from "@/components/ui/Reveal";
import { CtaBanner } from "@/components/home/CtaBanner";
import { caseStudies } from "@/lib/data/caseStudies";

export function generateStaticParams() {
  return caseStudies.map((s) => ({ slug: s.slug }));
}

export async function generateMetadata(props: PageProps<"/work/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  const study = caseStudies.find((s) => s.slug === slug);
  if (!study) return {};
  return { title: study.title, description: study.summary };
}

export default async function CaseStudyPage(props: PageProps<"/work/[slug]">) {
  const { slug } = await props.params;
  const study = caseStudies.find((s) => s.slug === slug);
  if (!study) notFound();

  const more = caseStudies.filter((s) => s.slug !== study.slug).slice(0, 2);

  return (
    <>
      <section
        className="relative overflow-hidden pt-16 pb-20 sm:pt-20 sm:pb-24"
        style={{ background: `linear-gradient(160deg, ${study.gradient[0]}, ${study.gradient[1]})` }}
      >
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_0%,rgba(255,255,255,0.16),transparent_55%)]" />
        <Container className="relative">
          <nav className="mb-8 flex items-center gap-1.5 text-sm text-white/60">
            <Link href="/work" className="hover:text-white/90">
              Work
            </Link>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="text-white/90">{study.client}</span>
          </nav>
          <Reveal>
            <Badge className="border-white/25 bg-white/10 text-white backdrop-blur">
              {study.industry} · {study.year}
            </Badge>
            <h1 className="mt-5 max-w-3xl text-balance text-3xl font-medium leading-tight text-white sm:text-4xl lg:text-[2.75rem]">
              {study.title}
            </h1>
            <p className="mt-5 max-w-2xl text-balance text-base text-white/80 sm:text-lg">{study.summary}</p>
          </Reveal>
        </Container>
      </section>

      <Section size="tight">
        <Container size="wide">
          <RevealResultsGrid study={study} />
        </Container>
      </Section>

      <Section theme="light" size="tight">
        <Container size="narrow">
          <div className="flex flex-col gap-10">
            <Reveal>
              <h2 className="text-xl font-medium text-(--color-ink)">The challenge</h2>
              <p className="mt-3 text-base leading-relaxed text-(--color-ink-muted)">{study.challenge}</p>
            </Reveal>
            <Reveal delay={0.05}>
              <h2 className="text-xl font-medium text-(--color-ink)">Our approach</h2>
              <p className="mt-3 text-base leading-relaxed text-(--color-ink-muted)">{study.solution}</p>
            </Reveal>
            <Reveal delay={0.1}>
              <div className="flex flex-wrap gap-2">
                {study.services.map((s) => (
                  <Badge key={s} variant="outline">
                    {s}
                  </Badge>
                ))}
              </div>
            </Reveal>
          </div>
        </Container>
      </Section>

      <Section size="tight">
        <Container size="narrow">
          <Reveal className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-8 sm:p-10">
            <Quote className="h-6 w-6 text-(--color-accent-2)" />
            <p className="mt-4 text-lg leading-relaxed text-(--color-ink) sm:text-xl">&ldquo;{study.quote.text}&rdquo;</p>
            <p className="mt-5 text-sm text-(--color-ink-muted)">
              <span className="font-medium text-(--color-ink)">{study.quote.author}</span> — {study.quote.role}, {study.client}
            </p>
          </Reveal>
        </Container>
      </Section>

      {more.length > 0 && (
        <Section theme="light">
          <Container>
            <h2 className="mb-8 text-2xl font-medium tracking-tight text-(--color-ink)">More case studies</h2>
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              {more.map((s) => (
                <Link
                  key={s.slug}
                  href={`/work/${s.slug}`}
                  className="focus-ring group rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                >
                  <Badge variant="outline">{s.industry}</Badge>
                  <h3 className="mt-3 text-base font-medium text-(--color-ink)">{s.title}</h3>
                  <p className="mt-2 text-sm text-(--color-ink-muted) line-clamp-2">{s.summary}</p>
                </Link>
              ))}
            </div>
          </Container>
        </Section>
      )}

      <CtaBanner />
    </>
  );
}

function RevealResultsGrid({ study }: { study: (typeof caseStudies)[number] }) {
  return (
    <Reveal className="grid grid-cols-2 gap-6 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-8 sm:grid-cols-4 sm:p-10">
      {study.results.map((r) => (
        <div key={r.label} className="text-center">
          <p className="text-2xl font-medium text-(--color-ink) sm:text-3xl">{r.value}</p>
          <p className="mt-1 text-xs text-(--color-ink-faint) sm:text-sm">{r.label}</p>
        </div>
      ))}
    </Reveal>
  );
}
