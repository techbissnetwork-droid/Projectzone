"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { CheckCircle2, Circle, Activity } from "lucide-react";
import { services } from "@/lib/site-data";
import { getIcon } from "./icon-map";
import { cn } from "@/lib/cn";

const featured = services
  .filter((s) => s.hasDetailPage)
  .slice(0, 5)
  .map((s, i) => ({
    ...s,
    progress: [82, 64, 91, 58, 97][i] ?? 70,
  }));

/**
 * The flagship interactive "product panel" — a stylized dashboard mock built
 * entirely from local components. Clicking a service tab swaps the preview
 * widget. All numbers shown are illustrative product-feel only, not real
 * client analytics.
 */
export function HeroPanel() {
  const [active, setActive] = useState(0);
  const current = featured[active];
  const Icon = getIcon(current.icon);

  return (
    <div className="relative">
      <div
        className="pointer-events-none absolute -inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-violet-500/20 via-fuchsia-500/10 to-blue-500/20 blur-2xl"
        aria-hidden="true"
      />
      <div className="relative rounded-2xl border border-white/10 bg-white/[0.04] p-4 shadow-2xl shadow-black/40 backdrop-blur-sm sm:p-5">
        {/* window chrome */}
        <div className="flex items-center justify-between border-b border-white/10 pb-3">
          <div className="flex items-center gap-1.5" aria-hidden="true">
            <span className="h-2.5 w-2.5 rounded-full bg-rose-400/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-amber-300/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-400/70" />
          </div>
          <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">
            <Activity className="h-3 w-3" aria-hidden="true" />
            Services Overview
          </span>
        </div>

        {/* tab switcher */}
        <div
          role="tablist"
          aria-label="Preview a service"
          className="mt-4 flex snap-x gap-2 overflow-x-auto pb-1"
        >
          {featured.map((s, i) => {
            const TabIcon = getIcon(s.icon);
            const isActive = i === active;
            return (
              <button
                key={s.slug}
                role="tab"
                type="button"
                id={`hero-tab-${i}`}
                aria-selected={isActive}
                aria-controls="hero-preview-panel"
                onClick={() => setActive(i)}
                className={cn(
                  "flex min-h-[44px] shrink-0 snap-start items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
                  isActive
                    ? "border-violet-400/40 bg-violet-500/15 text-white"
                    : "border-white/10 bg-white/[0.02] text-slate-400 hover:text-white"
                )}
              >
                <TabIcon className="h-3.5 w-3.5" aria-hidden="true" />
                {s.title.split(" ").slice(0, 2).join(" ")}
              </button>
            );
          })}
        </div>

        {/* preview panel */}
        <div
          id="hero-preview-panel"
          role="tabpanel"
          aria-labelledby={`hero-tab-${active}`}
          className="relative mt-4 min-h-[220px] overflow-hidden rounded-xl border border-white/10 bg-[#0d0e19] p-5"
        >
          <AnimatePresence mode="wait">
            <motion.div
              key={current.slug}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
            >
              <div className="flex items-start gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-blue-500 text-white">
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                  <h3 className="font-display text-base font-semibold text-white">{current.title}</h3>
                  <p className="mt-1 text-sm text-slate-400">{current.shortDescription}</p>
                </div>
              </div>

              <div className="mt-5">
                <div className="flex items-center justify-between text-xs text-slate-500">
                  <span>Illustrative delivery readiness</span>
                  <span className="font-semibold text-slate-300">{current.progress}%</span>
                </div>
                <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                  <motion.div
                    className="h-full rounded-full bg-gradient-to-r from-violet-400 via-fuchsia-400 to-blue-400"
                    initial={{ width: 0 }}
                    animate={{ width: `${current.progress}%` }}
                    transition={{ duration: 0.7, ease: "easeOut" }}
                  />
                </div>
              </div>

              <ul className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                {current.features.slice(0, 4).map((f) => (
                  <li key={f} className="flex items-start gap-2 text-xs text-slate-300">
                    <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400" aria-hidden="true" />
                    <span className="line-clamp-2">{f}</span>
                  </li>
                ))}
              </ul>
            </motion.div>
          </AnimatePresence>
        </div>

        {/* mini legend */}
        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-slate-500">
          <span className="inline-flex items-center gap-1.5">
            <Circle className="h-2 w-2 fill-violet-400 text-violet-400" aria-hidden="true" />
            Interactive preview
          </span>
          <span>Click a tab to explore a different service</span>
        </div>
      </div>
    </div>
  );
}
