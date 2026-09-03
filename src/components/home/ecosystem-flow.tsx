"use client";

import { useRef } from "react";
import { motion, useMotionValue, useSpring, useTransform } from "framer-motion";

const LAYERS = [
  { label: "Business", accent: "#8f9bb3" },
  { label: "Domain", accent: "#5eb3ff" },
  { label: "Website", accent: "#5170ff" },
  { label: "App", accent: "#7c8cff" },
  { label: "Hosting", accent: "#4fd1c5" },
  { label: "Email", accent: "#f2b84b" },
  { label: "Security", accent: "#3ecf8e" },
  { label: "Payments", accent: "#ff8a65" },
  { label: "Automation", accent: "#b98af0" },
  { label: "Growth", accent: "#c9a463" },
];

export function EcosystemFlow() {
  const ref = useRef<HTMLDivElement>(null);
  const mx = useMotionValue(0);
  const my = useMotionValue(0);
  const rx = useSpring(useTransform(my, [-40, 40], [4, -4]), { stiffness: 120, damping: 20 });
  const ry = useSpring(useTransform(mx, [-40, 40], [-4, 4]), { stiffness: 120, damping: 20 });
  const shiftX = useSpring(useTransform(mx, [-40, 40], [-10, 10]), { stiffness: 100, damping: 20 });

  function handleMouseMove(e: React.MouseEvent<HTMLDivElement>) {
    const rect = ref.current?.getBoundingClientRect();
    if (!rect) return;
    mx.set(e.clientX - rect.left - rect.width / 2);
    my.set(e.clientY - rect.top - rect.height / 2);
  }

  function handleMouseLeave() {
    mx.set(0);
    my.set(0);
  }

  return (
    <div
      ref={ref}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className="relative mx-auto mt-14 w-full max-w-[1100px] [perspective:1400px] sm:mt-20"
    >
      <motion.div
        style={{ rotateX: rx, rotateY: ry }}
        className="relative"
      >
        <div className="scrollbar-none relative flex snap-x snap-mandatory items-center gap-3 overflow-x-auto px-6 pb-4 sm:justify-center sm:gap-4 sm:overflow-visible sm:px-0">
          {LAYERS.map((layer, i) => (
            <motion.div
              key={layer.label}
              style={{ x: shiftX }}
              className="group relative flex shrink-0 snap-center flex-col items-center"
            >
              {i > 0 && (
                <span
                  aria-hidden
                  className="absolute top-1/2 right-full hidden h-px w-4 -translate-y-1/2 bg-gradient-to-r from-transparent to-[var(--color-border-strong)] sm:block"
                />
              )}
              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.4 + i * 0.05, ease: [0.16, 1, 0.3, 1] }}
                whileHover={{ y: -6 }}
                className="relative flex h-16 w-[92px] flex-col items-center justify-center gap-1.5 rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)]/80 backdrop-blur-sm transition-colors duration-300 hover:border-[var(--color-border-strong)] sm:h-20 sm:w-[104px]"
              >
                <span
                  className="size-1.5 rounded-full transition-shadow duration-300 group-hover:shadow-[0_0_12px_2px_var(--dot-color)]"
                  style={{ backgroundColor: layer.accent, ["--dot-color" as string]: layer.accent }}
                />
                <span className="px-1 text-center text-[11px] font-medium text-[var(--color-ink-muted)] group-hover:text-[var(--color-ink)] sm:text-[12px]">
                  {layer.label}
                </span>
              </motion.div>
            </motion.div>
          ))}
        </div>
        <div
          aria-hidden
          className="pointer-events-none absolute inset-x-8 top-1/2 hidden h-px -translate-y-1/2 bg-[var(--color-border)] sm:block"
          style={{ zIndex: -1 }}
        />
      </motion.div>
    </div>
  );
}
