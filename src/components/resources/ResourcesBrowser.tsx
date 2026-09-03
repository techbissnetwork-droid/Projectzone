"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowUpRight } from "lucide-react";
import { RevealGroup, revealItem } from "@/components/ui/Reveal";
import { Badge } from "@/components/ui/Badge";
import { cn } from "@/lib/utils";
import { articles, articleCategories } from "@/lib/data/resources";

export function ResourcesBrowser() {
  const [category, setCategory] = React.useState<string>("All");
  const filtered = category === "All" ? articles : articles.filter((a) => a.category === category);

  return (
    <div>
      <div className="flex flex-wrap justify-center gap-2">
        {articleCategories.map((c) => (
          <button
            key={c}
            type="button"
            onClick={() => setCategory(c)}
            className={cn(
              "focus-ring rounded-full border px-3.5 py-1.5 text-sm transition-colors",
              category === c
                ? "border-(--color-accent) bg-(--color-accent)/12 text-(--color-accent-2)"
                : "border-(--color-border-strong) text-(--color-ink-muted) hover:text-(--color-ink)",
            )}
          >
            {c}
          </button>
        ))}
      </div>

      <RevealGroup key={category} className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((a) => (
          <motion.div key={a.slug} variants={revealItem}>
            <Link
              href={`/resources/${a.slug}`}
              className="focus-ring group flex h-full flex-col rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
            >
              <div className="flex items-center justify-between">
                <Badge variant="accent">{a.category}</Badge>
                <ArrowUpRight className="h-4 w-4 text-(--color-ink-faint) transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-(--color-accent-2)" />
              </div>
              <h3 className="mt-4 text-base font-medium leading-snug text-(--color-ink)">{a.title}</h3>
              <p className="mt-2 flex-1 text-sm leading-relaxed text-(--color-ink-muted) line-clamp-3">{a.excerpt}</p>
              <div className="mt-5 flex items-center gap-2 border-t border-(--color-border) pt-4 text-xs text-(--color-ink-faint)">
                <span>{a.author}</span>
                <span>·</span>
                <span>{a.date}</span>
                <span>·</span>
                <span>{a.readTime}</span>
              </div>
            </Link>
          </motion.div>
        ))}
      </RevealGroup>
    </div>
  );
}
