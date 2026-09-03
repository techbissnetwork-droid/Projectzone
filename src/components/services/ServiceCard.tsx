import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { ServiceIcon } from "@/components/shared/ServiceIcon";
import type { Service } from "@/lib/data/services";
import { cn } from "@/lib/utils/cn";

export function ServiceCard({ service, compact = false }: { service: Service; compact?: boolean }) {
  const isGold = service.color === "gold";
  const content = (
    <>
      <div className="flex items-start justify-between gap-4">
        <span
          className={cn(
            "flex size-11 shrink-0 items-center justify-center rounded-full border transition-colors duration-300",
            isGold
              ? "border-gold/40 bg-gold-dim text-gold-bright"
              : "border-signal/40 bg-signal-dim text-signal-bright",
          )}
        >
          <ServiceIcon name={service.icon} className="size-[1.1rem]" aria-hidden />
        </span>
        {service.hasDetailPage ? (
          <ArrowUpRight className="size-5 shrink-0 text-paper-faint transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-paper" />
        ) : (
          <span className="text-eyebrow shrink-0 rounded-full border border-line-strong px-2.5 py-1 text-paper-faint">
            Included
          </span>
        )}
      </div>
      <div className="mt-6">
        <span className="text-eyebrow text-paper-faint">{service.category}</span>
        <h3 className="mt-2 text-xl font-medium tracking-tight text-paper">
          {service.fullName}
        </h3>
        {!compact && (
          <p className="mt-3 text-[0.95rem] leading-relaxed text-paper-dim">
            {service.shortDescription}
          </p>
        )}
      </div>
      {!compact && (
        <div className="mt-6 flex flex-wrap gap-2">
          {service.technologies.slice(0, 3).map((t) => (
            <span
              key={t}
              className="text-eyebrow rounded-full border border-line-strong px-2.5 py-1 text-[0.62rem] text-paper-faint"
            >
              {t}
            </span>
          ))}
        </div>
      )}
    </>
  );

  const className =
    "group flex h-full flex-col rounded-2xl border border-line bg-ink-raised-2 p-7 transition-colors duration-300 hover:border-line-strong sm:p-8";

  if (service.hasDetailPage) {
    return (
      <Link href={`/services/${service.slug}`} className={className}>
        {content}
      </Link>
    );
  }

  return <div className={className}>{content}</div>;
}
