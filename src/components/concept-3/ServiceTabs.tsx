"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowUpRight, CheckCircle2 } from "lucide-react";
import type { Service } from "@/lib/site-data";
import { getIcon } from "./icon-map";
import { cn } from "@/lib/cn";

type Category = "Web & Apps" | "Business Systems" | "Infrastructure & Security" | "Ongoing";

const categoryBySlug: Record<string, Category> = {
  "website-development": "Web & Apps",
  "web-application-development": "Web & Apps",
  "mobile-app-development": "Web & Apps",
  "business-digitization": "Business Systems",
  "business-email": "Business Systems",
  "domain-hosting": "Infrastructure & Security",
  "ssl-security": "Infrastructure & Security",
  "custom-solutions": "Ongoing",
  "technical-support": "Ongoing",
};

const categoryAccent: Record<Category, string> = {
  "Web & Apps": "from-violet-500 to-fuchsia-500",
  "Business Systems": "from-blue-500 to-violet-500",
  "Infrastructure & Security": "from-emerald-400 to-blue-500",
  Ongoing: "from-fuchsia-500 to-blue-500",
};

/**
 * Interactive service catalog: category pills filter a grid of widget cards
 * (real client state, not decorative). Cards reveal a couple of extra
 * feature bullets on hover/focus. This is the site's central "interactive
 * service visualization."
 */
export function ServiceTabs({ services }: { services: Service[] }) {
  const categories = useMemo(() => {
    const set = new Set<Category>();
    services.forEach((s) => set.add(categoryBySlug[s.slug] ?? "Ongoing"));
    return Array.from(set);
  }, [services]);

  const [filter, setFilter] = useState<Category | "All">("All");
  const [hovered, setHovered] = useState<string | null>(null);

  const filtered = filter === "All" ? services : services.filter((s) => (categoryBySlug[s.slug] ?? "Ongoing") === filter);

  return (
    <div>
      <div role="tablist" aria-label="Filter services by category" className="flex flex-wrap gap-2">
        <button
          type="button"
          role="tab"
          aria-selected={filter === "All"}
          onClick={() => setFilter("All")}
          className={cn(
            "min-h-[44px] rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
            filter === "All"
              ? "border-white/20 bg-white text-[#0b0c14]"
              : "border-white/10 bg-white/[0.03] text-slate-400 hover:text-white"
          )}
        >
          All Services
        </button>
        {categories.map((c) => (
          <button
            key={c}
            type="button"
            role="tab"
            aria-selected={filter === c}
            onClick={() => setFilter(c)}
            className={cn(
              "min-h-[44px] rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
              filter === c
                ? "border-white/20 bg-white text-[#0b0c14]"
                : "border-white/10 bg-white/[0.03] text-slate-400 hover:text-white"
            )}
          >
            {c}
          </button>
        ))}
      </div>

      <motion.div layout className="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((service) => {
          const Icon = getIcon(service.icon);
          const category = categoryBySlug[service.slug] ?? "Ongoing";
          const href = service.hasDetailPage ? `/concept-3/services/${service.slug}` : "/concept-3/contact";
          const isHovered = hovered === service.slug;

          return (
            <motion.div key={service.slug} layout initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }}>
              <Link
                href={href}
                onMouseEnter={() => setHovered(service.slug)}
                onMouseLeave={() => setHovered((v) => (v === service.slug ? null : v))}
                onFocus={() => setHovered(service.slug)}
                onBlur={() => setHovered((v) => (v === service.slug ? null : v))}
                className="group block h-full rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-lg shadow-black/10 transition-colors hover:border-white/20 hover:bg-white/[0.06] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
              >
                <div className="flex items-start justify-between gap-3">
                  <span className={cn("flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white", categoryAccent[category])}>
                    <Icon className="h-5 w-5" aria-hidden="true" />
                  </span>
                  <ArrowUpRight className="h-4 w-4 text-slate-500 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-white" aria-hidden="true" />
                </div>

                <span className="mt-4 inline-block rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                  {category}
                </span>

                <h3 className="font-display mt-3 text-lg font-semibold text-white">{service.title}</h3>
                <p className="mt-2 text-sm text-slate-400">{service.shortDescription}</p>

                <motion.div
                  initial={false}
                  animate={{ height: isHovered ? "auto" : 0, opacity: isHovered ? 1 : 0 }}
                  transition={{ duration: 0.25, ease: "easeOut" }}
                  className="overflow-hidden"
                >
                  <ul className="mt-4 flex flex-col gap-2 border-t border-white/10 pt-4">
                    {service.features.slice(0, 2).map((f) => (
                      <li key={f} className="flex items-start gap-2 text-xs text-slate-300">
                        <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400" aria-hidden="true" />
                        <span>{f}</span>
                      </li>
                    ))}
                  </ul>
                </motion.div>

                {!service.hasDetailPage ? (
                  <span className="mt-4 inline-block text-xs font-medium text-violet-300">Talk to us about this &rarr;</span>
                ) : null}
              </Link>
            </motion.div>
          );
        })}
      </motion.div>
    </div>
  );
}
