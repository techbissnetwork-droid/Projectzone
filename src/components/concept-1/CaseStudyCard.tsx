"use client";

import { motion } from "framer-motion";
import type { CaseStudy } from "@/lib/site-data";
import { cn } from "@/lib/cn";

export function CaseStudyCard({ study, className }: { study: CaseStudy; className?: string }) {
  return (
    <motion.div
      whileHover={{ y: -4 }}
      transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
      className={cn(
        "flex h-full flex-col rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur-xl",
        className
      )}
    >
      <span className="inline-flex w-fit items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium uppercase tracking-wide text-neutral-300">
        {study.industry}
      </span>
      <h3 className="mt-5 text-lg font-semibold tracking-tight text-neutral-50">
        {study.title}
      </h3>
      <p className="mt-3 flex-1 text-sm leading-relaxed text-neutral-400">{study.summary}</p>
      <div className="mt-5 flex flex-wrap gap-2">
        {study.services.map((service) => (
          <span
            key={service}
            className="rounded-full bg-white/5 px-3 py-1 text-xs text-neutral-300"
          >
            {service}
          </span>
        ))}
      </div>
      <p className="mt-5 border-t border-white/10 pt-4 text-xs italic leading-relaxed text-neutral-500">
        {study.outcome}
      </p>
    </motion.div>
  );
}
