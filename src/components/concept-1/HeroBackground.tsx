"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform, useReducedMotion } from "framer-motion";

/**
 * Layered cinematic hero background: a faint fixed grid, two soft radial
 * glows, and a subtle scroll-linked parallax on the glow layer. Sits behind
 * hero content at negative z-index / absolute inset-0 — never intercepts
 * text legibility since it stays fully behind and low-opacity.
 */
export function HeroBackground() {
  const ref = useRef<HTMLDivElement>(null);
  const shouldReduceMotion = useReducedMotion();
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start start", "end start"],
  });
  const y = useTransform(scrollYProgress, [0, 1], [0, shouldReduceMotion ? 0 : 120]);

  return (
    <div ref={ref} aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <div
        className="absolute inset-0 opacity-[0.15]"
        style={{
          backgroundImage:
            "linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)",
          backgroundSize: "64px 64px",
          maskImage: "radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 90%)",
          WebkitMaskImage: "radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 90%)",
        }}
      />
      <motion.div style={{ y }} className="absolute inset-0">
        <div
          className="absolute -top-32 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full opacity-40 blur-3xl"
          style={{
            background:
              "radial-gradient(circle, rgba(34,211,238,0.5) 0%, rgba(34,211,238,0) 70%)",
          }}
        />
        <div
          className="absolute top-24 right-0 h-[28rem] w-[28rem] rounded-full opacity-30 blur-3xl"
          style={{
            background:
              "radial-gradient(circle, rgba(217,70,239,0.45) 0%, rgba(217,70,239,0) 70%)",
          }}
        />
        <div
          className="absolute top-0 left-0 h-[24rem] w-[24rem] rounded-full opacity-25 blur-3xl"
          style={{
            background:
              "radial-gradient(circle, rgba(129,140,248,0.45) 0%, rgba(129,140,248,0) 70%)",
          }}
        />
      </motion.div>
    </div>
  );
}
