"use client";

import { motion } from "motion/react";
import { ChevronDown } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { AnimatedHeadline } from "@/components/shared/AnimatedHeadline";
import { EcosystemVisual } from "./EcosystemVisual";
import { fadeUp } from "@/lib/motion";

export function Hero() {
  return (
    <section className="relative overflow-hidden pb-20 pt-36 sm:pt-40 md:pb-28 md:pt-48">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[900px]"
        style={{
          background:
            "radial-gradient(60% 44% at 50% 0%, rgba(201,168,118,0.10), transparent), radial-gradient(38% 30% at 84% 12%, rgba(127,166,217,0.08), transparent)",
        }}
      />

      <Container className="flex flex-col items-center gap-8 text-center">
        <motion.div
          initial="hidden"
          animate="visible"
          variants={fadeUp}
        >
          <Eyebrow tone="gold">The Complete Digital Ecosystem</Eyebrow>
        </motion.div>

        <AnimatedHeadline
          text="Your Business. Built for the Digital World."
          className="text-display max-w-4xl text-balance font-medium text-paper"
          delay={0.1}
        />

        <motion.p
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.7, ease: [0.16, 1, 0.3, 1] }}
          className="text-lead max-w-2xl text-balance text-paper-dim"
        >
          From offline operations to a complete online ecosystem — TECHBISS
          builds, launches and powers everything your business needs to grow
          online.
        </motion.p>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.85, ease: [0.16, 1, 0.3, 1] }}
          className="flex flex-col items-center gap-4 pt-2 sm:flex-row"
        >
          <Button href="/contact" size="lg">
            Start Your Digital Journey
          </Button>
          <Button href="/services" variant="secondary" size="lg">
            Explore What We Build
          </Button>
        </motion.div>
      </Container>

      <motion.div
        initial={{ opacity: 0, y: 32 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 1, delay: 1.05, ease: [0.16, 1, 0.3, 1] }}
        className="mt-20 md:mt-28"
      >
        <Container>
          <EcosystemVisual />
        </Container>
      </motion.div>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 1, delay: 1.6 }}
        className="mt-14 hidden justify-center md:flex"
      >
        <div className="flex flex-col items-center gap-2 text-paper-faint">
          <span className="text-eyebrow">Scroll</span>
          <motion.div
            animate={{ y: [0, 6, 0] }}
            transition={{ duration: 1.8, repeat: Infinity, ease: "easeInOut" }}
          >
            <ChevronDown className="size-4" aria-hidden />
          </motion.div>
        </div>
      </motion.div>
    </section>
  );
}
