"use client";

import { useState } from "react";
import { Monitor, Tablet, Smartphone } from "lucide-react";
import { cn } from "@/lib/utils";
import { Product } from "@/lib/data/marketplace";
import { Device } from "./device-frame";
import { MockSitePreview } from "./mock-site-preview";

const devices: { key: Device; icon: typeof Monitor }[] = [
  { key: "desktop", icon: Monitor },
  { key: "tablet", icon: Tablet },
  { key: "mobile", icon: Smartphone },
];

const widths: Record<Device, string> = {
  desktop: "w-full",
  tablet: "w-[600px] max-w-full",
  mobile: "w-[380px] max-w-full",
};

export function FullPreview({ product }: { product: Product }) {
  const [device, setDevice] = useState<Device>("desktop");

  return (
    <div className="rounded-2xl border border-line-dark bg-ink-900/40 p-4 sm:p-6">
      <div className="flex items-center gap-1 rounded-full border border-line-dark bg-ink-950/60 p-1 sm:w-fit">
        {devices.map((d) => (
          <button
            key={d.key}
            onClick={() => setDevice(d.key)}
            className={cn(
              "flex flex-1 items-center justify-center gap-1.5 rounded-full px-4 py-2 text-[13px] font-medium transition-colors sm:flex-initial",
              device === d.key ? "bg-gold-400 text-ink-950" : "text-paper-50/50 hover:text-paper-50"
            )}
          >
            <d.icon className="size-4" />
            <span className="capitalize">{d.key}</span>
          </button>
        ))}
      </div>

      <div className={cn("mx-auto mt-6 transition-all duration-500 ease-out", widths[device])}>
        <div
          className={cn(
            "overflow-hidden border border-line-dark-strong bg-ink-950 shadow-[0_40px_100px_-40px_rgba(0,0,0,0.7)]",
            device === "desktop" ? "rounded-xl" : "rounded-[2rem]"
          )}
        >
          {device === "desktop" ? (
            <div className="flex items-center gap-1.5 border-b border-line-dark bg-ink-900 px-4 py-2.5">
              <span className="size-2.5 rounded-full bg-danger-500/60" />
              <span className="size-2.5 rounded-full bg-warning-500/60" />
              <span className="size-2.5 rounded-full bg-success-500/60" />
              <span className="font-mono-label ml-3 truncate text-[10px] text-paper-50/30">
                {product.name.toLowerCase().replace(/\s+/g, "")}.techbiss.site
              </span>
            </div>
          ) : (
            <div className="flex items-center justify-center bg-ink-900 py-2.5">
              <span className="h-1 w-10 rounded-full bg-paper-50/20" />
            </div>
          )}
          <div className={device === "desktop" ? "h-[640px] overflow-y-auto" : "h-[680px] overflow-y-auto"}>
            <MockSitePreview product={product} />
          </div>
        </div>
      </div>
    </div>
  );
}
