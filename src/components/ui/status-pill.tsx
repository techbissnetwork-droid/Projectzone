import { cn } from "@/lib/utils";

const map: Record<string, { dot: string; text: string }> = {
  Live: { dot: "bg-success-500", text: "text-success-500" },
  Active: { dot: "bg-success-500", text: "text-success-500" },
  Connected: { dot: "bg-success-500", text: "text-success-500" },
  Paid: { dot: "bg-success-500", text: "text-success-500" },
  Resolved: { dot: "bg-success-500", text: "text-success-500" },
  Ready: { dot: "bg-signal-400", text: "text-signal-400" },
  Building: { dot: "bg-signal-400", text: "text-signal-400" },
  "In Progress": { dot: "bg-signal-400", text: "text-signal-400" },
  Pending: { dot: "bg-warning-500", text: "text-warning-500" },
  Draft: { dot: "bg-ink-400", text: "text-ink-400" },
  "Not connected": { dot: "bg-ink-400", text: "text-ink-400" },
  Maintenance: { dot: "bg-warning-500", text: "text-warning-500" },
  Expiring: { dot: "bg-warning-500", text: "text-warning-500" },
  Due: { dot: "bg-warning-500", text: "text-warning-500" },
  Open: { dot: "bg-warning-500", text: "text-warning-500" },
  "Renewal required": { dot: "bg-warning-500", text: "text-warning-500" },
  Expired: { dot: "bg-danger-500", text: "text-danger-500" },
  Suspended: { dot: "bg-danger-500", text: "text-danger-500" },
  Overdue: { dot: "bg-danger-500", text: "text-danger-500" },
  Available: { dot: "bg-ink-400", text: "text-ink-400" },
};

export function StatusPill({ status, className }: { status: string; className?: string }) {
  const style = map[status] ?? { dot: "bg-ink-400", text: "text-ink-400" };
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border border-current/20 px-2.5 py-1 text-[11px] font-medium",
        style.text,
        className
      )}
    >
      <span className={cn("size-1.5 rounded-full", style.dot)} />
      {status}
    </span>
  );
}
