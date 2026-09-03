"use client";

import { motion } from "framer-motion";
import { Check } from "lucide-react";
import type { PricingTier } from "@/lib/site-data";
import { Button } from "@/components/concept-1/Button";
import { cn } from "@/lib/cn";

export function PricingCard({ tier }: { tier: PricingTier }) {
  return (
    <motion.div
      whileHover={{ y: -6 }}
      transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
      className={cn(
        "relative flex h-full flex-col rounded-3xl border p-8 backdrop-blur-xl",
        tier.highlighted
          ? "border-white/20 bg-white/[0.07] shadow-[0_0_0_1px_rgba(255,255,255,0.06),0_30px_80px_-30px_rgba(99,102,241,0.55)]"
          : "border-white/10 bg-white/5"
      )}
    >
      {tier.highlighted ? (
        <span className="absolute -top-3 left-8 rounded-full bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-neutral-950">
          Most Popular
        </span>
      ) : null}
      <h3 className="text-xl font-semibold tracking-tight text-neutral-50">{tier.name}</h3>
      <p className="mt-1 text-sm text-neutral-400">{tier.audience}</p>
      <p className="mt-5 text-sm leading-relaxed text-neutral-300">{tier.description}</p>
      <ul className="mt-7 flex-1 space-y-3">
        {tier.features.map((feature) => (
          <li key={feature} className="flex items-start gap-3 text-sm text-neutral-300">
            <span
              aria-hidden="true"
              className="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-gradient-to-r from-cyan-400/20 via-indigo-400/20 to-fuchsia-500/20"
            >
              <Check className="h-3.5 w-3.5 text-neutral-100" />
            </span>
            {feature}
          </li>
        ))}
      </ul>
      <Button
        href="/concept-1/get-started"
        variant={tier.highlighted ? "primary" : "secondary"}
        className="mt-8 w-full"
      >
        Get Started
      </Button>
    </motion.div>
  );
}
