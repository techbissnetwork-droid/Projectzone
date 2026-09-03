"use client";

import { useEffect, useRef, useState } from "react";
import { motion } from "motion/react";
import { Compass, PenTool, Code2, Rocket, LineChart, type LucideProps } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { processSteps } from "@/lib/data/process";
import { cn } from "@/lib/utils/cn";

const icons: Record<string, React.ComponentType<LucideProps>> = {
  discover: Compass,
  design: PenTool,
  build: Code2,
  launch: Rocket,
  grow: LineChart,
};

export function ProcessPreview() {
  const [active, setActive] = useState(0);
  const [idle, setIdle] = useState(true);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!idle) return;
    const id = setInterval(() => {
      setActive((i) => (i + 1) % processSteps.length);
    }, 3400);
    return () => clearInterval(id);
  }, [idle]);

  const select = (i: number) => {
    setActive(i);
    setIdle(false);
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setIdle(true), 5000);
  };

  const step = processSteps[active];
  const Icon = icons[step.slug];

  return (
    <section className="py-24 md:py-32">
      <Container>
        <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <SectionHeading
            eyebrow="The TECHBISS Experience"
            title="A clear path from idea to a growing digital business."
            className="md:max-w-xl"
          />
          <Reveal delay={0.15}>
            <Button href="/process" variant="secondary">
              See the Full Process
            </Button>
          </Reveal>
        </div>

        <Reveal delay={0.1} className="mt-14">
          <div className="grid grid-cols-1 gap-1 sm:grid-cols-5">
            {processSteps.map((s, i) => (
              <button
                key={s.slug}
                type="button"
                onClick={() => select(i)}
                className="group relative flex flex-col gap-3 border-t-2 py-5 pr-4 text-left transition-colors duration-500"
                style={{
                  borderColor:
                    active === i ? "var(--color-gold)" : "var(--color-line-strong)",
                }}
              >
                <span
                  className={cn(
                    "text-eyebrow transition-colors duration-300",
                    active === i ? "text-gold-bright" : "text-paper-faint group-hover:text-paper-dim",
                  )}
                >
                  {s.index}
                </span>
                <span
                  className={cn(
                    "text-lg font-medium tracking-tight transition-colors duration-300",
                    active === i ? "text-paper" : "text-paper-dim group-hover:text-paper",
                  )}
                >
                  {s.title}
                </span>
              </button>
            ))}
          </div>
        </Reveal>

        <motion.div
          key={step.slug}
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
          className="mt-4 grid gap-8 rounded-2xl border border-line bg-ink-raised-2 p-8 sm:p-10 md:grid-cols-[auto_1fr_auto] md:items-center md:gap-10"
        >
          <span className="flex size-14 shrink-0 items-center justify-center rounded-full border border-gold/40 bg-gold-dim text-gold-bright">
            {Icon && <Icon className="size-6" aria-hidden />}
          </span>
          <div>
            <p className="text-h3 font-medium text-paper">{step.summary}</p>
            <p className="mt-3 max-w-2xl text-[0.95rem] leading-relaxed text-paper-dim">
              {step.description}
            </p>
          </div>
          <span className="text-eyebrow shrink-0 text-paper-faint md:text-right">
            {step.duration}
          </span>
        </motion.div>
      </Container>
    </section>
  );
}
