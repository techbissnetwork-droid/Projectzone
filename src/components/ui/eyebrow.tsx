import { cn } from "@/lib/utils";

export function Eyebrow({
  children,
  className,
  index,
}: {
  children: React.ReactNode;
  className?: string;
  index?: string;
}) {
  return (
    <div
      className={cn(
        "inline-flex items-center gap-2.5 font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]",
        className,
      )}
    >
      {index && <span className="text-[var(--color-accent)]">{index}</span>}
      <span className="h-px w-6 bg-[var(--color-border-strong)]" />
      {children}
    </div>
  );
}

export function Badge({
  children,
  className,
  tone = "neutral",
}: {
  children: React.ReactNode;
  className?: string;
  tone?: "neutral" | "accent" | "gold" | "live" | "build";
}) {
  const tones: Record<string, string> = {
    neutral:
      "bg-white/[0.06] text-[var(--color-ink-muted)] border-[var(--color-border-strong)]",
    accent:
      "bg-[var(--color-accent-soft)] text-[#b7c3ff] border-transparent",
    gold: "bg-[var(--color-gold-soft)] text-[var(--color-gold)] border-transparent",
    live: "bg-[var(--color-live-soft)] text-[var(--color-live)] border-transparent",
    build: "bg-[var(--color-build-soft)] text-[var(--color-build)] border-transparent",
  };
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium",
        tones[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
