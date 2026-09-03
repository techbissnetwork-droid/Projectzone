"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform, type MotionValue } from "motion/react";
import {
  Globe,
  Smartphone,
  CreditCard,
  Mail,
  Server,
  Workflow,
  BarChart3,
  Users,
  type LucideProps,
} from "lucide-react";
import { Container } from "@/components/ui/Container";
import { Eyebrow } from "@/components/ui/Eyebrow";

const offlinePoints = [
  "Physical store, one location",
  "Paper records & filing cabinets",
  "Orders taken by phone",
  "Manual, person-dependent operations",
  "Reach limited to who walks in",
  "Systems that don't talk to each other",
];

const onlinePoints: { label: string; icon: React.ComponentType<LucideProps> }[] = [
  { label: "Website", icon: Globe },
  { label: "Mobile application", icon: Smartphone },
  { label: "Online payments", icon: CreditCard },
  { label: "Business email", icon: Mail },
  { label: "Cloud infrastructure", icon: Server },
  { label: "Automated systems", icon: Workflow },
  { label: "Real-time analytics", icon: BarChart3 },
  { label: "Customers, everywhere", icon: Users },
];

const stages = ["Offline", "Digital", "Online", "Growing"];

function OnlineItem({
  point,
  index,
  total,
  scrollYProgress,
}: {
  point: (typeof onlinePoints)[number];
  index: number;
  total: number;
  scrollYProgress: MotionValue<number>;
}) {
  const start = 0.18 + (index / total) * 0.6;
  const end = start + 0.6 / total + 0.1;
  const opacity = useTransform(scrollYProgress, [start, end], [0.18, 1]);
  const y = useTransform(scrollYProgress, [start, end], [14, 0]);
  const Icon = point.icon;
  return (
    <motion.li
      style={{ opacity, y }}
      className="flex items-center gap-2.5 rounded-lg border border-line bg-ink-raised-3/70 px-3.5 py-3 text-sm text-paper"
    >
      <Icon className="size-4 shrink-0 text-gold-bright" aria-hidden />
      {point.label}
    </motion.li>
  );
}

export function OfflineToOnline() {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start start", "end end"],
  });

  const offlineOpacity = useTransform(scrollYProgress, [0, 0.5, 0.75], [1, 0.55, 0.32]);
  const offlineScale = useTransform(scrollYProgress, [0, 0.75], [1, 0.93]);
  const lineWidth = useTransform(scrollYProgress, [0, 1], ["0%", "100%"]);
  const dotX = useTransform(scrollYProgress, [0, 1], ["0%", "100%"]);
  const bgOpacity = useTransform(scrollYProgress, [0, 1], [0.05, 0.16]);

  return (
    <section ref={ref} className="relative h-[320vh]">
      <div className="sticky top-0 flex h-screen flex-col justify-center overflow-hidden py-24">
        <motion.div
          aria-hidden
          style={{ opacity: bgOpacity }}
          className="pointer-events-none absolute inset-0"
        >
          <div
            className="absolute inset-0"
            style={{
              backgroundImage:
                "linear-gradient(var(--color-line) 1px, transparent 1px), linear-gradient(90deg, var(--color-line) 1px, transparent 1px)",
              backgroundSize: "56px 56px",
            }}
          />
          <div
            className="absolute inset-0"
            style={{
              background:
                "radial-gradient(46% 40% at 50% 50%, rgba(201,168,118,0.14), transparent)",
            }}
          />
        </motion.div>

        <Container className="relative flex flex-col gap-12 md:gap-16">
          <div className="flex flex-col items-center gap-6 text-center">
            <Eyebrow tone="gold">From Offline to Online</Eyebrow>
            <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
              The same business. A completely different reach.
            </h2>

            <div className="mt-2 w-full max-w-md">
              <div className="relative h-px w-full bg-line-strong">
                <motion.div
                  style={{ width: lineWidth }}
                  className="absolute inset-y-0 left-0 bg-gold"
                />
                <motion.div
                  style={{ left: dotX }}
                  className="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-bright shadow-[0_0_12px_2px_rgba(227,197,148,0.55)]"
                />
              </div>
              <div className="mt-4 flex justify-between">
                {stages.map((s) => (
                  <span key={s} className="text-eyebrow text-paper-faint">
                    {s}
                  </span>
                ))}
              </div>
            </div>
          </div>

          <div className="grid gap-6 md:grid-cols-2 md:gap-8">
            <motion.div
              style={{ opacity: offlineOpacity, scale: offlineScale }}
              className="rounded-2xl border border-line bg-ink-raised-2 p-7 sm:p-9"
            >
              <Eyebrow tone="neutral">Where it starts</Eyebrow>
              <h3 className="text-h3 mt-4 font-medium text-paper">Offline</h3>
              <ul className="mt-6 flex flex-col gap-3.5">
                {offlinePoints.map((p) => (
                  <li
                    key={p}
                    className="flex items-center gap-3 text-[0.95rem] text-paper-dim"
                  >
                    <span className="size-1.5 shrink-0 rounded-full bg-paper-faint" />
                    {p}
                  </li>
                ))}
              </ul>
            </motion.div>

            <div className="relative overflow-hidden rounded-2xl border border-gold/25 bg-gradient-to-b from-ink-raised-2 to-ink-raised p-7 sm:p-9">
              <div
                aria-hidden
                className="pointer-events-none absolute -right-16 -top-16 size-56 rounded-full bg-gold/10 blur-3xl"
              />
              <Eyebrow tone="gold">Where you&apos;re going</Eyebrow>
              <h3 className="text-h3 relative mt-4 font-medium text-paper">
                Online — and growing
              </h3>
              <ul className="relative mt-6 grid grid-cols-1 gap-3 xs:grid-cols-2">
                {onlinePoints.map((point, i) => (
                  <OnlineItem
                    key={point.label}
                    point={point}
                    index={i}
                    total={onlinePoints.length}
                    scrollYProgress={scrollYProgress}
                  />
                ))}
              </ul>
            </div>
          </div>
        </Container>
      </div>
    </section>
  );
}
