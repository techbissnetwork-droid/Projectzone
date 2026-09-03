"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";
import type { Faq } from "@/lib/site-data";

export function FaqAccordion({ faqs, className }: { faqs: Faq[]; className?: string }) {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <div className={cn("border-t border-neutral-200", className)}>
      {faqs.map((faq, i) => {
        const isOpen = openIndex === i;
        const panelId = `faq-panel-${i}`;
        const buttonId = `faq-button-${i}`;
        return (
          <div key={faq.question} className="border-b border-neutral-200">
            <h3>
              <button
                id={buttonId}
                type="button"
                aria-expanded={isOpen}
                aria-controls={panelId}
                onClick={() => setOpenIndex(isOpen ? null : i)}
                className="flex w-full items-center justify-between gap-6 py-6 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
              >
                <span className={cn(fontSerif, "text-lg text-neutral-900 sm:text-xl")}>{faq.question}</span>
                <Plus
                  aria-hidden="true"
                  className={cn(
                    "h-5 w-5 shrink-0 text-neutral-500 transition-transform duration-300",
                    isOpen && "rotate-45"
                  )}
                />
              </button>
            </h3>
            <div
              id={panelId}
              role="region"
              aria-labelledby={buttonId}
              className={cn(
                "grid transition-[grid-template-rows] duration-300 ease-in-out",
                isOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]"
              )}
            >
              <div className="overflow-hidden">
                <p className="max-w-2xl pb-6 text-sm leading-relaxed text-neutral-600 sm:text-base">{faq.answer}</p>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
