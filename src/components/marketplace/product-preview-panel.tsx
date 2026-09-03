"use client";

import { useState } from "react";
import Link from "next/link";
import { Monitor, Tablet, Smartphone, Maximize2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Product } from "@/lib/data/marketplace";
import { DeviceFrame, Device } from "./device-frame";
import { MockSitePreview } from "./mock-site-preview";

const devices: { key: Device; icon: typeof Monitor }[] = [
  { key: "desktop", icon: Monitor },
  { key: "tablet", icon: Tablet },
  { key: "mobile", icon: Smartphone },
];

export function ProductPreviewPanel({ product }: { product: Product }) {
  const [device, setDevice] = useState<Device>("desktop");

  return (
    <div className="rounded-2xl border border-line-dark bg-ink-900/40 p-4 sm:p-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-1 rounded-full border border-line-dark bg-ink-950/60 p-1">
          {devices.map((d) => (
            <button
              key={d.key}
              onClick={() => setDevice(d.key)}
              aria-label={`Preview on ${d.key}`}
              aria-pressed={device === d.key}
              className={cn(
                "flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-medium transition-colors",
                device === d.key
                  ? "bg-gold-400 text-ink-950"
                  : "text-paper-50/50 hover:text-paper-50"
              )}
            >
              <d.icon className="size-3.5" />
              <span className="hidden capitalize sm:inline">{d.key}</span>
            </button>
          ))}
        </div>
        <Link
          href={`/marketplace/product/${product.slug}/preview`}
          className="flex items-center gap-1.5 text-[12.5px] font-medium text-paper-50/60 hover:text-paper-50"
        >
          <Maximize2 className="size-3.5" />
          Full Preview
        </Link>
      </div>

      <div className="mt-5">
        <DeviceFrame device={device}>
          <MockSitePreview product={product} />
        </DeviceFrame>
      </div>
    </div>
  );
}
