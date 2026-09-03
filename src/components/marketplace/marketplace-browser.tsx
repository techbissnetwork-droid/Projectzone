"use client";

import { useMemo, useState } from "react";
import { Search, SlidersHorizontal, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { products, categories, ProductCategory } from "@/lib/data/marketplace";
import { ProductCard } from "@/components/marketplace/product-card";

const groupNames = categories.map((c) => c.group) as ProductCategory[];
const sorts = ["Featured", "Newest", "Price: Low to High", "Price: High to Low", "Top Rated"];

export function MarketplaceBrowser() {
  const [query, setQuery] = useState("");
  const [group, setGroup] = useState<ProductCategory | "All">("All");
  const [priceFilter, setPriceFilter] = useState<"All" | "Free" | "Premium">("All");
  const [sort, setSort] = useState(sorts[0]);
  const [filtersOpen, setFiltersOpen] = useState(false);

  const filtered = useMemo(() => {
    let list = products.slice();

    if (group !== "All") list = list.filter((p) => p.category === group);
    if (priceFilter === "Free") list = list.filter((p) => p.priceCents === 0);
    if (priceFilter === "Premium") list = list.filter((p) => p.priceCents > 0);
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter(
        (p) =>
          p.name.toLowerCase().includes(q) ||
          p.industry.toLowerCase().includes(q) ||
          p.tagline.toLowerCase().includes(q)
      );
    }

    switch (sort) {
      case "Newest":
        list = list.filter((p) => p.badge === "New").concat(list.filter((p) => p.badge !== "New"));
        break;
      case "Price: Low to High":
        list = list.sort((a, b) => a.priceCents - b.priceCents);
        break;
      case "Price: High to Low":
        list = list.sort((a, b) => b.priceCents - a.priceCents);
        break;
      case "Top Rated":
        list = list.sort((a, b) => b.rating - a.rating);
        break;
      default:
        break;
    }

    return list;
  }, [group, priceFilter, query, sort]);

  return (
    <div id="categories">
      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-paper-50/35" />
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search themes, applications, digital products…"
              className="w-full rounded-full border border-line-dark-strong bg-ink-900/60 py-3.5 pl-11 pr-4 text-[14px] text-paper-50 placeholder:text-paper-50/35 outline-none transition-colors focus:border-gold-500/50"
            />
          </div>
          <button
            onClick={() => setFiltersOpen((v) => !v)}
            className={cn(
              "flex shrink-0 items-center gap-2 rounded-full border px-5 py-3.5 text-[13px] font-medium transition-colors",
              filtersOpen
                ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                : "border-line-dark-strong text-paper-50/70 hover:border-line-dark-strong"
            )}
          >
            <SlidersHorizontal className="size-4" />
            Filters
          </button>
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="shrink-0 rounded-full border border-line-dark-strong bg-ink-900/60 px-4 py-3.5 text-[13px] text-paper-50/80 outline-none"
          >
            {sorts.map((s) => (
              <option key={s} value={s} className="bg-ink-900">
                {s}
              </option>
            ))}
          </select>
        </div>

        <div className="no-scrollbar flex items-center gap-2 overflow-x-auto pb-1">
          <button
            onClick={() => setGroup("All")}
            className={cn(
              "shrink-0 rounded-full border px-4 py-2 text-[13px] font-medium transition-colors",
              group === "All"
                ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                : "border-line-dark text-paper-50/60 hover:border-line-dark-strong"
            )}
          >
            All Categories
          </button>
          {groupNames.map((g) => (
            <button
              key={g}
              onClick={() => setGroup(g)}
              className={cn(
                "shrink-0 rounded-full border px-4 py-2 text-[13px] font-medium transition-colors",
                group === g
                  ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                  : "border-line-dark text-paper-50/60 hover:border-line-dark-strong"
              )}
            >
              {g}
            </button>
          ))}
        </div>

        {filtersOpen && (
          <div className="flex flex-wrap items-center gap-2 rounded-xl border border-line-dark bg-ink-900/40 p-3">
            <span className="px-2 text-[12px] text-paper-50/40">Price:</span>
            {(["All", "Free", "Premium"] as const).map((p) => (
              <button
                key={p}
                onClick={() => setPriceFilter(p)}
                className={cn(
                  "rounded-full border px-3.5 py-1.5 text-[12.5px] font-medium transition-colors",
                  priceFilter === p
                    ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                    : "border-line-dark text-paper-50/55 hover:border-line-dark-strong"
                )}
              >
                {p}
              </button>
            ))}
            {(group !== "All" || priceFilter !== "All" || query) && (
              <button
                onClick={() => {
                  setGroup("All");
                  setPriceFilter("All");
                  setQuery("");
                }}
                className="ml-auto flex items-center gap-1 text-[12.5px] text-paper-50/45 hover:text-paper-50"
              >
                <X className="size-3.5" />
                Clear all
              </button>
            )}
          </div>
        )}
      </div>

      <div className="mt-4 text-[13px] text-paper-50/40">
        {filtered.length} product{filtered.length !== 1 ? "s" : ""}
      </div>

      {filtered.length > 0 ? (
        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((p) => (
            <ProductCard key={p.slug} product={p} />
          ))}
        </div>
      ) : (
        <div className="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-line-dark py-24 text-center">
          <p className="text-[15px] text-paper-50/50">No products match your filters.</p>
        </div>
      )}
    </div>
  );
}
