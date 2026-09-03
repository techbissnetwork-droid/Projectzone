"use client";

import { motion } from "motion/react";
import { maskReveal, staggerContainer, viewportOnce } from "@/lib/motion";
import { cn } from "@/lib/utils/cn";

export function AnimatedHeadline({
  text,
  as = "h1",
  className,
  wordClassName,
  delay = 0,
  stagger = 0.05,
}: {
  text: string;
  as?: "h1" | "h2" | "span";
  className?: string;
  wordClassName?: string;
  delay?: number;
  stagger?: number;
}) {
  const words = text.split(" ");
  const MotionTag = motion[as];

  return (
    <MotionTag
      className={className}
      variants={staggerContainer(stagger, delay)}
      initial="hidden"
      whileInView="visible"
      viewport={viewportOnce}
    >
      {words.map((word, i) => (
        <span
          key={i}
          className="inline-block overflow-hidden pb-[0.12em] align-top"
        >
          <motion.span
            variants={maskReveal}
            className={cn("inline-block", wordClassName)}
          >
            {word}
            {i < words.length - 1 ? " " : ""}
          </motion.span>
        </span>
      ))}
    </MotionTag>
  );
}
