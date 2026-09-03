"use client";

import { useEffect, useRef, useState } from "react";
import { motion, useInView, animate, useReducedMotion } from "framer-motion";

function parseNumeric(value: string): { prefix: string; number: number; suffix: string } | null {
  const match = value.match(/^(\D*)(\d+(?:\.\d+)?)(\D*)$/);
  if (!match) return null;
  const [, prefix, numberStr, suffix] = match;
  return { prefix, number: Number(numberStr), suffix };
}

export function StatCounter({ label, value }: { label: string; value: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const isInView = useInView(ref, { once: true, margin: "-60px" });
  const shouldReduceMotion = useReducedMotion();
  const parsed = parseNumeric(value);
  const [display, setDisplay] = useState(parsed ? `${parsed.prefix}0${parsed.suffix}` : value);

  useEffect(() => {
    if (!isInView || !parsed) return;
    if (shouldReduceMotion) {
      setDisplay(value);
      return;
    }
    const controls = animate(0, parsed.number, {
      duration: 1.4,
      ease: [0.16, 1, 0.3, 1],
      onUpdate(latest) {
        const rounded = Number.isInteger(parsed.number) ? Math.round(latest) : Math.round(latest * 10) / 10;
        setDisplay(`${parsed.prefix}${rounded}${parsed.suffix}`);
      },
    });
    return () => controls.stop();
  }, [isInView, parsed, shouldReduceMotion, value]);

  return (
    <motion.div
      ref={ref}
      initial={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: 16 }}
      animate={isInView ? { opacity: 1, y: 0 } : {}}
      transition={{ duration: shouldReduceMotion ? 0.15 : 0.6, ease: [0.16, 1, 0.3, 1] }}
      className="rounded-3xl border border-white/10 bg-white/5 px-6 py-8 text-center backdrop-blur-xl"
    >
      <div className="bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-3xl font-semibold tracking-tight text-transparent sm:text-4xl">
        {parsed ? display : value}
      </div>
      <div className="mt-2 text-sm text-neutral-400">{label}</div>
    </motion.div>
  );
}
