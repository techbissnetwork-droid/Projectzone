"use client";

import { motion } from "framer-motion";
import { Check } from "lucide-react";
import { Icon } from "@/lib/icon-map";
import { Badge } from "@/components/ui/Badge";
import type { Service } from "@/lib/types";

export function ServiceRow({ service, index }: { service: Service; index: number }) {
  const reversed = index % 2 === 1;

  return (
    <motion.div
      id={service.slug}
      initial={{ opacity: 0, y: 24 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-100px" }}
      transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
      className="scroll-mt-24 border-t border-(--color-border) py-14 first:border-t-0 sm:py-16"
    >
      <div className={`grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-8 ${reversed ? "" : ""}`}>
        <div className={`lg:col-span-5 ${reversed ? "lg:order-2" : ""}`}>
          <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-(--radius-md) bg-[linear-gradient(135deg,rgba(75,91,255,0.12),rgba(23,195,255,0.12))] text-(--color-accent)">
            <Icon name={service.icon} className="h-5.5 w-5.5" />
          </div>
          <h3 className="text-2xl font-medium tracking-tight text-(--color-ink) sm:text-[1.75rem]">{service.name}</h3>
          <p className="mt-3 max-w-md text-sm leading-relaxed text-(--color-ink-muted) sm:text-base">
            {service.description}
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            {service.capabilities.map((c) => (
              <Badge key={c} variant="outline">
                {c}
              </Badge>
            ))}
          </div>
        </div>

        <div className={`lg:col-span-7 ${reversed ? "lg:order-1" : ""}`}>
          <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-7">
            <p className="mb-4 text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">
              What you get
            </p>
            <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {service.deliverables.map((d) => (
                <li key={d} className="flex items-start gap-2.5 text-sm text-(--color-ink)">
                  <Check className="mt-0.5 h-4 w-4 shrink-0 text-(--color-accent-2)" />
                  {d}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
