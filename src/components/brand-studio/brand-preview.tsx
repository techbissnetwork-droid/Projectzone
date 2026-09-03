"use client";

import { Star, Phone, Mail, MapPin } from "lucide-react";
import { BrandState } from "@/lib/brand";
import { cn } from "@/lib/utils";

const fontClass: Record<BrandState["font"], string> = {
  "Modern Sans": "font-sans",
  "Classic Serif": "font-serif",
  Rounded: "font-sans",
};

export function BrandPreview({ brand }: { brand: BrandState }) {
  const initial = brand.businessName.trim().charAt(0).toUpperCase() || "T";

  return (
    <div className={cn("flex min-h-full flex-col bg-paper-50 text-ink-950", fontClass[brand.font])}>
      <div className="flex items-center justify-between border-b border-black/10 px-5 py-3.5">
        <div className="flex items-center gap-2">
          <span
            className="flex size-7 items-center justify-center rounded-md text-[12px] font-bold text-white"
            style={{ background: brand.primaryColor }}
          >
            {initial}
          </span>
          <span className="text-[14px] font-semibold tracking-tight">{brand.businessName}</span>
        </div>
        <nav className="hidden items-center gap-4 sm:flex">
          {["Home", "Services", "About", "Contact"].map((p) => (
            <span key={p} className="text-[12px] font-medium text-ink-950/45">
              {p}
            </span>
          ))}
        </nav>
        <button
          className="rounded-full px-3.5 py-1.5 text-[11px] font-medium text-white"
          style={{ background: brand.secondaryColor }}
        >
          Contact
        </button>
      </div>

      <div
        className="flex flex-col items-start gap-3 px-5 py-12"
        style={{ background: `linear-gradient(160deg, ${brand.primaryColor}22, transparent 65%)` }}
      >
        <span
          className="rounded-full px-2.5 py-1 text-[10px] font-medium uppercase text-white"
          style={{ background: brand.primaryColor }}
        >
          {brand.industry}
        </span>
        <h2 className="max-w-xs text-[24px] font-semibold leading-tight tracking-tight">
          {brand.headline}
        </h2>
        <p className="max-w-sm text-[12.5px] leading-relaxed text-ink-950/55">{brand.tagline}</p>
        <button
          className="mt-2 rounded-full px-4 py-2 text-[12px] font-medium text-white"
          style={{ background: brand.primaryColor }}
        >
          Get Started
        </button>
      </div>

      <div className="grid grid-cols-2 gap-3 px-5 pb-8">
        {brand.services.map((f) => (
          <div key={f} className="rounded-lg border border-black/10 p-3">
            <div className="mb-2 size-6 rounded-md" style={{ background: `${brand.primaryColor}33` }} />
            <div className="text-[11.5px] font-medium leading-snug text-ink-950/80">{f}</div>
          </div>
        ))}
      </div>

      <div className="flex items-center gap-1 px-5 pb-6 text-[11px] text-ink-950/40">
        <Star className="size-3 fill-current" style={{ color: brand.primaryColor }} />
        4.9 rating from local customers
      </div>

      <div className="mt-auto flex flex-col gap-2 border-t border-black/10 px-5 py-5 text-[11px] text-ink-950/50">
        <span className="text-[12px] font-semibold text-ink-950">{brand.businessName}</span>
        <span className="flex items-center gap-1.5">
          <Phone className="size-3" />
          {brand.phone}
        </span>
        <span className="flex items-center gap-1.5">
          <Mail className="size-3" />
          {brand.email}
        </span>
        <span className="flex items-center gap-1.5">
          <MapPin className="size-3" />
          {brand.address}
        </span>
        <span className="mt-1 text-ink-950/35">{brand.hours}</span>
      </div>
    </div>
  );
}
