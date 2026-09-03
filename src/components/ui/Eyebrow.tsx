import { cn } from "@/lib/utils/cn";

export function Eyebrow({
  children,
  className,
  tone = "gold",
}: {
  children: React.ReactNode;
  className?: string;
  tone?: "gold" | "signal" | "neutral";
}) {
  const dot =
    tone === "gold"
      ? "bg-gold"
      : tone === "signal"
        ? "bg-signal"
        : "bg-paper-dim";

  return (
    <span
      className={cn(
        "text-eyebrow inline-flex items-center gap-2.5 text-paper-dim",
        className,
      )}
    >
      <span className={cn("h-1.5 w-1.5 rounded-full", dot)} aria-hidden />
      {children}
    </span>
  );
}
