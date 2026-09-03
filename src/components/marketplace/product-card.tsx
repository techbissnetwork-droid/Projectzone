"use client";

import Link from "next/link";
import { Star, ArrowUpRight, LayoutTemplate } from "lucide-react";
import { Product } from "@/lib/data/marketplace";
import { formatPrice } from "@/lib/utils";
import { cn } from "@/lib/utils";

export function ProductCard({ product, large = false }: { product: Product; large?: boolean }) {
  return (
    <Link
      href={`/marketplace/product/${product.slug}`}
      className="group relative flex flex-col overflow-hidden rounded-2xl border border-line-dark bg-ink-900/50 transition-colors hover:border-line-dark-strong"
    >
      <div
        className={cn(
          "relative flex items-center justify-center overflow-hidden border-b border-line-dark",
          large ? "aspect-[16/10]" : "aspect-[4/3]"
        )}
        style={{
          background: `linear-gradient(135deg, ${product.accent}22, transparent 60%), radial-gradient(circle at 30% 20%, ${product.accent}33, transparent 55%)`,
        }}
      >
        <LayoutTemplate
          className="size-10 text-paper-50/15 transition-transform duration-500 group-hover:scale-110"
          strokeWidth={1}
        />
        {product.badge && (
          <span className="absolute left-3 top-3 rounded-full bg-paper-50 px-2.5 py-1 text-[10px] font-medium uppercase tracking-wide text-ink-950">
            {product.badge}
          </span>
        )}
        <div className="absolute inset-0 flex items-end justify-between bg-gradient-to-t from-ink-950/90 via-transparent to-transparent p-4 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
          <div className="flex flex-wrap gap-1.5">
            {product.technology.slice(0, 2).map((t) => (
              <span
                key={t}
                className="font-mono-label rounded-full border border-line-dark-strong bg-ink-950/70 px-2 py-1 text-[9px] uppercase text-paper-50/70"
              >
                {t}
              </span>
            ))}
          </div>
          <span className="flex size-8 items-center justify-center rounded-full bg-paper-50 text-ink-950">
            <ArrowUpRight className="size-4" />
          </span>
        </div>
      </div>

      <div className="flex flex-1 flex-col gap-3 p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <div className="text-[15px] font-medium text-paper-50">{product.name}</div>
            <div className="mt-0.5 text-[12px] text-paper-50/45">{product.industry}</div>
          </div>
          <div className="whitespace-nowrap text-[15px] font-medium text-paper-50">
            {formatPrice(product.priceCents)}
          </div>
        </div>
        <p className="line-clamp-2 text-[13px] leading-relaxed text-paper-50/50">
          {product.tagline}
        </p>
        <div className="mt-auto flex items-center justify-between border-t border-line-dark pt-3 text-[12px] text-paper-50/45">
          <span className="flex items-center gap-1">
            <Star className="size-3.5 fill-gold-400 text-gold-400" />
            {product.rating}
            <span className="text-paper-50/30">({product.reviews})</span>
          </span>
          <span>{product.category}</span>
        </div>
      </div>
    </Link>
  );
}
