"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, AnimatePresence } from "framer-motion";
import { ArrowLeft, Monitor, Tablet, Smartphone } from "lucide-react";
import { ThemeVisual } from "@/components/marketplace/theme-visual";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { Product } from "@/lib/data/marketplace";

type Device = "desktop" | "tablet" | "mobile";

const DEVICES: { key: Device; label: string; icon: typeof Monitor }[] = [
  { key: "desktop", label: "Desktop", icon: Monitor },
  { key: "tablet", label: "Tablet", icon: Tablet },
  { key: "mobile", label: "Mobile", icon: Smartphone },
];

const FRAME: Record<Device, string> = {
  desktop: "w-full aspect-[16/9]",
  tablet: "w-full max-w-[520px] aspect-[3/4]",
  mobile: "w-full max-w-[300px] aspect-[9/19]",
};

export function LivePreview({ product }: { product: Product }) {
  const [device, setDevice] = useState<Device>("desktop");
  const [activePage, setActivePage] = useState(product.pages[0] ?? "Home");

  const seedProduct = {
    ...product,
    slug: `${product.slug}-${activePage}-${device}`,
  };

  return (
    <div>
      <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
        <Link
          href={`/marketplace/theme/${product.slug}`}
          className="inline-flex items-center gap-2 text-[13.5px] font-medium text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
        >
          <ArrowLeft className="size-3.5" />
          Back to Theme
        </Link>

        <div className="flex items-center gap-1 self-start rounded-full border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-1">
          {DEVICES.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              type="button"
              onClick={() => setDevice(key)}
              className={cn(
                "inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[12.5px] font-medium transition-colors duration-200",
                device === key
                  ? "bg-[var(--color-ink)] text-[var(--color-bg)]"
                  : "text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]",
              )}
            >
              <Icon className="size-3.5" strokeWidth={1.75} />
              {label}
            </button>
          ))}
        </div>

        <Button href={`/marketplace/checkout/${product.slug}`} variant="primary" size="sm">
          Buy Theme
        </Button>
      </div>

      <div className="mt-8 flex flex-wrap gap-2 border-b border-[var(--color-border)] pb-6">
        {product.pages.map((page) => (
          <button
            key={page}
            type="button"
            onClick={() => setActivePage(page)}
            className={cn(
              "rounded-full border px-4 py-2 text-[13px] font-medium transition-colors duration-200",
              activePage === page
                ? "border-transparent bg-[var(--color-accent-soft)] text-[#b7c3ff]"
                : "border-[var(--color-border-strong)] text-[var(--color-ink-muted)] hover:border-[var(--color-ink)] hover:text-[var(--color-ink)]",
            )}
          >
            {page}
          </button>
        ))}
      </div>

      <div className="mt-10 flex justify-center">
        <motion.div layout className={cn("mx-auto", FRAME[device])} transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}>
          <div
            className={cn(
              "relative h-full w-full overflow-hidden border border-[var(--color-border-strong)] bg-[var(--color-surface)] shadow-2xl shadow-black/40",
              device === "mobile" ? "rounded-[32px] p-1.5" : "rounded-2xl",
            )}
          >
            {device === "mobile" && (
              <div className="absolute left-1/2 top-2.5 z-10 h-1.5 w-16 -translate-x-1/2 rounded-full bg-black/40" />
            )}
            <div className={cn("h-full w-full overflow-hidden", device === "mobile" ? "rounded-[26px]" : "rounded-xl")}>
              <AnimatePresence mode="wait">
                <motion.div
                  key={`${activePage}-${device}`}
                  initial={{ opacity: 0, x: 16 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: -16 }}
                  transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
                  className="h-full w-full"
                >
                  <ThemeVisual product={seedProduct} className="h-full" chrome={device !== "mobile"} />
                </motion.div>
              </AnimatePresence>
            </div>
          </div>
        </motion.div>
      </div>

      <p className="mt-8 text-center text-[13px] text-[var(--color-ink-faint)]">
        Simulated preview — layout regenerates per page and device to illustrate how {product.name}{" "}
        adapts across screens.
      </p>
    </div>
  );
}
