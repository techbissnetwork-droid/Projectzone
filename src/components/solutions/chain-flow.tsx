import { ArrowRight } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Solution } from "@/lib/data/solutions";

export function ChainFlow({
  solution,
  size = "md",
}: {
  solution: Solution;
  size?: "sm" | "md";
}) {
  const isSm = size === "sm";
  return (
    <div className={cn("flex flex-wrap items-center", isSm ? "gap-1.5" : "gap-2.5")}>
      {solution.chain.map((step, i) => (
        <div key={step} className="flex items-center gap-2.5">
          <span
            className={cn(
              "rounded-full border font-medium",
              isSm ? "px-2.5 py-1 text-[11px]" : "px-3.5 py-2 text-[12.5px]",
              i === 0
                ? "border-[var(--color-border-strong)] text-[var(--color-ink-faint)]"
                : "border-transparent text-[var(--color-ink)]",
            )}
            style={i !== 0 ? { backgroundColor: `${solution.accent}1c` } : undefined}
          >
            {step}
          </span>
          {i < solution.chain.length - 1 && (
            <ArrowRight className={cn("shrink-0 text-[var(--color-ink-faint)]", isSm ? "size-3" : "size-3.5")} />
          )}
        </div>
      ))}
    </div>
  );
}
