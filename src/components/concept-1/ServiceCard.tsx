"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import {
  ArrowUpRight,
  Globe,
  LayoutDashboard,
  Smartphone,
  Building2,
  Server,
  ShieldCheck,
  Mail,
  Sparkles,
  LifeBuoy,
  type LucideIcon,
} from "lucide-react";
import type { Service } from "@/lib/site-data";
import { cn } from "@/lib/cn";

const iconMap: Record<string, LucideIcon> = {
  Globe,
  LayoutDashboard,
  Smartphone,
  Building2,
  Server,
  ShieldCheck,
  Mail,
  Sparkles,
  LifeBuoy,
};

export function ServiceCard({
  service,
  className,
}: {
  service: Service;
  className?: string;
}) {
  const Icon = iconMap[service.icon] ?? Sparkles;
  const href = service.hasDetailPage
    ? `/concept-1/services/${service.slug}`
    : "/concept-1/contact";

  return (
    <motion.div
      whileHover="hover"
      initial="rest"
      animate="rest"
      className={cn("group relative h-full", className)}
    >
      <motion.div
        aria-hidden="true"
        variants={{
          rest: { opacity: 0 },
          hover: { opacity: 1 },
        }}
        transition={{ duration: 0.4 }}
        className="pointer-events-none absolute -inset-px rounded-3xl bg-gradient-to-r from-cyan-400/60 via-indigo-400/60 to-fuchsia-500/60 blur-[2px]"
      />
      <motion.div
        variants={{ rest: { y: 0 }, hover: { y: -4 } }}
        transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        className="relative flex h-full flex-col rounded-3xl border border-white/10 bg-neutral-950/80 p-7 backdrop-blur-xl"
      >
        <motion.div
          variants={{ hover: { scale: 1.08, rotate: -4 } }}
          transition={{ duration: 0.35 }}
          className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/5"
        >
          <Icon className="h-5 w-5 text-neutral-100" aria-hidden="true" />
        </motion.div>
        <h3 className="text-lg font-semibold tracking-tight text-neutral-50">
          {service.title}
        </h3>
        <p className="mt-3 flex-1 text-sm leading-relaxed text-neutral-400">
          {service.shortDescription}
        </p>
        <Link
          href={href}
          className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded-full"
        >
          <span className="bg-gradient-to-r from-cyan-300 to-fuchsia-300 bg-clip-text text-transparent">
            {service.hasDetailPage ? "Explore service" : "Get in touch"}
          </span>
          <ArrowUpRight className="h-4 w-4 text-neutral-300 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" aria-hidden="true" />
        </Link>
      </motion.div>
    </motion.div>
  );
}
