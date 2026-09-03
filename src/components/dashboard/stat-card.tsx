import { LucideIcon } from "lucide-react";
import { cn } from "@/lib/utils";

export function StatCard({
  icon: Icon,
  label,
  value,
  hint,
  className,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  hint?: string;
  className?: string;
}) {
  return (
    <div className={cn("rounded-xl border border-line-dark bg-ink-900/40 p-5", className)}>
      <div className="flex items-center justify-between">
        <span className="text-[12px] uppercase tracking-wide text-paper-50/40">{label}</span>
        <Icon className="size-4 text-gold-400" strokeWidth={1.75} />
      </div>
      <div className="mt-3 text-[26px] font-medium tracking-tight text-paper-50">{value}</div>
      {hint && <div className="mt-1 text-[12.5px] text-paper-50/40">{hint}</div>}
    </div>
  );
}
