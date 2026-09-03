"use client";

import { useRef, useState } from "react";
import { motion, useMotionValueEvent, useScroll, useTransform, type MotionValue } from "framer-motion";
import { Check } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import { cn } from "@/lib/utils";
import { processStages } from "@/lib/data/process";

const TOTAL = processStages.length;

export function ProcessJourney() {
  const wrapRef = useRef<HTMLDivElement>(null);
  const sectionRefs = useRef<(HTMLDivElement | null)[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);

  const { scrollYProgress } = useScroll({
    target: wrapRef,
    offset: ["start start", "end end"],
  });

  useMotionValueEvent(scrollYProgress, "change", (v) => {
    const idx = Math.min(TOTAL - 1, Math.max(0, Math.floor(v * TOTAL)));
    setActiveIndex(idx);
  });

  const scrollToStage = (i: number) => {
    sectionRefs.current[i]?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  return (
    <section ref={wrapRef} className="relative">
      {/* overall progress bar */}
      <div className="sticky top-0 z-30 h-[3px] w-full bg-[var(--color-border)]">
        <motion.div
          style={{ scaleX: scrollYProgress }}
          className="h-full origin-left bg-[var(--color-accent)]"
        />
      </div>

      {/* mobile stage-pill tracker */}
      <div className="sticky top-[3px] z-20 -mx-6 flex gap-2 overflow-x-auto border-b border-[var(--color-border)] bg-[var(--color-bg)]/95 px-6 py-3 backdrop-blur-md scrollbar-none sm:-mx-8 sm:px-8 lg:hidden">
        {processStages.map((stage, i) => (
          <button
            key={stage.index}
            type="button"
            onClick={() => scrollToStage(i)}
            aria-current={activeIndex === i}
            className={cn(
              "shrink-0 whitespace-nowrap rounded-full border px-3.5 py-1.5 font-mono-label text-[11px] uppercase transition-colors duration-300",
              activeIndex === i
                ? "border-transparent bg-[var(--color-accent)] text-white"
                : "border-[var(--color-border-strong)] text-[var(--color-ink-faint)]",
            )}
          >
            {stage.index} · {stage.title}
          </button>
        ))}
      </div>

      <Container>
        <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr] lg:gap-16">
          {/* desktop sticky rail */}
          <div className="hidden lg:sticky lg:top-32 lg:block lg:h-fit lg:py-6">
            <div className="flex flex-col gap-0.5">
              {processStages.map((stage, i) => (
                <RailItem
                  key={stage.index}
                  stage={stage}
                  index={i}
                  progress={scrollYProgress}
                  active={activeIndex === i}
                  onClick={() => scrollToStage(i)}
                />
              ))}
            </div>
          </div>

          {/* stage content */}
          <div className="flex flex-col py-10 lg:py-6">
            {processStages.map((stage, i) => (
              <div
                key={stage.index}
                ref={(el) => {
                  sectionRefs.current[i] = el;
                }}
                className="flex min-h-[62vh] flex-col justify-center border-b border-[var(--color-border)] py-14 last:border-b-0 sm:min-h-[70vh] sm:py-20"
              >
                <Reveal>
                  <span className="font-mono-label text-[13px] text-[var(--color-accent-ink)]">
                    {stage.index} / {String(TOTAL).padStart(2, "0")}
                  </span>
                  <h2 className="mt-5 max-w-[16ch] text-balance text-[30px] font-medium leading-[1.08] tracking-[-0.02em] sm:text-[42px] md:text-[48px]">
                    {stage.title}
                  </h2>
                  <p className="mt-5 max-w-[54ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
                    {stage.description}
                  </p>

                  <ul className="mt-9 flex flex-col gap-3.5 sm:mt-10">
                    {stage.details.map((detail) => (
                      <li key={detail} className="flex items-start gap-3">
                        <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border border-[var(--color-border-strong)] bg-[var(--color-surface)]">
                          <Check className="size-3 text-[var(--color-accent-ink)]" strokeWidth={2.5} />
                        </span>
                        <span className="text-[14.5px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[15.5px]">
                          {detail}
                        </span>
                      </li>
                    ))}
                  </ul>
                </Reveal>
              </div>
            ))}
          </div>
        </div>
      </Container>
    </section>
  );
}

function RailItem({
  stage,
  index,
  progress,
  active,
  onClick,
}: {
  stage: (typeof processStages)[number];
  index: number;
  progress: MotionValue<number>;
  active: boolean;
  onClick: () => void;
}) {
  const start = index / TOTAL;
  const end = (index + 1) / TOTAL;
  const mid = (start + end) / 2;

  const opacity = useTransform(progress, [start, mid, end], [0.4, 1, 0.4]);
  const scale = useTransform(progress, [start, mid, end], [0.96, 1, 0.96]);
  const color = useTransform(
    progress,
    [start, mid, end],
    ["var(--color-ink-faint)", "var(--color-ink)", "var(--color-ink-faint)"],
  );

  return (
    <motion.button
      type="button"
      onClick={onClick}
      style={{ opacity }}
      aria-current={active}
      className={cn(
        "group flex items-center gap-3.5 rounded-xl px-3.5 py-3 text-left transition-colors duration-300",
        active ? "bg-[var(--color-surface)]" : "hover:bg-[var(--color-surface)]/60",
      )}
    >
      <motion.span
        style={{ scale, color }}
        className="origin-left font-mono-label text-[12px]"
      >
        {stage.index}
      </motion.span>
      <motion.span style={{ scale, color }} className="origin-left text-[15px] font-medium">
        {stage.title}
      </motion.span>
    </motion.button>
  );
}
