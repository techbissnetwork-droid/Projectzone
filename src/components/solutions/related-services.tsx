import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Reveal } from "@/components/ui/reveal";
import { services } from "@/lib/data/services";
import type { Solution } from "@/lib/data/solutions";

// Curated by relevance — which services most directly power each industry's
// transformation chain. Falls back to the first three services if a slug
// is ever added to solutions.ts without an entry here.
const RELATED_SERVICE_SLUGS: Record<string, string[]> = {
  restaurant: ["ecommerce", "payment-integration", "automation"],
  retail: ["ecommerce", "payment-integration", "business-digitization"],
  school: ["business-digitization", "automation", "payment-integration"],
  hospital: ["business-digitization", "payment-integration", "automation"],
  hotel: ["website-development", "payment-integration", "automation"],
  "real-estate": ["website-development", "business-digitization", "automation"],
  construction: ["website-development", "business-digitization", "payment-integration"],
  agency: ["website-development", "business-digitization", "payment-integration"],
  startup: ["mobile-app-development", "hosting-infrastructure", "payment-integration"],
  "service-business": ["automation", "payment-integration", "business-digitization"],
};

export function RelatedServices({ solution }: { solution: Solution }) {
  const slugs = RELATED_SERVICE_SLUGS[solution.slug] ?? services.slice(0, 3).map((s) => s.slug);
  const related = slugs
    .map((slug) => services.find((s) => s.slug === slug))
    .filter((s): s is (typeof services)[number] => Boolean(s));

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      {related.map((service, i) => (
        <Reveal key={service.slug} delay={i * 0.06}>
          <Link
            href={`/services/${service.slug}`}
            className="group relative flex h-full flex-col justify-between overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 transition-colors duration-300 hover:border-[var(--color-border-strong)]"
          >
            <div
              aria-hidden
              className="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"
              style={{ background: service.accent }}
            />
            <div className="relative flex items-start justify-between">
              <span className="font-mono-label text-[11px] text-[var(--color-ink-faint)]">
                {service.index}
              </span>
              <ArrowUpRight className="size-4 text-[var(--color-ink-faint)] opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
            </div>
            <div className="relative mt-6">
              <h4 className="text-[15.5px] font-medium text-[var(--color-ink)]">{service.name}</h4>
              <p className="mt-1.5 line-clamp-2 text-[13px] leading-relaxed text-[var(--color-ink-faint)]">
                {service.tagline}
              </p>
            </div>
          </Link>
        </Reveal>
      ))}
    </div>
  );
}
