"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { Cpu, Server, Smartphone, Cloud, ShieldCheck, Briefcase, type LucideIcon } from "lucide-react";
import type { TechCategory } from "@/lib/site-data";
import { cn } from "@/lib/cn";

const categoryIcon: Record<string, LucideIcon> = {
  "Frontend Engineering": Cpu,
  "Backend & APIs": Server,
  Mobile: Smartphone,
  "Cloud & Infrastructure": Cloud,
  Security: ShieldCheck,
  "Business Systems": Briefcase,
};

export function TechDashboard({ categories }: { categories: TechCategory[] }) {
  const [active, setActive] = useState(0);
  const current = categories[active];

  return (
    <div className="grid grid-cols-1 gap-3 lg:grid-cols-[280px_1fr] lg:gap-6">
      <div
        role="tablist"
        aria-label="Technology categories"
        className="flex snap-x gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0"
      >
        {categories.map((cat, i) => {
          const Icon = categoryIcon[cat.category] ?? Cpu;
          const isActive = i === active;
          return (
            <button
              key={cat.category}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => setActive(i)}
              className={cn(
                "flex min-h-[44px] shrink-0 snap-start items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 lg:w-full",
                isActive
                  ? "border-violet-400/40 bg-violet-500/15 text-white"
                  : "border-white/10 bg-white/[0.02] text-slate-400 hover:text-white"
              )}
            >
              <span
                className={cn(
                  "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg",
                  isActive ? "bg-gradient-to-br from-violet-500 to-blue-500 text-white" : "bg-white/5 text-slate-400"
                )}
              >
                <Icon className="h-4 w-4" aria-hidden="true" />
              </span>
              <span className="whitespace-nowrap lg:whitespace-normal">{cat.category}</span>
            </button>
          );
        })}
      </div>

      <div className="min-h-[220px] rounded-2xl border border-white/10 bg-white/[0.03] p-6 sm:p-8">
        <AnimatePresence mode="wait">
          <motion.div
            key={current.category}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
          >
            <h3 className="font-display text-lg font-semibold text-white">{current.category}</h3>
            <div className="mt-5 flex flex-wrap gap-2">
              {current.items.map((item, i) => (
                <motion.span
                  key={item}
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ duration: 0.25, delay: i * 0.05 }}
                  className="rounded-full border border-white/10 bg-gradient-to-r from-violet-500/10 to-blue-500/10 px-3.5 py-2 text-sm font-medium text-slate-200"
                >
                  {item}
                </motion.span>
              ))}
            </div>
          </motion.div>
        </AnimatePresence>
      </div>
    </div>
  );
}
