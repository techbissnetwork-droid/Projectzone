import { cn } from "@/lib/utils";

export function StatCard({
  label,
  value,
  delta,
  positive = true,
}: {
  label: string;
  value: string;
  delta?: string;
  positive?: boolean;
}) {
  return (
    <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-5">
      <p className="text-xs text-(--color-ink-faint)">{label}</p>
      <div className="mt-2 flex items-end justify-between">
        <p className="text-2xl font-medium text-(--color-ink)">{value}</p>
        {delta && (
          <span className={cn("text-xs font-medium", positive ? "text-emerald-400" : "text-red-400")}>{delta}</span>
        )}
      </div>
    </div>
  );
}
