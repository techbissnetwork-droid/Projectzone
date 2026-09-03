import Link from "next/link";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { AnimatedHeadline } from "@/components/shared/AnimatedHeadline";
import { Reveal } from "@/components/shared/Reveal";
import { Accordion } from "@/components/shared/Accordion";
import { ServiceIcon } from "@/components/shared/ServiceIcon";
import { ServiceCard } from "@/components/services/ServiceCard";
import { ServiceHeroVisual } from "@/components/services/ServiceHeroVisual";
import { getServiceBySlug, type Service } from "@/lib/data/services";
import { cn } from "@/lib/utils/cn";

export function ServiceDetailTemplate({ service }: { service: Service }) {
  const isGold = service.color === "gold";
  const related = service.relatedServices
    .map((slug) => getServiceBySlug(slug))
    .filter((s): s is Service => Boolean(s));

  return (
    <>
      <section className="relative overflow-hidden pb-16 pt-32 sm:pt-36 md:pb-24 md:pt-44">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[640px]"
          style={{
            background: isGold
              ? "radial-gradient(46% 38% at 30% 0%, rgba(201,168,118,0.10), transparent)"
              : "radial-gradient(46% 38% at 30% 0%, rgba(127,166,217,0.10), transparent)",
          }}
        />
        <Container>
          <Reveal>
            <Link
              href="/services"
              className="inline-flex items-center gap-2 text-sm text-paper-dim transition-colors hover:text-paper"
            >
              <ArrowLeft className="size-4" aria-hidden />
              All Services
            </Link>
          </Reveal>

          <div className="mt-8 grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <div>
              <Eyebrow tone={service.color}>{service.category}</Eyebrow>
              <AnimatedHeadline
                text={service.fullName}
                as="h1"
                delay={0.08}
                className="text-h1 mt-5 max-w-xl text-balance font-medium text-paper"
              />
              <p className="text-lead mt-6 max-w-lg text-balance text-paper-dim">
                {service.heroDescription}
              </p>
              <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                <Button href="/contact" size="lg">
                  Start This Project
                </Button>
                <Button href="/services" variant="secondary" size="lg">
                  Compare Services
                </Button>
              </div>
              <div className="mt-12 grid grid-cols-3 gap-6 border-t border-line pt-8">
                {service.stats.map((stat) => (
                  <div key={stat.label}>
                    <p className="text-xl font-semibold text-paper sm:text-2xl">
                      {stat.value}
                    </p>
                    <p className="mt-1 text-xs text-paper-faint">{stat.label}</p>
                  </div>
                ))}
              </div>
            </div>

            <Reveal delay={0.15}>
              <ServiceHeroVisual service={service} />
            </Reveal>
          </div>
        </Container>
      </section>

      <section className="border-t border-line py-20 md:py-28">
        <Container>
          <SectionHeading
            eyebrow="What's Included"
            tone={service.color}
            title={`Everything built around ${service.name.toLowerCase()}.`}
          />
          <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {service.features.map((feature, i) => (
              <Reveal key={feature.title} delay={0.05 * i}>
                <div className="flex h-full flex-col gap-4 rounded-2xl border border-line bg-ink-raised-2 p-7">
                  <span
                    className={cn(
                      "flex size-10 items-center justify-center rounded-full border",
                      isGold
                        ? "border-gold/40 bg-gold-dim text-gold-bright"
                        : "border-signal/40 bg-signal-dim text-signal-bright",
                    )}
                  >
                    <ServiceIcon name={feature.icon} className="size-[1.1rem]" aria-hidden />
                  </span>
                  <div>
                    <h3 className="font-medium text-paper">{feature.title}</h3>
                    <p className="mt-2 text-sm leading-relaxed text-paper-dim">
                      {feature.description}
                    </p>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-line bg-ink-raised py-20 md:py-28">
        <Container>
          <SectionHeading
            eyebrow="How We Work"
            tone="signal"
            title="From first conversation to launch."
          />
          <div className="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
            {service.howWeWork.map((step, i) => (
              <Reveal key={step.step} delay={0.06 * i}>
                <div className="flex flex-col gap-3 border-t-2 border-line-strong pt-5">
                  <span className="text-eyebrow text-paper-faint">0{i + 1}</span>
                  <h3 className="text-lg font-medium text-paper">{step.step}</h3>
                  <p className="text-sm leading-relaxed text-paper-dim">
                    {step.description}
                  </p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="py-20 md:py-28">
        <Container className="grid gap-14 lg:grid-cols-2 lg:gap-20">
          <div>
            <SectionHeading
              align="left"
              size="h3"
              eyebrow="What You Get"
              title="Deliverables"
            />
            <ul className="mt-8 grid gap-4 sm:grid-cols-2">
              {service.deliverables.map((d) => (
                <li key={d} className="flex items-start gap-3 text-[0.95rem] text-paper-dim">
                  <CheckCircle2
                    className={cn(
                      "mt-0.5 size-4 shrink-0",
                      isGold ? "text-gold-bright" : "text-signal-bright",
                    )}
                    aria-hidden
                  />
                  {d}
                </li>
              ))}
            </ul>
          </div>
          <div>
            <SectionHeading
              align="left"
              size="h3"
              tone="signal"
              eyebrow="Built With"
              title="Technology"
            />
            <div className="mt-8 flex flex-wrap gap-2.5">
              {service.technologies.map((t) => (
                <span
                  key={t}
                  className="rounded-full border border-line-strong px-3.5 py-2 text-sm text-paper-dim"
                >
                  {t}
                </span>
              ))}
            </div>
          </div>
        </Container>
      </section>

      <section className="border-t border-line py-20 md:py-28">
        <Container className="mx-auto max-w-3xl">
          <SectionHeading
            align="center"
            eyebrow="Common Questions"
            title="Frequently asked."
          />
          <div className="mt-12">
            <Accordion items={service.faqs} />
          </div>
        </Container>
      </section>

      {related.length > 0 && (
        <section className="border-t border-line bg-ink-raised py-20 md:py-28">
          <Container>
            <SectionHeading eyebrow="Pairs Well With" title="Related services" />
            <div className="mt-12 grid gap-5 sm:grid-cols-3">
              {related.map((r, i) => (
                <Reveal key={r.slug} delay={0.06 * i}>
                  <ServiceCard service={r} compact />
                </Reveal>
              ))}
            </div>
          </Container>
        </section>
      )}

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone={service.color}>Ready When You Are</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Let&apos;s talk about {service.name.toLowerCase()} for your business.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Start Your Project
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
