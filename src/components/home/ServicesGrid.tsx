"use client";

import { ArrowUpRight } from "lucide-react";
import Link from "next/link";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { RevealGroup, revealItem } from "@/components/ui/Reveal";
import { motion } from "framer-motion";
import { services } from "@/lib/data/services";
import { Icon } from "@/lib/icon-map";

export function ServicesGrid() {
  return (
    <Section theme="light">
      <Container>
        <SectionHeading
          eyebrow="What we do"
          title="Six disciplines. One accountable team."
          description="From product engineering to AI systems, every discipline works from the same roadmap — so nothing gets lost between strategy and shipped code."
        />

        <RevealGroup className="mt-14 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service) => (
            <motion.div key={service.slug} variants={revealItem}>
              <Link
                href={`/services#${service.slug}`}
                className="focus-ring group flex h-full flex-col justify-between rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
              >
                <div>
                  <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-(--radius-md) bg-[linear-gradient(135deg,rgba(75,91,255,0.12),rgba(23,195,255,0.12))] text-(--color-accent)">
                    <Icon name={service.icon} className="h-5 w-5" />
                  </div>
                  <h3 className="text-lg font-medium text-(--color-ink)">{service.name}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-(--color-ink-muted)">{service.short}</p>
                </div>
                <div className="mt-6 flex items-center gap-1.5 text-sm font-medium text-(--color-accent-2) opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                  Learn more <ArrowUpRight className="h-3.5 w-3.5" />
                </div>
              </Link>
            </motion.div>
          ))}
        </RevealGroup>
      </Container>
    </Section>
  );
}
