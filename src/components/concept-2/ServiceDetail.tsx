import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight } from "lucide-react";
import { serviceBySlug, services, processSteps } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { CtaSection } from "@/components/concept-2/CtaSection";
import { cn } from "@/lib/cn";

/**
 * Shared template rendered by all seven service detail pages — identical
 * layout throughout, differentiated only by content and the numeral shown
 * in the hero, per this concept's "rigorously consistent" design system.
 */
export function ServiceDetail({ slug, index }: { slug: string; index: string }) {
  const service = serviceBySlug(slug);
  if (!service) notFound();

  const related = services.filter((s) => s.hasDetailPage && s.slug !== slug).slice(0, 3);

  return (
    <>
      <PageHero eyebrow="Service" index={index} title={service.title} description={service.shortDescription} />

      <Section>
        <Reveal>
          <p className="max-w-3xl text-base leading-relaxed text-neutral-600 sm:text-lg">
            {service.longDescription}
          </p>
        </Reveal>
      </Section>

      <Section tone="off" border="top">
        <div className="grid gap-12 lg:grid-cols-2">
          <Reveal>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">What&apos;s included</p>
            <ul className="mt-6 border-t border-neutral-200">
              {service.features.map((f) => (
                <li key={f} className="border-b border-neutral-200 py-4 text-sm leading-relaxed text-neutral-700 sm:text-base">
                  {f}
                </li>
              ))}
            </ul>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Deliverables</p>
            <ul className="mt-6 border-t border-neutral-200">
              {service.deliverables.map((d) => (
                <li key={d} className="border-b border-neutral-200 py-4 text-sm leading-relaxed text-neutral-700 sm:text-base">
                  {d}
                </li>
              ))}
            </ul>
          </Reveal>
        </div>
      </Section>

      <Section border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">How it happens</p>
          <h2 className={cn(fontSerif, "mt-4 max-w-2xl text-4xl text-neutral-900 sm:text-5xl")}>
            Discovery first. Then design, build, and launch.
          </h2>
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-neutral-600 sm:text-lg">
            {service.title} follows our standard six-stage process — starting with{" "}
            {processSteps[0].title.toLowerCase()} and ending with {processSteps[5].title.toLowerCase()} — so scope,
            timeline, and responsibility are clear from day one.
          </p>
          <div className="mt-6">
            <Link
              href="/concept-2/process"
              className="group inline-flex items-center gap-1.5 text-sm font-medium text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 rounded-sm"
            >
              <span className="relative">
                See the full process
                <span className="absolute left-0 -bottom-0.5 h-px w-full origin-left scale-x-0 bg-neutral-900 transition-transform duration-300 group-hover:scale-x-100" />
              </span>
              <ArrowUpRight className="h-4 w-4 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
            </Link>
          </div>
        </Reveal>
      </Section>

      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Related services</p>
        </Reveal>
        <div className="mt-8 grid gap-x-10 gap-y-6 sm:grid-cols-3">
          {related.map((r) => (
            <Reveal key={r.slug}>
              <Link
                href={`/concept-2/services/${r.slug}`}
                className="group inline-flex items-start gap-2 text-base text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 rounded-sm"
              >
                <span className="relative">
                  {r.title}
                  <span className="absolute left-0 -bottom-0.5 h-px w-full origin-left scale-x-0 bg-neutral-900 transition-transform duration-300 group-hover:scale-x-100" />
                </span>
                <ArrowUpRight className="mt-1 h-4 w-4 shrink-0 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
              </Link>
            </Reveal>
          ))}
        </div>
      </Section>

      <CtaSection
        title={`Ready to start your ${service.title.toLowerCase()} project?`}
        primaryLabel="Start Your Project"
        secondaryLabel="View all services"
        secondaryHref="/concept-2/services"
      />
    </>
  );
}
