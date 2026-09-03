"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { Star, Smartphone } from "lucide-react";
import { ThemeVisual } from "@/components/marketplace/theme-visual";
import { Badge } from "@/components/ui/eyebrow";
import { cn } from "@/lib/utils";
import type { Product } from "@/lib/data/marketplace";

const badgeTone: Record<string, "accent" | "gold" | "live" | "neutral"> = {
  Featured: "accent",
  "Best Seller": "gold",
  Trending: "live",
  New: "accent",
  Free: "neutral",
};

export function ThemeCard({
  product,
  className,
  size = "md",
}: {
  product: Product;
  className?: string;
  size?: "md" | "lg";
}) {
  return (
    <Link href={`/marketplace/theme/${product.slug}`} className={cn("group block", className)}>
      <div
        className={cn(
          "relative overflow-hidden rounded-2xl",
          size === "lg" ? "aspect-[4/3]" : "aspect-[5/4]",
        )}
      >
        <motion.div
          className="h-full w-full"
          initial={false}
          whileHover={{ scale: 1.035 }}
          transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
        >
          <ThemeVisual product={product} className="h-full" />
        </motion.div>

        <div className="pointer-events-none absolute inset-x-3 top-3 flex flex-wrap gap-1.5">
          {product.badges.map((b) => (
            <Badge key={b} tone={badgeTone[b] ?? "neutral"}>
              {b}
            </Badge>
          ))}
        </div>

        <div className="absolute inset-x-0 bottom-0 flex translate-y-2 items-center justify-between bg-gradient-to-t from-black/70 to-transparent px-4 py-3 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
          <span className="text-[12.5px] font-medium text-white">View Theme →</span>
          {product.responsive && (
            <Smartphone className="size-3.5 text-white/70" strokeWidth={1.75} />
          )}
        </div>
      </div>

      <div className="mt-3.5 flex items-start justify-between gap-3">
        <div>
          <h3 className="text-[15px] font-medium text-[var(--color-ink)] transition-colors group-hover:text-white">
            {product.name}
          </h3>
          <p className="mt-0.5 text-[13px] text-[var(--color-ink-faint)]">
            {product.category} · {product.industry}
          </p>
        </div>
        <div className="flex shrink-0 flex-col items-end">
          <span className="text-[14px] font-medium">
            {product.free ? "Free" : `$${product.price}`}
          </span>
          <span className="mt-0.5 flex items-center gap-1 text-[12px] text-[var(--color-ink-faint)]">
            <Star className="size-3 fill-[var(--color-gold)] text-[var(--color-gold)]" />
            {product.rating}
          </span>
        </div>
      </div>
    </Link>
  );
}
