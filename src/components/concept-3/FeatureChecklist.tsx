"use client";

import { motion } from "framer-motion";
import { CheckCircle2 } from "lucide-react";

/** Checklist whose items animate their checkmark in as they scroll into view. */
export function FeatureChecklist({ items, className }: { items: string[]; className?: string }) {
  return (
    <ul className={className}>
      {items.map((item, i) => (
        <motion.li
          key={item}
          initial={{ opacity: 0, x: -12 }}
          whileInView={{ opacity: 1, x: 0 }}
          viewport={{ once: true, margin: "-40px" }}
          transition={{ duration: 0.4, delay: i * 0.06, ease: "easeOut" }}
          className="flex items-start gap-3 border-b border-white/5 py-3 last:border-none"
        >
          <motion.span
            initial={{ scale: 0 }}
            whileInView={{ scale: 1 }}
            viewport={{ once: true, margin: "-40px" }}
            transition={{ delay: i * 0.06 + 0.15, type: "spring", stiffness: 400, damping: 20 }}
            className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300"
          >
            <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
          </motion.span>
          <span className="text-sm text-slate-300">{item}</span>
        </motion.li>
      ))}
    </ul>
  );
}
