"use client";

import { motion } from "motion/react";
import type { VisualTheme } from "@/lib/data/caseStudies";
import { cn } from "@/lib/utils/cn";

function GridTheme({ tone }: { tone: string }) {
  return (
    <div className="grid size-full grid-cols-6 grid-rows-4 gap-1.5 p-6">
      {Array.from({ length: 24 }).map((_, i) => {
        const highlight = [3, 8, 14, 19].includes(i);
        return (
          <motion.div
            key={i}
            className={cn("rounded-[3px]", highlight ? tone : "bg-line")}
            animate={highlight ? { opacity: [0.4, 1, 0.4] } : {}}
            transition={{ duration: 2.4, repeat: Infinity, delay: i * 0.08 }}
          />
        );
      })}
    </div>
  );
}

function FlowTheme({ stroke }: { stroke: string }) {
  const paths = [
    "M0,40 C60,10 120,70 200,30",
    "M0,70 C70,100 130,20 200,60",
    "M0,20 C50,50 150,10 200,45",
  ];
  return (
    <svg viewBox="0 0 200 100" className="size-full p-6" preserveAspectRatio="none">
      {paths.map((d, i) => (
        <motion.path
          key={d}
          d={d}
          fill="none"
          stroke={stroke}
          strokeWidth={1.2}
          strokeLinecap="round"
          strokeDasharray="6 8"
          opacity={0.55 - i * 0.12}
          animate={{ strokeDashoffset: [0, -28] }}
          transition={{ duration: 3, repeat: Infinity, ease: "linear", delay: i * 0.3 }}
        />
      ))}
    </svg>
  );
}

function PulseTheme({ ring }: { ring: string }) {
  return (
    <div className="relative flex size-full items-center justify-center">
      {[0, 1, 2].map((i) => (
        <motion.span
          key={i}
          className={cn(
            "absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border",
            ring,
          )}
          initial={{ width: 20, height: 20, opacity: 0.7 }}
          animate={{ width: 160, height: 160, opacity: 0 }}
          transition={{ duration: 3, repeat: Infinity, delay: i * 1, ease: "easeOut" }}
        />
      ))}
      <span className={cn("relative size-3 rounded-full", ring.replace("border-", "bg-"))} />
    </div>
  );
}

function OrbitTheme({ dot, ring }: { dot: string; ring: string }) {
  return (
    <div className="relative flex size-full items-center justify-center">
      <span className={cn("absolute left-1/2 top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full", dot)} />
      {[46, 70].map((r, i) => (
        <motion.div
          key={r}
          className={cn(
            "absolute left-1/2 top-1/2 rounded-full border",
            ring,
          )}
          style={{ width: r * 2, height: r * 2, marginLeft: -r, marginTop: -r }}
          animate={{ rotate: 360 }}
          transition={{ duration: 14 + i * 8, repeat: Infinity, ease: "linear" }}
        >
          <span className={cn("absolute -top-1.5 left-1/2 size-3 -translate-x-1/2 rounded-full", dot)} />
        </motion.div>
      ))}
    </div>
  );
}

function WavesTheme({ bar }: { bar: string }) {
  return (
    <div className="flex size-full items-center justify-center gap-1.5 px-8">
      {Array.from({ length: 14 }).map((_, i) => (
        <motion.span
          key={i}
          className={cn("w-1.5 rounded-full", bar)}
          animate={{ height: ["20%", "80%", "35%", "60%", "20%"] }}
          transition={{
            duration: 2.6,
            repeat: Infinity,
            delay: i * 0.09,
            ease: "easeInOut",
          }}
        />
      ))}
    </div>
  );
}

function TerminalTheme({ text }: { text: string }) {
  const lines = [
    "$ techbiss deploy --env production",
    "✓ build compiled in 4.2s",
    "✓ infrastructure provisioned",
    "✓ payments verified",
    `${text} → live`,
  ];
  return (
    <div className="flex size-full flex-col justify-center gap-2 px-7 font-mono text-[0.7rem] text-paper-dim">
      {lines.map((l, i) => (
        <motion.p
          key={l}
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: i * 0.25, duration: 0.4 }}
          className={i === lines.length - 1 ? "text-signal-bright" : ""}
        >
          {l}
        </motion.p>
      ))}
    </div>
  );
}

export function CaseStudyVisual({
  theme,
  color,
  label,
  className,
}: {
  theme: VisualTheme;
  color: "gold" | "signal";
  label: string;
  className?: string;
}) {
  const isGold = color === "gold";
  const tone = isGold ? "bg-gold" : "bg-signal";
  const stroke = isGold ? "#e3c594" : "#9dbee8";
  const ring = isGold ? "border-gold/50" : "border-signal/50";
  const dot = isGold ? "bg-gold-bright" : "bg-signal-bright";
  const bar = isGold ? "bg-gold/60" : "bg-signal/60";

  return (
    <div
      className={cn(
        "relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-xl border border-line bg-ink-raised-2 sm:aspect-[16/11]",
        className,
      )}
    >
      <div
        aria-hidden
        className="absolute inset-0"
        style={{
          background: isGold
            ? "radial-gradient(60% 55% at 30% 20%, rgba(201,168,118,0.10), transparent)"
            : "radial-gradient(60% 55% at 30% 20%, rgba(127,166,217,0.10), transparent)",
        }}
      />
      {theme === "grid" && <GridTheme tone={tone} />}
      {theme === "flow" && <FlowTheme stroke={stroke} />}
      {theme === "pulse" && <PulseTheme ring={ring} />}
      {theme === "orbit" && <OrbitTheme dot={dot} ring={ring} />}
      {theme === "waves" && <WavesTheme bar={bar} />}
      {theme === "terminal" && <TerminalTheme text={label} />}
    </div>
  );
}
