import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Reveal } from "@/components/ui/reveal";
import { services, type Service } from "@/lib/data/services";

export function RelatedServices({ current }: { current: Service }) {
  const currentIdx = services.findIndex((s) => s.slug === current.slug);
  const related = Array.from({ length: 3 }, (_, i) => services[(currentIdx + i + 1) % services.length]);

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
