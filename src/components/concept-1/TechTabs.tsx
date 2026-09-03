"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import type { TechCategory } from "@/lib/site-data";
import { cn } from "@/lib/cn";

export function TechTabs({ categories }: { categories: TechCategory[] }) {
  const [active, setActive] = useState(0);
  const activeCategory = categories[active];

  return (
    <div className="rounded-3xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl sm:p-6">
      <div
        role="tablist"
        aria-label="Technology capability categories"
        className="flex flex-wrap gap-2 border-b border-white/10 pb-4"
      >
        {categories.map((category, index) => {
          const isActive = index === active;
          return (
            <button
              key={category.category}
              role="tab"
              id={`tech-tab-${index}`}
              aria-selected={isActive}
              aria-controls={`tech-panel-${index}`}
              onClick={() => setActive(index)}
              className={cn(
                "rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70",
                isActive
                  ? "bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500 text-neutral-950"
                  : "bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-neutral-50"
              )}
            >
              {category.category}
            </button>
          );
        })}
      </div>

      <div
        role="tabpanel"
        id={`tech-panel-${active}`}
        aria-labelledby={`tech-tab-${active}`}
        className="pt-6"
      >
        <motion.div
          key={activeCategory.category}
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
          className="flex flex-wrap gap-3"
        >
          {activeCategory.items.map((item) => (
            <span
              key={item}
              className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-neutral-200"
            >
              {item}
            </span>
          ))}
        </motion.div>
      </div>
    </div>
  );
}
