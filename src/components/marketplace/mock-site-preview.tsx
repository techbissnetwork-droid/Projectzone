"use client";

import { useState } from "react";
import { Product } from "@/lib/data/marketplace";
import { Star, ShoppingCart, Menu } from "lucide-react";
import { cn } from "@/lib/utils";

export function MockSitePreview({ product }: { product: Product }) {
  const [page, setPage] = useState(product.pages[0]);
  const [navOpen, setNavOpen] = useState(false);

  const isCommerce = product.ecommerce;
  const isApp = product.application && !product.ecommerce;

  return (
    <div className="flex min-h-full flex-col bg-paper-50 text-ink-950">
      <div className="flex items-center justify-between border-b border-black/10 px-5 py-3.5">
        <span className="text-[14px] font-semibold tracking-tight">{product.name}</span>
        <nav className="hidden items-center gap-4 sm:flex">
          {product.pages.slice(0, 5).map((p) => (
            <button
              key={p}
              onClick={() => setPage(p)}
              className={cn(
                "text-[12px] font-medium transition-colors",
                page === p ? "text-ink-950" : "text-ink-950/40 hover:text-ink-950/70"
              )}
              style={page === p ? { color: product.accent } : undefined}
            >
              {p}
            </button>
          ))}
        </nav>
        <button
          aria-label="Toggle menu"
          className="rounded-full px-3.5 py-1.5 text-[11px] font-medium text-white sm:hidden"
          style={{ background: product.accent }}
          onClick={() => setNavOpen((v) => !v)}
        >
          <Menu className="size-3.5" />
        </button>
      </div>

      {navOpen && (
        <div className="flex flex-col border-b border-black/10 bg-white sm:hidden">
          {product.pages.map((p) => (
            <button
              key={p}
              onClick={() => {
                setPage(p);
                setNavOpen(false);
              }}
              className="border-b border-black/5 px-5 py-3 text-left text-[13px] font-medium text-ink-950/70"
            >
              {p}
            </button>
          ))}
        </div>
      )}

      <div
        className="flex flex-col items-start gap-3 px-5 py-10"
        style={{
          background: `linear-gradient(160deg, ${product.accent}18, transparent 60%)`,
        }}
      >
        <span
          className="rounded-full px-2.5 py-1 text-[10px] font-medium uppercase text-white"
          style={{ background: product.accent }}
        >
          {page}
        </span>
        <h2 className="max-w-xs text-[22px] font-semibold leading-tight tracking-tight">
          {product.industry} built for {isCommerce ? "selling online" : isApp ? "daily operations" : "growth"}.
        </h2>
        <p className="max-w-sm text-[12.5px] leading-relaxed text-ink-950/55">{product.tagline}</p>
        <button
          className="mt-2 rounded-full px-4 py-2 text-[12px] font-medium text-white"
          style={{ background: product.accent }}
        >
          {isCommerce ? "Shop Now" : isApp ? "Open Dashboard" : "Get Started"}
        </button>
      </div>

      {isCommerce ? (
        <div className="grid grid-cols-2 gap-3 px-5 pb-8 sm:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex flex-col gap-2 rounded-lg border border-black/10 p-2.5">
              <div
                className="aspect-square rounded-md"
                style={{ background: `${product.accent}22` }}
              />
              <div className="h-2 w-3/4 rounded bg-black/10" />
              <div className="h-2 w-1/2 rounded bg-black/10" />
            </div>
          ))}
        </div>
      ) : isApp ? (
        <div className="flex flex-col gap-3 px-5 pb-8">
          <div className="grid grid-cols-3 gap-2.5">
            {[
              { label: "Today", value: 24 },
              { label: "This Week", value: 68 },
              { label: "This Month", value: 82 },
            ].map((stat) => (
              <div key={stat.label} className="rounded-lg border border-black/10 p-3">
                <div className="text-[10px] text-ink-950/40">{stat.label}</div>
                <div className="mt-1 text-[16px] font-semibold">{stat.value}</div>
              </div>
            ))}
          </div>
          <div className="flex flex-col gap-2 rounded-lg border border-black/10 p-3">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="flex items-center justify-between border-b border-black/5 py-2 last:border-0">
                <div className="h-2 w-1/3 rounded bg-black/10" />
                <div className="h-2 w-10 rounded bg-black/10" />
              </div>
            ))}
          </div>
        </div>
      ) : (
        <div className="flex flex-col gap-4 px-5 pb-8">
          <div className="grid grid-cols-2 gap-3">
            {product.features.slice(0, 4).map((f) => (
              <div key={f} className="rounded-lg border border-black/10 p-3">
                <div
                  className="mb-2 size-6 rounded-md"
                  style={{ background: `${product.accent}33` }}
                />
                <div className="text-[11.5px] font-medium leading-snug text-ink-950/80">{f}</div>
              </div>
            ))}
          </div>
          <div className="flex items-center gap-1 text-[11px] text-ink-950/40">
            <Star className="size-3 fill-current" style={{ color: product.accent }} />
            {product.rating} rating from {product.reviews} businesses
          </div>
        </div>
      )}

      <div className="mt-auto flex items-center justify-between border-t border-black/10 px-5 py-4 text-[11px] text-ink-950/40">
        <span>© {product.name}</span>
        {isCommerce && <ShoppingCart className="size-3.5" />}
      </div>
    </div>
  );
}
