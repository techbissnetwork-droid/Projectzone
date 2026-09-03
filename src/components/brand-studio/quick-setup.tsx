"use client";

import { useState } from "react";
import { Check, Sparkles } from "lucide-react";
import { BrandState, colorSwatches } from "@/lib/brand";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-4 py-3 text-[14px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

export function QuickSetup({
  initial,
  onComplete,
}: {
  initial: BrandState;
  onComplete: (state: BrandState) => void;
}) {
  const [form, setForm] = useState(initial);

  return (
    <div className="mx-auto max-w-xl rounded-3xl border border-line-dark bg-ink-900/40 p-8 sm:p-10">
      <span className="flex size-11 items-center justify-center rounded-xl border border-gold-500/30 bg-gold-500/10 text-gold-400">
        <Sparkles className="size-5" strokeWidth={1.75} />
      </span>
      <h2 className="mt-6 text-[24px] font-medium tracking-tight text-paper-50">Quick Brand Setup</h2>
      <p className="mt-2 text-[14px] leading-relaxed text-paper-50/55">
        Answer a few questions and TECHBISS will apply your identity across
        the whole product automatically. You can refine everything after.
      </p>

      <div className="mt-8 flex flex-col gap-5">
        <div className="grid gap-5 sm:grid-cols-2">
          <label className="flex flex-col gap-1.5">
            <span className="text-[12.5px] font-medium text-paper-50/60">Business Name</span>
            <input
              className={inputClass}
              value={form.businessName}
              onChange={(e) => setForm({ ...form, businessName: e.target.value })}
            />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[12.5px] font-medium text-paper-50/60">Industry</span>
            <input
              className={inputClass}
              value={form.industry}
              onChange={(e) => setForm({ ...form, industry: e.target.value })}
            />
          </label>
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-[12.5px] font-medium text-paper-50/60">Primary Color</span>
          <div className="flex flex-wrap gap-2">
            {colorSwatches.map((c) => (
              <button
                key={c}
                type="button"
                aria-label={`Set primary color ${c}`}
                aria-pressed={form.primaryColor === c}
                onClick={() => setForm({ ...form, primaryColor: c })}
                className={cn(
                  "flex size-9 items-center justify-center rounded-full border-2 transition-transform",
                  form.primaryColor === c ? "scale-110 border-paper-50" : "border-transparent"
                )}
                style={{ background: c }}
              >
                {form.primaryColor === c && <Check className="size-4 text-white" />}
              </button>
            ))}
          </div>
        </label>

        <div className="grid gap-5 sm:grid-cols-2">
          <label className="flex flex-col gap-1.5">
            <span className="text-[12.5px] font-medium text-paper-50/60">Phone</span>
            <input
              className={inputClass}
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
            />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[12.5px] font-medium text-paper-50/60">Email</span>
            <input
              className={inputClass}
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
          </label>
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-[12.5px] font-medium text-paper-50/60">Domain</span>
          <input
            className={inputClass}
            value={form.domain}
            onChange={(e) => setForm({ ...form, domain: e.target.value })}
          />
        </label>

        <Button onClick={() => onComplete(form)} size="lg" className="mt-2 w-full justify-center" arrow>
          Apply My Brand
        </Button>
      </div>
    </div>
  );
}
