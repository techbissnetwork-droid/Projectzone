"use client";

import { useState } from "react";
import { cn } from "@/lib/utils";
import { BrandState, colorSwatches, fontOptions } from "@/lib/brand";
import { Check } from "lucide-react";

const tabs = ["Brand", "Content", "Business", "Website"] as const;
type Tab = (typeof tabs)[number];

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-3.5 py-2.5 text-[13.5px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[12.5px] font-medium text-paper-50/60">{label}</span>
      {children}
    </label>
  );
}

export function EditorPanel({
  brand,
  update,
}: {
  brand: BrandState;
  update: (patch: Partial<BrandState>) => void;
}) {
  const [tab, setTab] = useState<Tab>("Brand");

  return (
    <div className="rounded-2xl border border-line-dark bg-ink-900/40">
      <div className="flex items-center gap-1 border-b border-line-dark p-2">
        {tabs.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={cn(
              "flex-1 rounded-lg px-3 py-2 text-[12.5px] font-medium transition-colors",
              tab === t ? "bg-gold-500/10 text-gold-300" : "text-paper-50/50 hover:text-paper-50"
            )}
          >
            {t}
          </button>
        ))}
      </div>

      <div className="flex flex-col gap-5 p-5">
        {tab === "Brand" && (
          <>
            <Field label="Business Name">
              <input
                className={inputClass}
                value={brand.businessName}
                onChange={(e) => update({ businessName: e.target.value })}
              />
            </Field>
            <Field label="Primary Color">
              <div className="flex flex-wrap gap-2">
                {colorSwatches.map((c) => (
                  <button
                    key={c}
                    aria-label={`Set primary color ${c}`}
                    aria-pressed={brand.primaryColor === c}
                    onClick={() => update({ primaryColor: c })}
                    className={cn(
                      "flex size-8 items-center justify-center rounded-full border-2 transition-transform",
                      brand.primaryColor === c ? "scale-110 border-paper-50" : "border-transparent"
                    )}
                    style={{ background: c }}
                  >
                    {brand.primaryColor === c && <Check className="size-3.5 text-white" />}
                  </button>
                ))}
              </div>
            </Field>
            <Field label="Secondary Color">
              <div className="flex flex-wrap gap-2">
                {colorSwatches.map((c) => (
                  <button
                    key={c}
                    aria-label={`Set secondary color ${c}`}
                    aria-pressed={brand.secondaryColor === c}
                    onClick={() => update({ secondaryColor: c })}
                    className={cn(
                      "flex size-8 items-center justify-center rounded-full border-2 transition-transform",
                      brand.secondaryColor === c ? "scale-110 border-paper-50" : "border-transparent"
                    )}
                    style={{ background: c }}
                  >
                    {brand.secondaryColor === c && <Check className="size-3.5 text-white" />}
                  </button>
                ))}
              </div>
            </Field>
            <Field label="Typography">
              <div className="flex gap-2">
                {fontOptions.map((f) => (
                  <button
                    key={f}
                    onClick={() => update({ font: f })}
                    className={cn(
                      "flex-1 rounded-lg border px-3 py-2.5 text-[12.5px] font-medium transition-colors",
                      brand.font === f
                        ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                        : "border-line-dark text-paper-50/55 hover:border-line-dark-strong"
                    )}
                  >
                    {f}
                  </button>
                ))}
              </div>
            </Field>
          </>
        )}

        {tab === "Content" && (
          <>
            <Field label="Headline">
              <input
                className={inputClass}
                value={brand.headline}
                onChange={(e) => update({ headline: e.target.value })}
              />
            </Field>
            <Field label="Supporting Copy">
              <textarea
                className={cn(inputClass, "resize-none")}
                rows={3}
                value={brand.tagline}
                onChange={(e) => update({ tagline: e.target.value })}
              />
            </Field>
            <Field label="Services / Products">
              <div className="flex flex-col gap-2">
                {brand.services.map((s, i) => (
                  <input
                    key={i}
                    className={inputClass}
                    value={s}
                    onChange={(e) => {
                      const next = [...brand.services];
                      next[i] = e.target.value;
                      update({ services: next });
                    }}
                  />
                ))}
              </div>
            </Field>
          </>
        )}

        {tab === "Business" && (
          <>
            <Field label="Phone">
              <input
                className={inputClass}
                value={brand.phone}
                onChange={(e) => update({ phone: e.target.value })}
              />
            </Field>
            <Field label="Email">
              <input
                className={inputClass}
                value={brand.email}
                onChange={(e) => update({ email: e.target.value })}
              />
            </Field>
            <Field label="Address">
              <input
                className={inputClass}
                value={brand.address}
                onChange={(e) => update({ address: e.target.value })}
              />
            </Field>
            <Field label="Business Hours">
              <input
                className={inputClass}
                value={brand.hours}
                onChange={(e) => update({ hours: e.target.value })}
              />
            </Field>
          </>
        )}

        {tab === "Website" && (
          <>
            <Field label="Domain">
              <input
                className={inputClass}
                value={brand.domain}
                onChange={(e) => update({ domain: e.target.value })}
              />
            </Field>
            <Field label="Industry">
              <input
                className={inputClass}
                value={brand.industry}
                onChange={(e) => update({ industry: e.target.value })}
              />
            </Field>
            <div className="rounded-lg border border-line-dark bg-ink-950/40 p-4 text-[12.5px] leading-relaxed text-paper-50/50">
              Navigation, sections, footer and page structure carry over
              automatically from your chosen product — customize their
              content from the Content tab.
            </div>
          </>
        )}
      </div>
    </div>
  );
}
