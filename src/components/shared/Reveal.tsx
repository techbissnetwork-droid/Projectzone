"use client";

import { motion, type Variants } from "motion/react";
import { fadeUp, viewportOnce } from "@/lib/motion";

export function Reveal({
  children,
  variants = fadeUp,
  delay = 0,
  className,
  as = "div",
}: {
  children: React.ReactNode;
  variants?: Variants;
  delay?: number;
  className?: string;
  as?: "div" | "span" | "li";
}) {
  const MotionTag = motion[as];
  return (
    <MotionTag
      className={className}
      initial="hidden"
      whileInView="visible"
      viewport={viewportOnce}
      variants={variants}
      transition={{ delay }}
    >
      {children}
    </MotionTag>
  );
}
