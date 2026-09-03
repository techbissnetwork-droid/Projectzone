import { fontSerif } from "@/components/concept-2/fonts";
import { LinkButton } from "@/components/concept-2/Button";
import { Section } from "@/components/concept-2/Section";
import { cn } from "@/lib/cn";

export function CtaSection({
  title,
  description,
  primaryHref = "/concept-2/get-started",
  primaryLabel = "Start Your Project",
  secondaryHref,
  secondaryLabel,
  className,
}: {
  title: string;
  description?: string;
  primaryHref?: string;
  primaryLabel?: string;
  secondaryHref?: string;
  secondaryLabel?: string;
  className?: string;
}) {
  return (
    <Section tone="off" border="top" className={className}>
      <div className="flex flex-col items-start justify-between gap-10 lg:flex-row lg:items-end">
        <h2 className={cn(fontSerif, "max-w-2xl text-4xl leading-[1.05] text-neutral-900 sm:text-5xl lg:text-6xl")}>
          {title}
        </h2>
        <div className="flex w-full flex-col gap-6 lg:w-auto lg:items-end">
          {description ? <p className="max-w-xs text-sm text-neutral-600">{description}</p> : null}
          <div className="flex flex-wrap gap-4">
            <LinkButton href={primaryHref}>{primaryLabel}</LinkButton>
            {secondaryHref && secondaryLabel ? (
              <LinkButton href={secondaryHref} variant="secondary">
                {secondaryLabel}
              </LinkButton>
            ) : null}
          </div>
        </div>
      </div>
    </Section>
  );
}
