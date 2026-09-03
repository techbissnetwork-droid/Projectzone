import { cn } from "@/lib/utils";

export function Badge({
  children,
  className,
  variant = "default",
}: {
  children: React.ReactNode;
  className?: string;
  variant?: "default" | "accent" | "outline" | "success" | "warning";
}) {
  const variants = {
    default: "bg-(--color-surface-raised) text-(--color-ink-muted) border-(--color-border-strong)",
    accent: "bg-(--color-accent)/12 text-(--color-accent-2) border-(--color-accent)/25",
    outline: "bg-transparent text-(--color-ink-muted) border-(--color-border-strong)",
    success: "bg-emerald-500/10 text-emerald-400 border-emerald-500/25",
    warning: "bg-amber-500/10 text-amber-400 border-amber-500/25",
  } as const;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium",
        variants[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}
