"use client";

import { motion } from "motion/react";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { AnimatedHeadline } from "@/components/shared/AnimatedHeadline";
import { site } from "@/lib/data/site";

export function FinalCTA() {
  return (
    <section className="relative overflow-hidden py-28 md:py-40">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10"
        style={{
          background:
            "radial-gradient(50% 60% at 50% 100%, rgba(201,168,118,0.14), transparent), radial-gradient(35% 40% at 15% 20%, rgba(127,166,217,0.08), transparent)",
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10 opacity-40"
        style={{
          backgroundImage:
            "linear-gradient(var(--color-line) 1px, transparent 1px), linear-gradient(90deg, var(--color-line) 1px, transparent 1px)",
          backgroundSize: "64px 64px",
          maskImage: "radial-gradient(60% 60% at 50% 50%, black, transparent)",
        }}
      />

      <Container className="flex flex-col items-center gap-8 text-center">
        <AnimatedHeadline
          text="Ready to take your business online?"
          as="h2"
          className="text-h1 max-w-3xl text-balance font-medium text-paper"
        />
        <motion.p
          initial={{ opacity: 0, y: 16 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.7, delay: 0.3, ease: [0.16, 1, 0.3, 1] }}
          className="text-lead max-w-lg text-balance text-paper-dim"
        >
          Tell us what you&apos;re building. We&apos;ll figure out the
          technology.
        </motion.p>
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.7, delay: 0.45, ease: [0.16, 1, 0.3, 1] }}
          className="flex flex-col items-center gap-4 pt-2 sm:flex-row"
        >
          <Button href="/contact" size="lg">
            Start Your Project
          </Button>
          <Button href={`mailto:${site.email}`} variant="secondary" size="lg" icon={false}>
            Talk to TECHBISS
          </Button>
        </motion.div>
      </Container>
    </section>
  );
}
