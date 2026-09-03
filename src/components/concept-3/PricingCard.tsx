import { CheckCircle2, Sparkles } from "lucide-react";
import type { PricingTier } from "@/lib/site-data";
import { Button } from "./Button";
import { cn } from "@/lib/cn";

export function PricingCard({ tier }: { tier: PricingTier }) {
  return (
    <div
      className={cn(
        "relative flex h-full flex-col rounded-2xl border p-7 shadow-lg transition-transform duration-300",
        tier.highlighted
          ? "border-violet-400/40 bg-gradient-to-b from-violet-500/[0.12] to-white/[0.03] shadow-violet-500/20 lg:-translate-y-3 lg:scale-[1.03]"
          : "border-white/10 bg-white/[0.03] shadow-black/10"
      )}
    >
      {tier.highlighted ? (
        <span className="absolute -top-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-gradient-to-r from-violet-500 to-blue-500 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow-lg shadow-violet-500/30">
          <Sparkles className="h-3 w-3" aria-hidden="true" />
          Recommended
        </span>
      ) : null}

      <h3 className="font-display text-xl font-semibold text-white">{tier.name}</h3>
      <p className="mt-1 text-sm text-slate-400">{tier.audience}</p>
      <p className="mt-4 text-sm leading-relaxed text-slate-300">{tier.description}</p>

      <ul className="mt-6 flex flex-1 flex-col gap-3">
        {tier.features.map((f) => (
          <li key={f} className="flex items-start gap-2.5 text-sm text-slate-300">
            <CheckCircle2
              className={cn("mt-0.5 h-4 w-4 shrink-0", tier.highlighted ? "text-violet-300" : "text-emerald-400")}
              aria-hidden="true"
            />
            <span>{f}</span>
          </li>
        ))}
      </ul>

      <Button
        href="/concept-3/get-started"
        variant={tier.highlighted ? "primary" : "secondary"}
        className="mt-8 w-full"
      >
        Get Started
      </Button>
    </div>
  );
}
