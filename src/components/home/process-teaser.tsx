"use client";

import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { motion } from "framer-motion";

const steps = [
  { n: "01", title: "Discover", detail: "Understand the business, customers and objectives." },
  { n: "02", title: "Choose", detail: "A ready-made product, or a custom architecture." },
  { n: "03", title: "Build", detail: "Develop the website, application and infrastructure." },
  { n: "04", title: "Brand", detail: "Transform the experience around your identity." },
  { n: "05", title: "Launch", detail: "Domain, hosting, SSL, email — all configured." },
  { n: "06", title: "Grow", detail: "Maintain, optimize and continuously improve." },
];

export function ProcessTeaser() {
  return (
    <section className="border-b border-line-dark py-24 sm:py-32">
      <Container wide>
        <Reveal className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <div>
            <Eyebrow>Our Process</Eyebrow>
            <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
              One clear path, from idea to a growing business.
            </h2>
          </div>
          <Button href="/process" arrow variant="ghost" className="shrink-0">
            See the Full Process
          </Button>
        </Reveal>

        <RevealGroup className="mt-14 grid gap-px overflow-hidden rounded-2xl border border-line-dark bg-line-dark sm:grid-cols-2 lg:grid-cols-6">
          {steps.map((step) => (
            <motion.div
              key={step.n}
              variants={revealItem}
              className="bg-ink-950 p-6"
            >
              <div className="font-mono-label text-[11px] text-gold-400">{step.n}</div>
              <div className="mt-4 text-[16px] font-medium text-paper-50">{step.title}</div>
              <p className="mt-2 text-[12.5px] leading-relaxed text-paper-50/45">{step.detail}</p>
            </motion.div>
          ))}
        </RevealGroup>
      </Container>
    </section>
  );
}
