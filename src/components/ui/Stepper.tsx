import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

export function Stepper({ steps, current }: { steps: string[]; current: number }) {
  return (
    <div className="flex items-center justify-between gap-2">
      {steps.map((label, i) => {
        const done = i < current;
        const active = i === current;
        return (
          <div key={label} className="flex flex-1 items-center gap-2">
            <div className="flex flex-col items-center gap-2">
              <div
                className={cn(
                  "flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-xs font-medium transition-colors duration-300",
                  done && "border-(--color-accent) bg-(--color-accent) text-white",
                  active && !done && "border-(--color-accent) text-(--color-accent-2)",
                  !active && !done && "border-(--color-border-strong) text-(--color-ink-faint)",
                )}
              >
                {done ? <Check className="h-4 w-4" /> : i + 1}
              </div>
              <span
                className={cn(
                  "hidden text-center text-xs sm:block",
                  active || done ? "text-(--color-ink)" : "text-(--color-ink-faint)",
                )}
              >
                {label}
              </span>
            </div>
            {i < steps.length - 1 && (
              <div className={cn("h-px flex-1 transition-colors duration-300", done ? "bg-(--color-accent)" : "bg-(--color-border-strong)")} />
            )}
          </div>
        );
      })}
    </div>
  );
}
