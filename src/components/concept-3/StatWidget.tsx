"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { motion, useInView, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/cn";

/**
 * Animated dashboard-style stat tile. Numeric-looking values count up on
 * scroll into view; the accent bar underneath is a purely decorative
 * "activity" flourish, not a real metric.
 */
export function StatWidget({
  label,
  value,
  icon,
  index = 0,
  className,
}: {
  label: string;
  value: string;
  icon?: ReactNode;
  index?: number;
  className?: string;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-60px" });
  const reduceMotion = useReducedMotion();
  const numericMatch = value.match(/^(\d+)(\D*)$/);
  const [display, setDisplay] = useState(numericMatch ? "0" + numericMatch[2] : value);

  useEffect(() => {
    if (!inView || !numericMatch || reduceMotion) return;
    const target = parseInt(numericMatch[1], 10);
    const suffix = numericMatch[2];
    const duration = 900;
    const start = performance.now();
    let raf = 0;
    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      setDisplay(Math.round(eased * target) + suffix);
      if (progress < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inView]);

  return (
    <motion.div
      ref={ref}
      className={cn(
        "relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-lg shadow-black/20",
        className
      )}
      initial={{ opacity: 0, y: reduceMotion ? 0 : 20 }}
      animate={inView ? { opacity: 1, y: 0 } : {}}
      transition={{ duration: 0.5, delay: reduceMotion ? 0 : index * 0.08, ease: [0.16, 1, 0.3, 1] }}
    >
      <div className="flex items-center justify-between gap-3">
        {icon ? (
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
            {icon}
          </span>
        ) : null}
        <span className="h-1.5 w-16 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
          <motion.span
            className="block h-full rounded-full bg-gradient-to-r from-violet-400 to-emerald-400"
            initial={{ width: "0%" }}
            animate={inView ? { width: "100%" } : {}}
            transition={{ duration: 1, delay: reduceMotion ? 0 : index * 0.08 + 0.1, ease: "easeOut" }}
          />
        </span>
      </div>
      <p className="font-display mt-4 text-3xl font-bold text-white">{reduceMotion ? value : display}</p>
      <p className="mt-1 text-sm text-slate-400">{label}</p>
    </motion.div>
  );
}
