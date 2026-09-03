import { Check } from "lucide-react";
import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";
import { LinkButton } from "@/components/concept-2/Button";
import type { PricingTier } from "@/lib/site-data";

export function PricingColumn({ tier }: { tier: PricingTier }) {
  return (
    <div
      className={cn(
        "flex h-full flex-col border p-8 sm:p-10",
        tier.highlighted ? "border-neutral-900 bg-neutral-900 text-white" : "border-neutral-200 bg-white text-neutral-900"
      )}
    >
      <p className={cn("text-xs uppercase tracking-[0.2em]", tier.highlighted ? "text-neutral-400" : "text-neutral-500")}>
        {tier.audience}
      </p>
      <h3 className={cn(fontSerif, "mt-4 text-4xl")}>{tier.name}</h3>
      <p className={cn("mt-4 text-sm leading-relaxed", tier.highlighted ? "text-neutral-300" : "text-neutral-600")}>
        {tier.description}
      </p>
      <ul className="mt-8 flex-1 space-y-4">
        {tier.features.map((f) => (
          <li key={f} className="flex items-start gap-3 text-sm">
            <Check className={cn("mt-0.5 h-4 w-4 shrink-0", tier.highlighted ? "text-white" : "text-neutral-900")} aria-hidden="true" />
            <span className={tier.highlighted ? "text-neutral-200" : "text-neutral-700"}>{f}</span>
          </li>
        ))}
      </ul>
      <LinkButton
        href="/concept-2/get-started"
        className={cn("mt-10 w-full", tier.highlighted && "bg-white text-neutral-900 hover:opacity-90")}
      >
        Start with {tier.name}
      </LinkButton>
    </div>
  );
}
