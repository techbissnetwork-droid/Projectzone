"use client";

import * as React from "react";
import { motion } from "framer-motion";
import { Check, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

export type ScanItem = { label: string; detail: string };

export function ScanList({
  items,
  onComplete,
  stepMs = 550,
}: {
  items: ScanItem[];
  onComplete?: () => void;
  stepMs?: number;
}) {
  const [doneCount, setDoneCount] = React.useState(0);
  const onCompleteRef = React.useRef(onComplete);
  onCompleteRef.current = onComplete;

  React.useEffect(() => {
    setDoneCount(0);
    let cancelled = false;
    let i = 0;
    function tick() {
      if (cancelled) return;
      i += 1;
      setDoneCount(i);
      if (i < items.length) {
        setTimeout(tick, stepMs);
      } else {
        onCompleteRef.current?.();
      }
    }
    const t = setTimeout(tick, stepMs);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [items, stepMs]);

  return (
    <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-md) border border-(--color-border) bg-(--color-canvas)">
      {items.map((item, i) => {
        const done = i < doneCount;
        const active = i === doneCount;
        return (
          <div key={item.label} className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="flex items-center gap-3">
              <span
                className={cn(
                  "flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition-colors duration-300",
                  done && "border-emerald-500/40 bg-emerald-500/12 text-emerald-400",
                  active && !done && "border-(--color-accent) text-(--color-accent-2)",
                  !active && !done && "border-(--color-border-strong) text-(--color-ink-faint)",
                )}
              >
                {done ? (
                  <Check className="h-3.5 w-3.5" />
                ) : active ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <span className="h-1.5 w-1.5 rounded-full bg-current" />
                )}
              </span>
              <span className={cn("text-sm", done || active ? "text-(--color-ink)" : "text-(--color-ink-faint)")}>
                {item.label}
              </span>
            </div>
            <motion.span
              initial={{ opacity: 0 }}
              animate={{ opacity: done ? 1 : 0 }}
              className="text-xs text-(--color-ink-faint)"
            >
              {done ? item.detail : ""}
            </motion.span>
          </div>
        );
      })}
    </div>
  );
}
