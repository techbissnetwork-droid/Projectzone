"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Check } from "lucide-react";
import type { ProcessStep } from "@/lib/site-data";
import { cn } from "@/lib/cn";

/**
 * Interactive stepper: click any step to focus it. Horizontal with a
 * progress line on desktop, vertical with inline reveal on mobile.
 */
export function ProcessStepper({ steps }: { steps: ProcessStep[] }) {
  const [active, setActive] = useState(0);
  const progressPct = steps.length > 1 ? (active / (steps.length - 1)) * 100 : 0;

  return (
    <div>
      {/* Desktop: horizontal */}
      <div className="hidden lg:block">
        <div role="tablist" aria-label="Our process steps" className="relative flex items-start justify-between">
          <div className="absolute left-0 right-0 top-6 h-0.5 bg-white/10" aria-hidden="true" />
          <motion.div
            className="absolute left-0 top-6 h-0.5 bg-gradient-to-r from-violet-400 via-fuchsia-400 to-blue-400"
            initial={false}
            animate={{ width: `${progressPct}%` }}
            transition={{ duration: 0.4, ease: "easeOut" }}
            aria-hidden="true"
          />
          {steps.map((step, i) => {
            const isActive = i === active;
            const isDone = i < active;
            return (
              <button
                key={step.step}
                type="button"
                role="tab"
                aria-selected={isActive}
                onClick={() => setActive(i)}
                className="relative z-10 flex w-full flex-col items-center gap-3 rounded-lg px-2 py-1 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
              >
                <span
                  className={cn(
                    "flex h-12 w-12 items-center justify-center rounded-full border text-sm font-bold transition-colors",
                    isActive
                      ? "border-transparent bg-gradient-to-br from-violet-500 to-blue-500 text-white shadow-lg shadow-violet-500/30"
                      : isDone
                        ? "border-emerald-400/40 bg-emerald-400/10 text-emerald-300"
                        : "border-white/15 bg-[#0b0c14] text-slate-400"
                  )}
                >
                  {isDone ? <Check className="h-5 w-5" aria-hidden="true" /> : step.step}
                </span>
                <span className={cn("text-sm font-semibold", isActive ? "text-white" : "text-slate-400")}>
                  {step.title}
                </span>
              </button>
            );
          })}
        </div>

        <AnimatePresence mode="wait">
          <motion.div
            key={active}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
            className="mt-8 rounded-2xl border border-white/10 bg-white/[0.03] p-7"
          >
            <p className="text-xs font-semibold uppercase tracking-widest text-violet-300">
              Step {steps[active].step}
            </p>
            <h3 className="font-display mt-2 text-xl font-semibold text-white">{steps[active].title}</h3>
            <p className="mt-3 max-w-2xl text-slate-400">{steps[active].description}</p>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Mobile / tablet: vertical accordion-style */}
      <ol className="flex flex-col gap-3 lg:hidden">
        {steps.map((step, i) => {
          const isActive = i === active;
          return (
            <li key={step.step} className="rounded-2xl border border-white/10 bg-white/[0.03]">
              <button
                type="button"
                aria-expanded={isActive}
                onClick={() => setActive(isActive ? -1 : i)}
                className="flex min-h-[44px] w-full items-center gap-4 rounded-2xl px-5 py-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
              >
                <span
                  className={cn(
                    "flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold",
                    isActive
                      ? "bg-gradient-to-br from-violet-500 to-blue-500 text-white"
                      : "border border-white/15 text-slate-400"
                  )}
                >
                  {step.step}
                </span>
                <span className={cn("text-sm font-semibold", isActive ? "text-white" : "text-slate-300")}>
                  {step.title}
                </span>
              </button>
              <AnimatePresence initial={false}>
                {isActive ? (
                  <motion.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: "auto", opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    transition={{ duration: 0.25 }}
                    className="overflow-hidden"
                  >
                    <p className="px-5 pb-5 pl-[3.75rem] text-sm text-slate-400">{step.description}</p>
                  </motion.div>
                ) : null}
              </AnimatePresence>
            </li>
          );
        })}
      </ol>
    </div>
  );
}
