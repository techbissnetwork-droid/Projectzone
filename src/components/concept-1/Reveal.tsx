"use client";

import type { ReactNode } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/cn";

type RevealTag = "div" | "span" | "li";

export function Reveal({
  children,
  className,
  delay = 0,
  as = "div",
}: {
  children: ReactNode;
  className?: string;
  delay?: number;
  as?: RevealTag;
}) {
  const shouldReduceMotion = useReducedMotion();

  const initial = shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: 24 };
  const whileInView = shouldReduceMotion ? { opacity: 1 } : { opacity: 1, y: 0 };
  const transition = shouldReduceMotion
    ? { duration: 0.15 }
    : { duration: 0.6, delay, ease: [0.16, 1, 0.3, 1] as const };
  const viewport = { once: true, margin: "-80px" } as const;

  if (as === "span") {
    return (
      <motion.span
        className={cn(className)}
        initial={initial}
        whileInView={whileInView}
        viewport={viewport}
        transition={transition}
      >
        {children}
      </motion.span>
    );
  }

  if (as === "li") {
    return (
      <motion.li
        className={cn(className)}
        initial={initial}
        whileInView={whileInView}
        viewport={viewport}
        transition={transition}
      >
        {children}
      </motion.li>
    );
  }

  return (
    <motion.div
      className={cn(className)}
      initial={initial}
      whileInView={whileInView}
      viewport={viewport}
      transition={transition}
    >
      {children}
    </motion.div>
  );
}
