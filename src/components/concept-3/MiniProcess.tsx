import { ArrowRight } from "lucide-react";
import { RevealGroup, RevealItem } from "./Reveal";

export type MiniProcessStep = { title: string; description: string };

/** Compact 3–4 step "how it works" strip used on individual service pages. */
export function MiniProcess({ steps }: { steps: MiniProcessStep[] }) {
  return (
    <RevealGroup className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:flex lg:items-stretch">
      {steps.map((step, i) => (
        <RevealItem key={step.title} className="flex flex-1 items-stretch gap-4">
          <div className="flex-1 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <span className="font-display text-2xl font-bold text-violet-300">{String(i + 1).padStart(2, "0")}</span>
            <h3 className="font-display mt-2 text-base font-semibold text-white">{step.title}</h3>
            <p className="mt-1.5 text-sm text-slate-400">{step.description}</p>
          </div>
          {i < steps.length - 1 ? (
            <span className="hidden shrink-0 items-center text-slate-600 lg:flex" aria-hidden="true">
              <ArrowRight className="h-5 w-5" />
            </span>
          ) : null}
        </RevealItem>
      ))}
    </RevealGroup>
  );
}
