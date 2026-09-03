"use client";

import { motion } from "framer-motion";
import { Clock } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { processSteps } from "@/lib/data/process";

export function ProcessTimeline() {
  return (
    <div className="relative">
      <div className="absolute left-6 top-2 bottom-2 hidden w-px bg-(--color-border) sm:block" />
      <div className="flex flex-col gap-10">
        {processSteps.map((step, i) => (
          <motion.div
            key={step.number}
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-100px" }}
            transition={{ duration: 0.55, delay: i * 0.05, ease: [0.16, 1, 0.3, 1] }}
            className="relative flex flex-col gap-5 sm:flex-row sm:gap-8"
          >
            <div className="flex items-center gap-4 sm:w-12 sm:flex-col sm:items-center sm:gap-0">
              <div className="relative z-10 flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-(--color-border-strong) bg-(--color-surface) text-sm font-medium text-(--color-ink)">
                {step.number}
              </div>
            </div>
            <div className="flex-1 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-7">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-xl font-medium text-(--color-ink)">{step.title}</h3>
                <Badge variant="outline">
                  <Clock className="h-3 w-3" /> {step.duration}
                </Badge>
              </div>
              <p className="mt-3 text-sm leading-relaxed text-(--color-ink-muted) sm:text-base">{step.description}</p>
              <div className="mt-5 flex flex-wrap gap-2">
                {step.deliverables.map((d) => (
                  <span
                    key={d}
                    className="rounded-full bg-(--color-surface-raised) px-3 py-1.5 text-xs text-(--color-ink-muted)"
                  >
                    {d}
                  </span>
                ))}
              </div>
            </div>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
