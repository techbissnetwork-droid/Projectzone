import { cn } from "@/lib/utils";

export function ProgressBar({ value, className }: { value: number; className?: string }) {
  return (
    <div className={cn("h-1.5 w-full overflow-hidden rounded-full bg-(--color-surface-raised)", className)}>
      <div
        className="h-full rounded-full bg-[linear-gradient(90deg,#4b5bff,#7a5cff,#17c3ff)] transition-all duration-500 ease-out"
        style={{ width: `${Math.min(100, Math.max(0, value))}%` }}
      />
    </div>
  );
}
