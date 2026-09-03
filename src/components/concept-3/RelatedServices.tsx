import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import type { Service } from "@/lib/site-data";
import { getIcon } from "./icon-map";
import { RevealGroup, RevealItem } from "./Reveal";

export function RelatedServices({ services }: { services: Service[] }) {
  return (
    <RevealGroup className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      {services.map((service) => {
        const Icon = getIcon(service.icon);
        const href = service.hasDetailPage ? `/concept-3/services/${service.slug}` : "/concept-3/contact";
        return (
          <RevealItem key={service.slug}>
            <Link
              href={href}
              className="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.03] p-6 transition-colors hover:border-white/20 hover:bg-white/[0.06] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
            >
              <div className="flex items-center justify-between">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
                  <Icon className="h-4 w-4" aria-hidden="true" />
                </span>
                <ArrowUpRight className="h-4 w-4 text-slate-500 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-white" aria-hidden="true" />
              </div>
              <h3 className="font-display mt-4 text-base font-semibold text-white">{service.title}</h3>
              <p className="mt-2 text-sm text-slate-400">{service.shortDescription}</p>
            </Link>
          </RevealItem>
        );
      })}
    </RevealGroup>
  );
}
