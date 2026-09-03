"use client";

import * as React from "react";
import { Search, SlidersHorizontal } from "lucide-react";
import { motion } from "framer-motion";
import { RevealGroup } from "@/components/ui/Reveal";
import { ProductCard } from "@/components/marketplace/ProductCard";
import { cn } from "@/lib/utils";
import { products, categories } from "@/lib/data/products";

type SortKey = "featured" | "price-asc" | "price-desc" | "rating" | "sales";

const sortOptions: { key: SortKey; label: string }[] = [
  { key: "featured", label: "Featured" },
  { key: "sales", label: "Best selling" },
  { key: "rating", label: "Top rated" },
  { key: "price-asc", label: "Price: Low to high" },
  { key: "price-desc", label: "Price: High to low" },
];

export function MarketplaceBrowser() {
  const [query, setQuery] = React.useState("");
  const [category, setCategory] = React.useState<string>("All");
  const [sort, setSort] = React.useState<SortKey>("featured");

  const filtered = React.useMemo(() => {
    let list = products.filter((p) => {
      const matchesCategory = category === "All" || p.category === category;
      const q = query.trim().toLowerCase();
      const matchesQuery =
        !q ||
        p.name.toLowerCase().includes(q) ||
        p.tagline.toLowerCase().includes(q) ||
        p.tags.some((t) => t.toLowerCase().includes(q));
      return matchesCategory && matchesQuery;
    });

    list = [...list].sort((a, b) => {
      switch (sort) {
        case "price-asc":
          return a.price - b.price;
        case "price-desc":
          return b.price - a.price;
        case "rating":
          return b.rating - a.rating;
        case "sales":
          return b.sales - a.sales;
        default:
          return (b.featured ? 1 : 0) - (a.featured ? 1 : 0);
      }
    });

    return list;
  }, [query, category, sort]);

  return (
    <div>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="relative w-full sm:max-w-xs">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-(--color-ink-faint)" />
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search products, tags..."
            className="focus-ring w-full rounded-full border border-(--color-border-strong) bg-(--color-surface-raised) py-2.5 pl-10 pr-4 text-sm text-(--color-ink) placeholder:text-(--color-ink-faint)"
          />
        </div>

        <div className="flex items-center gap-2">
          <SlidersHorizontal className="h-4 w-4 text-(--color-ink-faint)" />
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value as SortKey)}
            className="focus-ring rounded-full border border-(--color-border-strong) bg-(--color-surface-raised) py-2.5 pl-3.5 pr-8 text-sm text-(--color-ink)"
          >
            {sortOptions.map((o) => (
              <option key={o.key} value={o.key}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="mt-5 flex flex-wrap gap-2">
        {categories.map((c) => (
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

      <p className="mt-6 text-sm text-(--color-ink-faint)">
        {filtered.length} product{filtered.length === 1 ? "" : "s"}
      </p>

      {filtered.length > 0 ? (
        <RevealGroup key={`${query}-${category}-${sort}`} className="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((product) => (
            <ProductCard key={product.slug} product={product} />
          ))}
        </RevealGroup>
      ) : (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="mt-16 flex flex-col items-center gap-2 py-16 text-center"
        >
          <p className="text-lg font-medium text-(--color-ink)">No products match your search</p>
          <p className="text-sm text-(--color-ink-muted)">Try a different keyword or clear your filters.</p>
        </motion.div>
      )}
    </div>
  );
}
