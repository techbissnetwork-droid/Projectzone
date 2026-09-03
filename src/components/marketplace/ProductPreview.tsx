"use client";

import * as React from "react";
import { Laptop, Smartphone, Tablet } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Product } from "@/lib/types";

const devices = [
  { key: "desktop", icon: Laptop, width: "100%" },
  { key: "tablet", icon: Tablet, width: "480px" },
  { key: "mobile", icon: Smartphone, width: "300px" },
] as const;

export function ProductPreview({ product }: { product: Product }) {
  const [device, setDevice] = React.useState<(typeof devices)[number]["key"]>("desktop");
  const active = devices.find((d) => d.key === device)!;

  return (
    <div className="rounded-(--radius-xl) border border-(--color-border) bg-(--color-surface) p-2 sm:p-3">
      <div className="flex items-center justify-between px-3 py-2.5">
        <div className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-full bg-red-500/70" />
          <span className="h-2.5 w-2.5 rounded-full bg-amber-500/70" />
          <span className="h-2.5 w-2.5 rounded-full bg-emerald-500/70" />
          <span className="ml-3 text-xs text-(--color-ink-faint)">
            preview.techbiss.com/{product.slug}
          </span>
        </div>
        <div className="flex items-center gap-1 rounded-full border border-(--color-border-strong) p-1">
          {devices.map(({ key, icon: Icon }) => (
            <button
              key={key}
              type="button"
              onClick={() => setDevice(key)}
              className={cn(
                "focus-ring flex h-7 w-7 items-center justify-center rounded-full transition-colors",
                device === key
                  ? "bg-(--color-accent) text-white"
                  : "text-(--color-ink-faint) hover:text-(--color-ink)",
              )}
              aria-label={`Preview on ${key}`}
            >
              <Icon className="h-3.5 w-3.5" />
            </button>
          ))}
        </div>
      </div>

      <div className="flex justify-center overflow-hidden rounded-(--radius-lg) bg-(--color-canvas) p-4 sm:p-6">
        <div
          className="w-full overflow-hidden rounded-(--radius-md) border border-(--color-border) transition-all duration-500 ease-out"
          style={{ maxWidth: active.width }}
        >
          <div
            className="relative flex flex-col gap-4 p-6"
            style={{ background: `linear-gradient(160deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
          >
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_0%,rgba(255,255,255,0.18),transparent_55%)]" />
            <div className="relative flex items-center justify-between">
              <span className="text-sm font-semibold text-white">{product.name}</span>
              <div className="flex gap-1.5">
                <span className="h-1.5 w-6 rounded-full bg-white/40" />
                <span className="h-1.5 w-6 rounded-full bg-white/40" />
                <span className="h-1.5 w-6 rounded-full bg-white/70" />
              </div>
            </div>
            <div className="relative flex flex-col gap-2">
              <div className="h-3 w-3/4 rounded-full bg-white/70" />
              <div className="h-3 w-1/2 rounded-full bg-white/40" />
            </div>
            <div className="relative h-2 w-24 rounded-full bg-white" />
          </div>
          <div className="grid grid-cols-3 gap-2 bg-(--color-surface) p-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="flex flex-col gap-2 rounded-(--radius-sm) border border-(--color-border) p-2.5">
                <div className="h-12 rounded-sm bg-(--color-surface-raised)" />
                <div className="h-1.5 w-3/4 rounded-full bg-(--color-surface-raised)" />
                <div className="h-1.5 w-1/2 rounded-full bg-(--color-surface-raised)" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
