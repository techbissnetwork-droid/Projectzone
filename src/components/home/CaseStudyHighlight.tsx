import { ArrowRight } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/ui/Reveal";
import { caseStudies } from "@/lib/data/caseStudies";

export function CaseStudyHighlight() {
  const study = caseStudies[0];

  return (
    <Section theme="light">
      <Container>
        <Reveal>
          <div className="overflow-hidden rounded-(--radius-xl) border border-(--color-border)">
            <div
              className="relative flex flex-col justify-between gap-8 p-8 sm:p-12 lg:flex-row lg:items-end lg:p-16"
              style={{ background: `linear-gradient(135deg, ${study.gradient[0]}, ${study.gradient[1]})` }}
            >
              <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.14),transparent_55%)]" />
              <div className="relative max-w-xl">
                <Badge className="mb-5 border-white/25 bg-white/10 text-white backdrop-blur">
                  {study.industry} · {study.year}
                </Badge>
                <h3 className="text-2xl font-medium leading-tight text-white sm:text-3xl">{study.title}</h3>
                <p className="mt-4 text-sm leading-relaxed text-white/75 sm:text-base">{study.summary}</p>
                <Button href={`/work/${study.slug}`} variant="light" className="mt-7" icon={<ArrowRight className="h-4 w-4" />}>
                  Read the full story
                </Button>
              </div>
              <div className="relative grid grid-cols-2 gap-4 sm:gap-6 lg:min-w-[19rem]">
                {study.results.slice(0, 4).map((r) => (
                  <div key={r.label}>
                    <p className="text-2xl font-medium text-white sm:text-3xl">{r.value}</p>
                    <p className="mt-1 text-xs text-white/70">{r.label}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </Reveal>
      </Container>
    </Section>
  );
}
