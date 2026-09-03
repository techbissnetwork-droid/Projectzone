"use client";

import { Star } from "lucide-react";
import { motion } from "framer-motion";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { RevealGroup, revealItem } from "@/components/ui/Reveal";
import { testimonials } from "@/lib/data/testimonials";

export function Testimonials() {
  return (
    <Section>
      <Container>
        <SectionHeading eyebrow="Client outcomes" title="What leaders say after we ship." />

        <RevealGroup className="mt-14 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {testimonials.map((t) => (
            <motion.figure
              key={t.author}
              variants={revealItem}
              className="flex flex-col justify-between rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6"
            >
              <div>
                <div className="mb-4 flex gap-0.5">
                  {Array.from({ length: t.rating }).map((_, i) => (
                    <Star key={i} className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                  ))}
                </div>
                <blockquote className="text-sm leading-relaxed text-(--color-ink)">&ldquo;{t.quote}&rdquo;</blockquote>
              </div>
              <figcaption className="mt-6 flex items-center gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[linear-gradient(135deg,#4b5bff,#17c3ff)] text-xs font-medium text-white">
                  {t.author
                    .split(" ")
                    .map((n) => n[0])
                    .join("")}
                </span>
                <div>
                  <p className="text-sm font-medium text-(--color-ink)">{t.author}</p>
                  <p className="text-xs text-(--color-ink-faint)">
                    {t.role}, {t.company}
                  </p>
                </div>
              </figcaption>
            </motion.figure>
          ))}
        </RevealGroup>
      </Container>
    </Section>
  );
}
