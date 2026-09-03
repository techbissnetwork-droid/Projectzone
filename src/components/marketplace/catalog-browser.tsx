"use client";

import { useMemo, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Search, X, ChevronDown } from "lucide-react";
import { ThemeCard } from "@/components/marketplace/theme-card";
import { Eyebrow } from "@/components/ui/eyebrow";
import { cn } from "@/lib/utils";
import { categoryGroups, products, type ProductGroup } from "@/lib/data/marketplace";

type SortKey = "featured" | "new" | "trending" | "best-sellers" | "price-low" | "price-high";

const SORTS: { value: SortKey; label: string }[] = [
  { value: "featured", label: "Featured" },
  { value: "new", label: "New" },
  { value: "trending", label: "Trending" },
  { value: "best-sellers", label: "Best Sellers" },
  { value: "price-low", label: "Price: Low to High" },
  { value: "price-high", label: "Price: High to Low" },
];

const GROUP_OPTIONS = [
  { key: "all" as const, title: "All" },
  ...categoryGroups.map((g) => ({ key: g.key, title: g.title })),
];

function isProductGroup(v: string | undefined): v is ProductGroup {
  return !!v && categoryGroups.some((g) => g.key === v);
}

function isSortKey(v: string | undefined): v is SortKey {
  return !!v && SORTS.some((s) => s.value === v);
}

export function CatalogBrowser({
  initialSort,
  initialGroup,
  initialCategory,
}: {
  initialSort?: string;
  initialGroup?: string;
  initialCategory?: string;
}) {
  const [query, setQuery] = useState("");
  const [group, setGroup] = useState<ProductGroup | "all">(
    isProductGroup(initialGroup) ? initialGroup : "all",
  );
  const [category, setCategory] = useState<string | null>(initialCategory ?? null);
  const [sort, setSort] = useState<SortKey>(isSortKey(initialSort) ? initialSort : "featured");

  function selectGroup(next: ProductGroup | "all") {
    setGroup(next);
    if (category) {
      const belongs =
        next === "all" || categoryGroups.find((g) => g.key === next)?.categories.includes(category);
      if (!belongs) setCategory(null);
    }
  }

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    const list = products.filter((p) => {
      if (group !== "all" && p.group !== group) return false;
      if (category && p.category !== category) return false;
      if (
        q &&
        !(
          p.name.toLowerCase().includes(q) ||
          p.category.toLowerCase().includes(q) ||
          p.industry.toLowerCase().includes(q) ||
          p.tagline.toLowerCase().includes(q)
        )
      )
        return false;
      return true;
    });

    const sorted = [...list];
    switch (sort) {
      case "price-low":
        sorted.sort((a, b) => a.price - b.price);
        break;
      case "price-high":
        sorted.sort((a, b) => b.price - a.price);
        break;
      case "new":
        sorted.sort((a, b) => Number(b.badges.includes("New")) - Number(a.badges.includes("New")));
        break;
      case "trending":
        sorted.sort(
          (a, b) => Number(b.badges.includes("Trending")) - Number(a.badges.includes("Trending")),
        );
        break;
      case "best-sellers":
        sorted.sort(
          (a, b) => Number(b.badges.includes("Best Seller")) - Number(a.badges.includes("Best Seller")),
        );
        break;
      default:
        sorted.sort(
          (a, b) =>
            Number(b.badges.includes("Featured")) - Number(a.badges.includes("Featured")) ||
            b.rating - a.rating,
        );
    }
    return sorted;
  }, [query, group, category, sort]);

  return (
    <div>
      <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
        <div>
          <Eyebrow>Full Catalog</Eyebrow>
          <h2 className="mt-6 text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
            Every theme and product, in one place.
          </h2>
        </div>
        <p className="text-[13px] text-[var(--color-ink-faint)]">
          {filtered.length} of {products.length} products
        </p>
      </div>

      <div className="mt-10 flex flex-col gap-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[var(--color-ink-faint)]" />
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search by name, category or industry…"
              className="w-full rounded-full border border-[var(--color-border-strong)] bg-[var(--color-surface)] py-3 pl-11 pr-4 text-[14px] text-[var(--color-ink)] placeholder:text-[var(--color-ink-faint)] outline-none transition-colors focus-visible:border-[var(--color-accent)]"
            />
          </div>

          <div className="relative sm:w-[220px]">
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value as SortKey)}
              className="w-full appearance-none rounded-full border border-[var(--color-border-strong)] bg-[var(--color-surface)] py-3 pl-4 pr-10 text-[14px] text-[var(--color-ink)] outline-none transition-colors focus-visible:border-[var(--color-accent)]"
            >
              {SORTS.map((s) => (
                <option key={s.value} value={s.value} className="bg-[var(--color-surface)]">
                  Sort: {s.label}
                </option>
              ))}
            </select>
            <ChevronDown className="pointer-events-none absolute right-4 top-1/2 size-3.5 -translate-y-1/2 text-[var(--color-ink-faint)]" />
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {GROUP_OPTIONS.map((g) => (
            <button
              key={g.key}
              type="button"
              onClick={() => selectGroup(g.key)}
              className={cn(
                "rounded-full border px-4 py-2 text-[13px] font-medium transition-colors duration-200",
                group === g.key
                  ? "border-transparent bg-[var(--color-ink)] text-[var(--color-bg)]"
                  : "border-[var(--color-border-strong)] text-[var(--color-ink-muted)] hover:border-[var(--color-ink)] hover:text-[var(--color-ink)]",
              )}
            >
              {g.title}
            </button>
          ))}

          {category && (
            <button
              type="button"
              onClick={() => setCategory(null)}
              className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-accent-soft)] bg-[var(--color-accent-soft)] px-4 py-2 text-[13px] font-medium text-[#b7c3ff] transition-colors hover:bg-[var(--color-accent-soft)]/70"
            >
              {category}
              <X className="size-3.5" />
            </button>
          )}
        </div>
      </div>

      <motion.div
        layout
        className="mt-12 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <AnimatePresence mode="popLayout">
          {filtered.map((product) => (
            <motion.div
              key={product.slug}
              layout
              initial={{ opacity: 0, scale: 0.97 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.97 }}
              transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
            >
              <ThemeCard product={product} />
            </motion.div>
          ))}
        </AnimatePresence>
      </motion.div>

      {filtered.length === 0 && (
        <div className="mt-16 flex flex-col items-center gap-4 rounded-2xl border border-dashed border-[var(--color-border-strong)] py-20 text-center">
          <p className="text-[15px] text-[var(--color-ink-muted)]">
            No products match those filters.
          </p>
          <button
            type="button"
            onClick={() => {
              setQuery("");
              selectGroup("all");
              setCategory(null);
              setSort("featured");
            }}
            className="text-[13px] font-medium text-[var(--color-accent-ink)] underline underline-offset-4"
          >
            Clear all filters
          </button>
        </div>
      )}
    </div>
  );
}
