"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform, type MotionValue } from "framer-motion";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";

const OFFLINE_ITEMS = [
  "Physical Store",
  "Paper Records",
  "Phone Orders",
  "Manual Processes",
  "Limited Reach",
  "Disconnected Systems",
];

const DIGITAL_ITEMS = [
  "Website",
  "Mobile App",
  "Online Payments",
  "Business Email",
  "Cloud Infrastructure",
  "Automation",
  "Analytics",
  "Global Customers",
];

const STAGES = ["Offline", "Digital", "Online", "Growing"];

export function Transformation() {
  const wrapRef = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({
    target: wrapRef,
    offset: ["start start", "end end"],
  });

  const offlineOpacity = useTransform(scrollYProgress, [0, 0.28, 0.42], [1, 1, 0]);
  const offlineBlur = useTransform(scrollYProgress, [0.2, 0.42], [0, 10]);
  const offlineScale = useTransform(scrollYProgress, [0, 0.42], [1, 0.92]);

  const digitalOpacity = useTransform(scrollYProgress, [0.38, 0.55, 1], [0, 1, 1]);
  const digitalBlur = useTransform(scrollYProgress, [0.38, 0.55], [10, 0]);
  const digitalScale = useTransform(scrollYProgress, [0.38, 0.6], [0.94, 1]);

  const bgOpacity = useTransform(scrollYProgress, [0, 0.5, 1], [0, 1, 1]);
  const barScale = useTransform(scrollYProgress, [0, 1], [0, 1]);

  return (
    <section ref={wrapRef} className="relative h-[320vh]">
      <div className="sticky top-0 flex h-screen flex-col justify-center overflow-hidden">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0"
          style={{ background: "radial-gradient(80% 60% at 50% 40%, rgba(81,112,255,0.12), transparent)" }}
        />
        <motion.div
          aria-hidden
          style={{ opacity: bgOpacity }}
          className="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(81,112,255,0.06),transparent)]"
        />

        <Container className="relative">
          <div className="mx-auto max-w-[720px] text-center">
            <Eyebrow className="justify-center">The Transformation</Eyebrow>
            <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.08] tracking-[-0.02em] sm:text-[48px] md:text-[56px]">
              Every business starts somewhere.{" "}
              <span className="font-serif-display italic text-[var(--color-ink-muted)]">
                Yours can go further.
              </span>
            </h2>
          </div>

          <div className="mx-auto mt-10 flex max-w-[520px] items-center justify-between">
            {STAGES.map((stage, i) => {
              const start = i / STAGES.length;
              const end = (i + 1) / STAGES.length;
              return (
                <StageLabel key={stage} label={stage} progress={scrollYProgress} start={start} end={end} />
              );
            })}
          </div>

          <div className="relative mx-auto mt-14 h-[300px] max-w-[880px] sm:h-[340px]">
            <motion.div
              style={{ opacity: offlineOpacity, scale: offlineScale, filter: useTransform(offlineBlur, (v) => `blur(${v}px)`) }}
              className="absolute inset-0 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
              {OFFLINE_ITEMS.map((item, i) => (
                <div
                  key={item}
                  style={{ rotate: `${(i % 2 === 0 ? -1 : 1) * (1 + i)}deg` }}
                  className="flex items-center justify-center rounded-xl border border-dashed border-[var(--color-border-strong)] bg-[var(--color-surface)]/60 px-3 py-6 text-center text-[13px] text-[var(--color-ink-faint)] sm:text-[14px]"
                >
                  {item}
                </div>
              ))}
            </motion.div>

            <motion.div
              style={{ opacity: digitalOpacity, scale: digitalScale, filter: useTransform(digitalBlur, (v) => `blur(${v}px)`) }}
              className="absolute inset-0 grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
              {DIGITAL_ITEMS.map((item, i) => (
                <div
                  key={item}
                  className="relative flex items-center justify-center rounded-xl border border-[var(--color-accent-soft)] bg-[linear-gradient(180deg,rgba(81,112,255,0.10),rgba(81,112,255,0.02))] px-3 py-6 text-center text-[13px] font-medium text-[var(--color-ink)] shadow-[0_0_0_1px_rgba(81,112,255,0.08)] sm:text-[14px]"
                >
                  <span className="absolute left-1/2 top-0 size-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[var(--color-accent)]" />
                  {item}
                </div>
              ))}
            </motion.div>
          </div>

          <div className="mx-auto mt-12 h-px max-w-[520px] bg-[var(--color-border)]">
            <motion.div
              style={{ scaleX: barScale }}
              className="h-full origin-left bg-[var(--color-accent)]"
            />
          </div>
        </Container>
      </div>
    </section>
  );
}

function StageLabel({
  label,
  progress,
  start,
  end,
}: {
  label: string;
  progress: MotionValue<number>;
  start: number;
  end: number;
}) {
  const opacity = useTransform(progress, [start, start + (end - start) * 0.3], [0.35, 1]);
  const color = useTransform(
    progress,
    [start, start + (end - start) * 0.3],
    ["var(--color-ink-faint)", "var(--color-ink)"],
  );
  return (
    <motion.span
      style={{ opacity, color }}
      className="font-mono-label text-[11px] uppercase sm:text-[12px]"
    >
      {label}
    </motion.span>
  );
}
