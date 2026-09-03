"use client";

import { motion } from "framer-motion";
import { Container } from "@/components/ui/container";
import { Button } from "@/components/ui/button";
import { Eyebrow } from "@/components/ui/eyebrow";
import { EcosystemFlow } from "@/components/home/ecosystem-flow";

const EASE = [0.16, 1, 0.3, 1] as const;

const LINE_1 = "YOUR BUSINESS.";
const LINE_2 = "BUILT FOR THE DIGITAL WORLD.";

function AnimatedLine({ text, delayBase }: { text: string; delayBase: number }) {
  const words = text.split(" ");
  return (
    <span className="block overflow-hidden">
      <span className="block">
        {words.map((word, i) => (
          <span key={i} className="mr-[0.28em] inline-block overflow-hidden last:mr-0 align-top">
            <motion.span
              initial={{ y: "110%" }}
              animate={{ y: "0%" }}
              transition={{ duration: 0.9, delay: delayBase + i * 0.06, ease: EASE }}
              className="inline-block"
            >
              {word}
            </motion.span>
          </span>
        ))}
      </span>
    </span>
  );
}

export function Hero() {
  return (
    <section className="relative overflow-hidden pt-40 pb-20 sm:pt-48 sm:pb-28">
      <div
        aria-hidden
        className="bg-grid pointer-events-none absolute inset-0 opacity-[0.35] mask-fade-b"
      />
      <div
        aria-hidden
        className="pointer-events-none absolute left-1/2 top-[-10%] h-[560px] w-[900px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.16),transparent)] blur-2xl"
      />

      <Container className="relative">
        <div className="flex flex-col items-center text-center">
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, ease: EASE }}
          >
            <Eyebrow>Digital Transformation Platform</Eyebrow>
          </motion.div>

          <h1 className="mt-7 max-w-[15ch] text-[13vw] font-medium leading-[0.98] tracking-[-0.03em] text-balance sm:text-[64px] md:text-[84px] lg:text-[104px]">
            <AnimatedLine text={LINE_1} delayBase={0.15} />
            <AnimatedLine text={LINE_2} delayBase={0.35} />
          </h1>

          <motion.p
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.75, ease: EASE }}
            className="mt-8 max-w-[52ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]"
          >
            From offline operations to a complete online ecosystem — TECHBISS builds,
            launches and powers everything your business needs to grow online.
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.9, ease: EASE }}
            className="mt-10 flex flex-col items-center gap-3 sm:flex-row"
          >
            <Button href="/contact" size="lg">
              Start Your Digital Journey
            </Button>
            <Button href="/services" variant="secondary" size="lg">
              Explore What We Build
            </Button>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.7, delay: 1.05, ease: EASE }}
          >
            <a
              href="/marketplace"
              className="mt-5 inline-block text-[13px] font-medium text-[var(--color-ink-faint)] underline decoration-[var(--color-border-strong)] underline-offset-4 transition-colors hover:text-[var(--color-ink-muted)]"
            >
              Browse Ready-Made Themes →
            </a>
          </motion.div>
        </div>

        <EcosystemFlow />
      </Container>
    </section>
  );
}
