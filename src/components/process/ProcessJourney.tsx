"use client";

import { useRef, useState } from "react";
import {
  motion,
  AnimatePresence,
  useScroll,
  useTransform,
  useMotionValueEvent,
} from "motion/react";
import { Compass, PenTool, Code2, Rocket, LineChart, type LucideProps } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { processSteps } from "@/lib/data/process";
import { cn } from "@/lib/utils/cn";

const icons: Record<string, React.ComponentType<LucideProps>> = {
  discover: Compass,
  design: PenTool,
  build: Code2,
  launch: Rocket,
  grow: LineChart,
};

export function ProcessJourney() {
  const ref = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start start", "end end"],
  });

  const progressHeight = useTransform(scrollYProgress, [0, 1], ["0%", "100%"]);

  useMotionValueEvent(scrollYProgress, "change", (v) => {
    const idx = Math.min(
      processSteps.length - 1,
      Math.max(0, Math.floor(v * processSteps.length)),
    );
    setActive((prev) => (prev !== idx ? idx : prev));
  });

  const step = processSteps[active];
  const Icon = icons[step.slug];

  return (
    <section ref={ref} className="relative" style={{ height: `${processSteps.length * 95}vh` }}>
      <div className="sticky top-0 flex h-screen flex-col justify-center overflow-hidden border-y border-line py-20 md:py-24">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 flex items-center justify-center select-none"
        >
          <AnimatePresence mode="wait">
            <motion.span
              key={step.index}
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.6 }}
              className="text-[38vw] font-semibold leading-none text-paper/[0.03] sm:text-[26vw]"
            >
              {step.index}
            </motion.span>
          </AnimatePresence>
        </div>

        <Container className="relative grid gap-10 lg:grid-cols-[300px_1fr] lg:gap-16">
          <div className="flex flex-row gap-3 overflow-x-auto lg:flex-col lg:gap-1 lg:overflow-visible">
            {processSteps.map((s, i) => (
              <div
                key={s.slug}
                className={cn(
                  "flex shrink-0 items-center gap-4 border-b-2 py-3 pr-4 transition-all duration-500 lg:border-b-0 lg:border-l-2 lg:py-3 lg:pl-5",
                  i === active
                    ? "border-gold"
                    : "border-line-strong",
                )}
              >
                <span
                  className={cn(
                    "text-eyebrow transition-colors duration-500",
                    i === active ? "text-gold-bright" : "text-paper-faint",
                  )}
                >
                  {s.index}
                </span>
                <span
                  className={cn(
                    "text-sm font-medium transition-colors duration-500 lg:text-base",
                    i === active ? "text-paper" : "text-paper-faint",
                  )}
                >
                  {s.title}
                </span>
              </div>
            ))}

            <div className="relative mt-2 hidden h-1 overflow-hidden rounded-full bg-line lg:block">
              <motion.div
                className="absolute inset-y-0 left-0 rounded-full bg-gold"
                style={{ width: progressHeight }}
              />
            </div>
          </div>

          <AnimatePresence mode="wait">
            <motion.div
              key={step.slug}
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -16 }}
              transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
              className="flex flex-col gap-8"
            >
              <div className="flex items-center gap-5">
                <span className="flex size-14 shrink-0 items-center justify-center rounded-full border border-gold/40 bg-gold-dim text-gold-bright">
                  <Icon className="size-6" aria-hidden />
                </span>
                <div>
                  <h2 className="text-h2 font-medium text-paper">{step.title}</h2>
                  <p className="text-eyebrow mt-1 text-paper-faint">{step.duration}</p>
                </div>
              </div>

              <p className="text-lead max-w-2xl text-balance text-paper-dim">
                {step.description}
              </p>

              <div className="grid gap-8 sm:grid-cols-2">
                <div>
                  <span className="text-eyebrow text-paper-faint">What Happens</span>
                  <ul className="mt-4 flex flex-col gap-3">
                    {step.activities.map((a) => (
                      <li key={a} className="flex items-start gap-2.5 text-sm text-paper-dim">
                        <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-gold" />
                        {a}
                      </li>
                    ))}
                  </ul>
                </div>
                <div>
                  <span className="text-eyebrow text-paper-faint">You Receive</span>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {step.deliverables.map((d) => (
                      <span
                        key={d}
                        className="rounded-full border border-line-strong px-3 py-1.5 text-xs text-paper-dim"
                      >
                        {d}
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            </motion.div>
          </AnimatePresence>
        </Container>
      </div>
    </section>
  );
}
