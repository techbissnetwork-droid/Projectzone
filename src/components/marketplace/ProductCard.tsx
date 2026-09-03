"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { Star } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { revealItem } from "@/components/ui/Reveal";
import { formatCurrency } from "@/lib/utils";
import type { Product } from "@/lib/types";

export function ProductCard({ product }: { product: Product }) {
  return (
    <motion.div variants={revealItem}>
      <Link
        href={`/marketplace/${product.slug}`}
        className="focus-ring group flex h-full flex-col overflow-hidden rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
      >
        <div
          className="relative flex h-44 items-center justify-center overflow-hidden"
          style={{ background: `linear-gradient(135deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
        >
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.25),transparent_60%)] transition-transform duration-500 group-hover:scale-110" />
          <span className="text-4xl font-semibold tracking-tight text-white/90">{product.name.slice(0, 1)}</span>
          <div className="absolute left-3 top-3 flex gap-1.5">
            <Badge className="border-white/20 bg-black/25 text-white backdrop-blur">{product.category}</Badge>
            {product.new && (
              <Badge className="border-white/20 bg-white/90 text-slate-900">New</Badge>
            )}
          </div>
          {product.originalPrice && (
            <Badge className="absolute right-3 top-3 border-white/20 bg-black/25 text-white backdrop-blur">
              Sale
            </Badge>
          )}
        </div>
        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-start justify-between gap-2">
            <h3 className="text-base font-medium text-(--color-ink)">{product.name}</h3>
            <span className="flex shrink-0 items-center gap-1 text-xs text-(--color-ink-muted)">
              <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> {product.rating}
            </span>
          </div>
          <p className="mt-1.5 flex-1 text-sm text-(--color-ink-muted) line-clamp-2">{product.tagline}</p>
          <div className="mt-4 flex flex-wrap gap-1.5">
            {product.tags.slice(0, 2).map((t) => (
              <span key={t} className="rounded-full bg-(--color-surface-raised) px-2.5 py-1 text-[0.7rem] text-(--color-ink-faint)">
                {t}
              </span>
            ))}
          </div>
          <div className="mt-4 flex items-center justify-between border-t border-(--color-border) pt-4">
            <div className="flex items-center gap-2">
              <span className="text-base font-medium text-(--color-ink)">{formatCurrency(product.price)}</span>
              {product.originalPrice && (
                <span className="text-xs text-(--color-ink-faint) line-through">
                  {formatCurrency(product.originalPrice)}
                </span>
              )}
            </div>
            <span className="text-xs text-(--color-ink-faint)">{product.sales.toLocaleString()} sales</span>
          </div>
        </div>
      </Link>
    </motion.div>
  );
}
