"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform, useReducedMotion } from "framer-motion";
import { Code2, ShieldCheck, Layers, LifeBuoy, type LucideIcon } from "lucide-react";

const points: { title: string; description: string; icon: LucideIcon }[] = [
  {
    title: "Engineering-led delivery",
    description: "Every build is architected by engineers, not assembled from templates — so it holds up as you grow.",
    icon: Code2,
  },
  {
    title: "Security & infrastructure built-in",
    description: "SSL, hardened configuration, and monitoring ship with every project as standard, not an upsell.",
    icon: ShieldCheck,
  },
  {
    title: "One partner, full stack",
    description: "Website, application, mobile, domain, email, and security — coordinated by a single accountable team.",
    icon: Layers,
  },
  {
    title: "Support that doesn't end at launch",
    description: "Post-launch support windows and ongoing plans keep your systems maintained as your business evolves.",
    icon: LifeBuoy,
  },
];

function ParallaxCard({ point, index }: { point: (typeof points)[number]; index: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start 95%", "start 40%"] });

  const rawOffset = index % 2 === 0 ? 36 : 56;
  const y = useTransform(scrollYProgress, [0, 1], [reduceMotion ? 0 : rawOffset, 0]);
  const opacity = useTransform(scrollYProgress, [0, 1], [0, 1]);
  const scale = useTransform(scrollYProgress, [0, 1], [reduceMotion ? 1 : 0.94, 1]);
  const Icon = point.icon;

  return (
    <motion.div
      ref={ref}
      style={{ y, opacity, scale }}
      className="relative rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-lg shadow-black/10"
    >
      <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/20 to-emerald-400/20 text-violet-300">
        <Icon className="h-5 w-5" aria-hidden="true" />
      </span>
      <h3 className="font-display mt-4 text-lg font-semibold text-white">{point.title}</h3>
      <p className="mt-2 text-sm text-slate-400">{point.description}</p>
    </motion.div>
  );
}

/** Scroll-driven layered reveal: cards ease up and settle as they enter view. */
export function WhyUsParallax() {
  return (
    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      {points.map((point, i) => (
        <ParallaxCard key={point.title} point={point} index={i} />
      ))}
    </div>
  );
}
