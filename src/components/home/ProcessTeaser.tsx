"use client";

import { motion } from "framer-motion";
import { ArrowRight } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Button } from "@/components/ui/Button";
import { RevealGroup, revealItem } from "@/components/ui/Reveal";
import { processSteps } from "@/lib/data/process";

export function ProcessTeaser() {
  return (
    <Section theme="light">
      <Container>
        <SectionHeading
          eyebrow="How we work"
          title="A disciplined process, built for momentum."
          description="Five phases. Full visibility at every stage. No black boxes between kickoff and launch."
        />

        <RevealGroup className="mt-14 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {processSteps.map((step) => (
            <motion.div
              key={step.number}
              variants={revealItem}
              className="group relative rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
            >
              <span className="text-sm font-medium text-(--color-accent-2)">{step.number}</span>
              <h3 className="mt-3 text-base font-medium text-(--color-ink)">{step.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-(--color-ink-muted)">{step.description}</p>
            </motion.div>
          ))}
        </RevealGroup>

        <div className="mt-10 flex justify-center">
          <Button href="/process" variant="outline" icon={<ArrowRight className="h-4 w-4" />}>
            See our full process
          </Button>
        </div>
      </Container>
    </Section>
  );
}
