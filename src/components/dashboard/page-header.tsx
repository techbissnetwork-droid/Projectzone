import { cn } from "@/lib/utils";

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
  className,
}: {
  eyebrow?: string;
  title: string;
  description?: string;
  actions?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between", className)}>
      <div>
        {eyebrow && (
          <p className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">{eyebrow}</p>
        )}
        <h1 className="mt-2 text-[26px] font-medium tracking-tight text-[var(--color-ink)] sm:text-[30px]">
          {title}
        </h1>
        {description && (
          <p className="mt-2 max-w-[560px] text-[14px] leading-relaxed text-[var(--color-ink-muted)]">
            {description}
          </p>
        )}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2.5">{actions}</div>}
    </div>
  );
}

export function Card({
  className,
  children,
}: {
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 sm:p-6",
        className,
      )}
    >
      {children}
    </div>
  );
}
