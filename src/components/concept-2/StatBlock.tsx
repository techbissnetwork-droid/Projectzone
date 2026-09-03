import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";

export function StatBlock({
  value,
  label,
  className,
}: {
  value: string;
  label: string;
  className?: string;
}) {
  return (
    <div className={cn("border-t border-neutral-300 pt-6", className)}>
      <p className={cn(fontSerif, "text-5xl leading-none text-neutral-900 sm:text-6xl")}>{value}</p>
      <p className="mt-4 text-xs uppercase tracking-[0.2em] text-neutral-500">{label}</p>
    </div>
  );
}
