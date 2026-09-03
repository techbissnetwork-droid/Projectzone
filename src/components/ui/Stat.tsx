import { Reveal } from "./Reveal";
import { cn } from "@/lib/utils";

export function Stat({
  value,
  label,
  delay = 0,
  className,
}: {
  value: string;
  label: string;
  delay?: number;
  className?: string;
}) {
  return (
    <Reveal delay={delay} className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-3xl font-medium tracking-tight text-(--color-ink) sm:text-4xl">{value}</span>
      <span className="text-sm text-(--color-ink-muted)">{label}</span>
    </Reveal>
  );
}
