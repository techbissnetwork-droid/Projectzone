import { cn } from "@/lib/utils";

export function Table({ children }: { children: React.ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-(--radius-lg) border border-(--color-border)">
      <table className="w-full min-w-[560px] text-left text-sm">{children}</table>
    </div>
  );
}

export function Thead({ columns }: { columns: string[] }) {
  return (
    <thead>
      <tr className="border-b border-(--color-border) bg-(--color-surface-raised)">
        {columns.map((c) => (
          <th key={c} className="px-4 py-3 text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">
            {c}
          </th>
        ))}
      </tr>
    </thead>
  );
}

export function Tbody({ children }: { children: React.ReactNode }) {
  return <tbody className="divide-y divide-(--color-border) bg-(--color-surface)">{children}</tbody>;
}

export function StatusPill({ status }: { status: string }) {
  const positive = ["Live", "Paid", "Completed", "Active", "Excellent", "Resolved", "Good"];
  const warning = ["Processing", "In Progress", "Normal", "Open", "High"];
  const negative = ["Refunded", "At risk", "On leave"];

  const tone = positive.includes(status) ? "success" : negative.includes(status) ? "warning" : warning.includes(status) ? "accent" : "default";

  const classes = {
    success: "bg-emerald-500/10 text-emerald-400 border-emerald-500/25",
    warning: "bg-red-500/10 text-red-400 border-red-500/25",
    accent: "bg-amber-500/10 text-amber-400 border-amber-500/25",
    default: "bg-(--color-surface-raised) text-(--color-ink-muted) border-(--color-border-strong)",
  } as const;

  return (
    <span className={cn("inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium", classes[tone])}>
      {status}
    </span>
  );
}
