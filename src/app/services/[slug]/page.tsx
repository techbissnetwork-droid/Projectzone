import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Check } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow, Badge } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ProcessVisual } from "@/components/services/process-visual";
import { RelatedServices } from "@/components/services/related-services";
import { services, getService } from "@/lib/data/services";

export function generateStaticParams() {
  return services.map((service) => ({ slug: service.slug }));
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
    description: service.tagline,
  };
}

export default async function ServiceDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const service = getService(slug);
  if (!service) notFound();

  return (
    <>
      {/* Hero */}
      <section className="relative overflow-hidden pt-36 sm:pt-40 md:pt-44">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-20 h-[380px] w-[720px] -translate-x-1/2 rounded-full opacity-[0.16] blur-[100px]"
          style={{ background: service.accent }}
        />
        <Container className="relative">
          <Reveal className="flex items-center gap-3">
            <Badge tone="accent">Service {service.index} / {services.length.toString().padStart(2, "0")}</Badge>
            <Eyebrow>Services</Eyebrow>
          </Reveal>
          <Reveal delay={0.06}>
            <h1 className="mt-6 max-w-[18ch] text-balance text-[38px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[56px] md:text-[64px]">
              {service.name}
            </h1>
          </Reveal>
          <Reveal delay={0.12}>
            <p className="mt-6 max-w-[56ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[19px]">
              {service.tagline}
            </p>
          </Reveal>
          <Reveal delay={0.18} className="mt-9 flex flex-col gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start This Project
            </Button>
            <Button href="/services" variant="secondary" size="lg" icon={false}>
              All Services
            </Button>
          </Reveal>
        </Container>
      </section>

      {/* Description + Benefits */}
      <section className="mt-24 sm:mt-32">
        <Container>
          <div className="grid gap-12 lg:grid-cols-2 lg:gap-16">
            <Reveal>
              <Eyebrow>Overview</Eyebrow>
              <p className="mt-6 max-w-[52ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink)] sm:text-[18px]">
                {service.description}
              </p>
            </Reveal>
            <Reveal delay={0.08}>
              <Eyebrow>What You Get</Eyebrow>
              <RevealGroup className="mt-6 flex flex-col gap-3.5" stagger={0.05}>
                {service.benefits.map((benefit) => (
                  <RevealItem key={benefit}>
                    <div className="flex items-start gap-3">
                      <span
                        className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full"
                        style={{ backgroundColor: `${service.accent}22`, color: service.accent }}
                      >
                        <Check className="size-3" strokeWidth={2.5} />
                      </span>
                      <span className="text-[14.5px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[15px]">
                        {benefit}
                      </span>
                    </div>
                  </RevealItem>
                ))}
              </RevealGroup>
            </Reveal>
          </div>
        </Container>
      </section>

      {/* Process visual */}
      <section className="mt-24 border-y border-[var(--color-border)] bg-[var(--color-bg-soft)] py-24 sm:mt-32 sm:py-32">
        <Container>
          <Reveal className="mx-auto max-w-[640px] text-center">
            <Eyebrow className="justify-center">How We Build It</Eyebrow>
            <h2 className="mt-6 text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[38px]">
              A clear process, from first call to launch.
            </h2>
          </Reveal>
          <div className="mt-14">
            <ProcessVisual service={service} />
          </div>
        </Container>
      </section>

      {/* Technology */}
      <section className="py-24 sm:py-32">
        <Container>
          <Reveal>
            <Eyebrow>Technology &amp; Tools</Eyebrow>
          </Reveal>
          <Reveal delay={0.06} className="mt-6 flex flex-wrap gap-2.5">
            {service.tech.map((t) => (
              <span
                key={t}
                className="rounded-full border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-4 py-2 text-[13px] font-medium text-[var(--color-ink)]"
              >
                {t}
              </span>
            ))}
          </Reveal>
        </Container>
      </section>

      {/* Related services */}
      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal>
            <Eyebrow>Related Services</Eyebrow>
            <h2 className="mt-6 max-w-[24ch] text-balance text-[26px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[34px]">
              Often paired with {service.shortName.toLowerCase()}.
            </h2>
          </Reveal>
          <div className="mt-10">
            <RelatedServices current={service} />
          </div>
        </Container>
      </section>

      {/* CTA */}
      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[420px] w-[820px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[22ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Ready to start your {service.shortName.toLowerCase()} project?
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="mx-auto mt-5 max-w-[54ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
              Tell us about your business and we&apos;ll scope exactly what {service.shortName.toLowerCase()}{" "}
              looks like for you.
            </p>
          </Reveal>
          <Reveal delay={0.2} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start a Project
            </Button>
            <Button href="/marketplace" variant="secondary" size="lg">
              Browse Marketplace
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
