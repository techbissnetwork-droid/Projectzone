"use client";

import { useState } from "react";
import { Check, Rocket, PartyPopper } from "lucide-react";
import { cn } from "@/lib/utils";
import { Product } from "@/lib/data/marketplace";
import { Button } from "@/components/ui/button";

const initialItems = [
  { key: "purchased", label: "Product purchased", locked: true },
  { key: "brand", label: "Brand configured" },
  { key: "content", label: "Content added" },
  { key: "domain", label: "Domain connected" },
  { key: "hosting", label: "Hosting configured" },
  { key: "ssl", label: "SSL enabled" },
  { key: "email", label: "Business email configured" },
  { key: "payments", label: "Payment system connected" },
  { key: "seo", label: "SEO configured" },
  { key: "mobile", label: "Mobile checked" },
];

export function LaunchChecklist({ product }: { product: Product }) {
  const [checked, setChecked] = useState<Record<string, boolean>>({ purchased: true, brand: true });
  const [launched, setLaunched] = useState(false);

  const total = initialItems.length;
  const done = initialItems.filter((i) => checked[i.key]).length;
  const allDone = done === total;

  if (launched) {
    return (
      <div className="flex flex-col items-center gap-6 rounded-3xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-10 text-center sm:p-16">
        <span className="flex size-16 items-center justify-center rounded-full bg-gold-400 text-ink-950">
          <PartyPopper className="size-7" strokeWidth={2.25} />
        </span>
        <div>
          <h2 className="text-[28px] font-medium text-paper-50">You&rsquo;re live.</h2>
          <p className="mt-3 max-w-sm text-[14px] leading-relaxed text-paper-50/55">
            {product.name} is now live on {product.name.toLowerCase().replace(/\s+/g, "")}.com.
            TECHBISS will keep monitoring uptime, security and performance
            from here.
          </p>
        </div>
        <div className="flex flex-wrap items-center justify-center gap-3">
          <Button href={`/marketplace/product/${product.slug}/preview`} arrow>
            View Live Site
          </Button>
          <Button href="/dashboard" variant="ghost">
            Go to Dashboard
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center justify-between text-[13px] text-paper-50/50">
        <span>Launch readiness</span>
        <span>{done} of {total} complete</span>
      </div>
      <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-ink-900">
        <div
          className="h-full rounded-full bg-gold-400 transition-all duration-500"
          style={{ width: `${(done / total) * 100}%` }}
        />
      </div>

      <div className="mt-8 grid gap-2.5">
        {initialItems.map((item) => {
          const isChecked = checked[item.key];
          return (
            <button
              key={item.key}
              disabled={item.locked}
              onClick={() => setChecked((c) => ({ ...c, [item.key]: !c[item.key] }))}
              className={cn(
                "flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-colors",
                isChecked
                  ? "border-gold-500/30 bg-gold-500/[0.06]"
                  : "border-line-dark bg-ink-900/30 hover:border-line-dark-strong",
                item.locked && "cursor-default opacity-80"
              )}
            >
              <span
                className={cn(
                  "flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors",
                  isChecked ? "border-gold-400 bg-gold-400 text-ink-950" : "border-line-dark-strong"
                )}
              >
                {isChecked && <Check className="size-3" strokeWidth={3} />}
              </span>
              <span className={cn("text-[13.5px] font-medium", isChecked ? "text-paper-50" : "text-paper-50/60")}>
                {item.label}
              </span>
              {item.locked && (
                <span className="ml-auto text-[11px] uppercase tracking-wide text-paper-50/30">Done</span>
              )}
            </button>
          );
        })}
      </div>

      <div className="mt-8 flex items-center justify-between rounded-2xl border border-line-dark bg-ink-900/40 p-6">
        <div>
          <div className="text-[15px] font-medium text-paper-50">
            {allDone ? "Ready to launch" : "Complete the checklist to launch"}
          </div>
          <p className="mt-1 text-[12.5px] text-paper-50/45">
            {allDone
              ? "Everything is configured — you're ready to go live."
              : `${total - done} step${total - done !== 1 ? "s" : ""} remaining.`}
          </p>
        </div>
        <Button onClick={() => setLaunched(true)} disabled={!allDone} arrow>
          <Rocket className="size-4" />
          Launch Website
        </Button>
      </div>
    </div>
  );
}
