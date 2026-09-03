"use client";

import type { ReactNode } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/cn";

type Tag = "div" | "span" | "li" | "p";

/**
 * Minimal scroll reveal: a short fade + a few pixels of rise. Deliberately
 * restrained — this concept's motion language is precision, not spectacle.
 * Respects prefers-reduced-motion by dropping the transform entirely.
 */
export function Reveal({
  children,
  className,
  delay = 0,
  as = "div",
  y = 8,
}: {
  children: ReactNode;
  className?: string;
  delay?: number;
  as?: Tag;
  y?: number;
}) {
  const reduced = useReducedMotion();
  const Component = motion[as];

  if (reduced) {
    return (
      <Component
        className={cn(className)}
        initial={{ opacity: 0 }}
        whileInView={{ opacity: 1 }}
        viewport={{ once: true, margin: "-80px" }}
        transition={{ duration: 0.2 }}
      >
        {children}
      </Component>
    );
  }

  return (
    <Component
      className={cn(className)}
      initial={{ opacity: 0, y }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: 0.5, delay, ease: [0.16, 1, 0.3, 1] }}
    >
      {children}
    </Component>
  );
}

/**
 * Word-by-word stagger reveal, for display headlines only. Each word is
 * clipped inside an overflow-hidden mask and rises into place. The full
 * string is exposed to assistive tech via aria-label on the wrapper, with
 * every animated word hidden from the accessibility tree.
 */
export function RevealWords({
  text,
  className,
  wordClassName,
  delay = 0,
}: {
  text: string;
  className?: string;
  wordClassName?: string;
  delay?: number;
}) {
  const reduced = useReducedMotion();
  const words = text.split(" ");

  if (reduced) {
    return (
      <span className={cn(className)} aria-label={text}>
        {text}
      </span>
    );
  }

  return (
    <span className={cn(className)} aria-label={text}>
      {words.map((word, i) => (
        <span key={i} aria-hidden="true" className="inline-block overflow-hidden pb-[0.1em] align-top">
          <motion.span
            className={cn("inline-block", wordClassName)}
            initial={{ y: "110%", opacity: 0 }}
            whileInView={{ y: "0%", opacity: 1 }}
            viewport={{ once: true, margin: "-80px" }}
            transition={{ duration: 0.6, delay: delay + i * 0.06, ease: [0.16, 1, 0.3, 1] }}
          >
            {word}
            {i < words.length - 1 ? " " : ""}
          </motion.span>
        </span>
      ))}
    </span>
  );
}
