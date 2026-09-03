"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { ProcessStep } from "@/lib/site-data";
import { cn } from "@/lib/cn";

export function ProcessTimeline({ steps, className }: { steps: ProcessStep[]; className?: string }) {
  const shouldReduceMotion = useReducedMotion();

  return (
    <div className={cn("relative", className)}>
      <div
        aria-hidden="true"
        className="absolute left-6 top-2 bottom-2 w-px bg-gradient-to-b from-cyan-400/60 via-indigo-400/40 to-fuchsia-500/60 sm:left-8"
      />
      <ol className="space-y-10 sm:space-y-14">
        {steps.map((step, index) => (
          <motion.li
            key={step.step}
            initial={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: 24 }}
            whileInView={shouldReduceMotion ? { opacity: 1 } : { opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-100px" }}
            transition={{ duration: shouldReduceMotion ? 0.15 : 0.6, delay: shouldReduceMotion ? 0 : index * 0.05, ease: [0.16, 1, 0.3, 1] }}
            className="relative flex gap-6 pl-0 sm:gap-8"
          >
            <div className="relative z-10 flex h-12 w-12 flex-none items-center justify-center rounded-full border border-white/15 bg-neutral-950 text-sm font-semibold text-neutral-100 shadow-[0_0_20px_rgba(99,102,241,0.35)] sm:h-16 sm:w-16">
              <span className="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-transparent">
                {step.step}
              </span>
            </div>
            <div className="flex-1 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl sm:p-7">
              <h3 className="text-lg font-semibold tracking-tight text-neutral-50 sm:text-xl">
                {step.title}
              </h3>
              <p className="mt-3 text-sm leading-relaxed text-neutral-400 sm:text-base">
                {step.description}
              </p>
            </div>
          </motion.li>
        ))}
      </ol>
    </div>
  );
}
