import { Check, ArrowRight } from "lucide-react";
import { serviceBySlug, processSteps, services } from "@/lib/site-data";
import { Container } from "@/components/concept-1/Container";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { Button } from "@/components/concept-1/Button";
import { ServiceCard } from "@/components/concept-1/ServiceCard";
import { CtaBanner } from "@/components/concept-1/CtaBanner";
import { HeroBackground } from "@/components/concept-1/HeroBackground";
import { ServiceVisual, type ServiceVisualVariant } from "@/components/concept-1/ServiceVisual";

export function ServiceDetailLayout({
  slug,
  visual,
  relatedSlugs,
  processStepIndexes,
  processIntro,
}: {
  slug: string;
  visual: ServiceVisualVariant;
  relatedSlugs: string[];
  processStepIndexes: number[];
  processIntro: string;
}) {
  const service = serviceBySlug(slug);
  if (!service) return null;

  const relatedServices = relatedSlugs
    .map((s) => services.find((item) => item.slug === s))
    .filter((item): item is (typeof services)[number] => Boolean(item));

  const highlightedSteps = processStepIndexes
    .map((index) => processSteps[index])
    .filter(Boolean);

  return (
    <>
      <section className="relative overflow-hidden pb-16 pt-40 sm:pb-24 sm:pt-48">
        <HeroBackground />
        <Container>
          <div className="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
            <Reveal>
              <Eyebrow className="mb-6">Service</Eyebrow>
              <h1 className="text-4xl font-semibold leading-[1.05] tracking-tight text-neutral-50 sm:text-5xl lg:text-6xl">
                {service.title}
              </h1>
              <p className="mt-6 max-w-xl text-lg leading-relaxed text-neutral-400">
                {service.shortDescription}
              </p>
              <p className="mt-4 max-w-xl text-sm leading-relaxed text-neutral-500">
                {service.longDescription}
              </p>
              <div className="mt-9 flex flex-wrap gap-4">
                <Button href="/concept-1/get-started" variant="primary">
                  Start Your Project
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </Button>
                <Button href="/concept-1/pricing" variant="secondary">
                  View Pricing
                </Button>
              </div>
            </Reveal>
            <Reveal delay={0.1} className="flex justify-center lg:justify-end">
              <ServiceVisual variant={visual} />
            </Reveal>
          </div>
        </Container>
      </section>

      <Section className="pt-0">
        <div className="grid gap-6 lg:grid-cols-2 lg:gap-10">
          <Reveal>
            <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-xl">
              <h2 className="text-xl font-semibold tracking-tight text-neutral-50">
                What&rsquo;s included
              </h2>
              <ul className="mt-6 space-y-4">
                {service.features.map((feature) => (
                  <li key={feature} className="flex items-start gap-3 text-sm text-neutral-300">
                    <span
                      aria-hidden="true"
                      className="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-gradient-to-r from-cyan-400/20 via-indigo-400/20 to-fuchsia-500/20"
                    >
                      <Check className="h-3.5 w-3.5 text-neutral-100" />
                    </span>
                    {feature}
                  </li>
                ))}
              </ul>
            </div>
          </Reveal>
          <Reveal delay={0.08}>
            <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-xl">
              <h2 className="text-xl font-semibold tracking-tight text-neutral-50">
                Deliverables
              </h2>
              <ul className="mt-6 space-y-4">
                {service.deliverables.map((deliverable) => (
                  <li key={deliverable} className="flex items-start gap-3 text-sm text-neutral-300">
                    <span
                      aria-hidden="true"
                      className="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-gradient-to-r from-cyan-400/20 via-indigo-400/20 to-fuchsia-500/20"
                    >
                      <Check className="h-3.5 w-3.5 text-neutral-100" />
                    </span>
                    {deliverable}
                  </li>
                ))}
              </ul>
            </div>
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading eyebrow="How It Comes Together" title="A focused path to delivery." description={processIntro} />
        <div className="mt-12 grid gap-6 sm:grid-cols-3">
          {highlightedSteps.map((step, index) => (
            <Reveal key={step.step} delay={index * 0.06}>
              <div className="h-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <span className="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-2xl font-semibold text-transparent">
                  {step.step}
                </span>
                <h3 className="mt-3 text-base font-semibold tracking-tight text-neutral-50">
                  {step.title}
                </h3>
                <p className="mt-2 text-sm leading-relaxed text-neutral-400">{step.description}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      {relatedServices.length > 0 ? (
        <Section className="border-t border-white/5">
          <SectionHeading eyebrow="Related Services" title="Often paired together." />
          <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {relatedServices.map((related, index) => (
              <Reveal key={related.slug} delay={index * 0.06}>
                <ServiceCard service={related} className="h-full" />
              </Reveal>
            ))}
          </div>
        </Section>
      ) : null}

      <CtaBanner
        title={`Ready to start your ${service.title.toLowerCase()} project?`}
        description="Tell us about your goals and we'll map out the right scope, timeline, and team to get you there."
      />
    </>
  );
}
