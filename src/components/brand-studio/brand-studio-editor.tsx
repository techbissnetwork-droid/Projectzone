"use client";

import { useState } from "react";
import Link from "next/link";
import { Monitor, Smartphone, Rocket, Sparkles } from "lucide-react";
import { cn } from "@/lib/utils";
import { Product } from "@/lib/data/marketplace";
import { BrandState, defaultBrandState } from "@/lib/brand";
import { QuickSetup } from "./quick-setup";
import { EditorPanel } from "./editor-panel";
import { BrandPreview } from "./brand-preview";
import { Button } from "@/components/ui/button";

export function BrandStudioEditor({ product }: { product: Product }) {
  const [brand, setBrand] = useState<BrandState>(() => defaultBrandState(product));
  const [setupDone, setSetupDone] = useState(false);
  const [device, setDevice] = useState<"desktop" | "mobile">("desktop");

  const update = (patch: Partial<BrandState>) => setBrand((prev) => ({ ...prev, ...patch }));

  if (!setupDone) {
    return (
      <QuickSetup
        initial={brand}
        onComplete={(state) => {
          setBrand(state);
          setSetupDone(true);
        }}
      />
    );
  }

  return (
    <div>
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div className="flex items-center gap-1 rounded-full border border-line-dark bg-ink-900/60 p-1">
          <button
            onClick={() => setDevice("desktop")}
            className={cn(
              "flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12.5px] font-medium transition-colors",
              device === "desktop" ? "bg-gold-400 text-ink-950" : "text-paper-50/50 hover:text-paper-50"
            )}
          >
            <Monitor className="size-3.5" />
            Desktop
          </button>
          <button
            onClick={() => setDevice("mobile")}
            className={cn(
              "flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12.5px] font-medium transition-colors",
              device === "mobile" ? "bg-gold-400 text-ink-950" : "text-paper-50/50 hover:text-paper-50"
            )}
          >
            <Smartphone className="size-3.5" />
            Mobile
          </button>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setSetupDone(false)}
            className="flex items-center gap-1.5 rounded-full border border-line-dark-strong px-3.5 py-2 text-[12.5px] font-medium text-paper-50/60 hover:text-paper-50"
          >
            <Sparkles className="size-3.5" />
            Quick Setup
          </button>
          <Button href={`/marketplace/launch/${product.slug}`} arrow size="sm">
            Continue to Launch
          </Button>
        </div>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
        <EditorPanel brand={brand} update={update} />

        <div className="rounded-2xl border border-line-dark bg-ink-900/40 p-4 sm:p-6">
          <div
            className={cn(
              "mx-auto overflow-hidden rounded-2xl border border-line-dark-strong bg-ink-950 shadow-[0_30px_80px_-30px_rgba(0,0,0,0.6)] transition-all duration-500",
              device === "desktop" ? "w-full" : "w-[340px] max-w-full"
            )}
          >
            <div className="flex items-center gap-1.5 border-b border-line-dark bg-ink-900 px-4 py-2.5">
              <span className="size-2.5 rounded-full bg-danger-500/60" />
              <span className="size-2.5 rounded-full bg-warning-500/60" />
              <span className="size-2.5 rounded-full bg-success-500/60" />
              <span className="font-mono-label ml-3 truncate text-[10px] text-paper-50/30">
                {brand.domain}
              </span>
            </div>
            <div className="h-[600px] overflow-y-auto">
              <BrandPreview brand={brand} />
            </div>
          </div>
        </div>
      </div>

      <div className="mt-8 flex flex-col items-start justify-between gap-5 rounded-2xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-7 sm:flex-row sm:items-center">
        <div>
          <div className="text-[16px] font-medium text-paper-50">Need help making it yours?</div>
          <p className="mt-1.5 max-w-md text-[13.5px] leading-relaxed text-paper-50/55">
            TECHBISS can integrate your logo, custom sections, payments,
            booking systems and more — beyond what the editor covers.
          </p>
        </div>
        <div className="flex shrink-0 gap-3">
          <Button href="/contact" variant="ghost">
            Customize My Theme
          </Button>
          <Link
            href={`/marketplace/launch/${product.slug}`}
            className="inline-flex items-center gap-1.5 rounded-full bg-gold-400 px-5 py-3 text-[13px] font-medium text-ink-950 hover:bg-gold-300"
          >
            <Rocket className="size-4" />
            Launch
          </Link>
        </div>
      </div>
    </div>
  );
}
