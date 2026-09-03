import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/ui/Reveal";
import { Accordion } from "@/components/ui/Accordion";
import { ServiceRow } from "@/components/services/ServiceRow";
import { CtaBanner } from "@/components/home/CtaBanner";
import { services } from "@/lib/data/services";
import { generalFaqs } from "@/lib/data/faqs";

export const metadata: Metadata = {
  title: "Services",
  description: "Product engineering, cloud, AI, design, data and security — six disciplines under one accountable team.",
};

const stack = [
  "React",
  "Next.js",
  "TypeScript",
  "Node.js",
  "Python",
  "Go",
  "AWS",
  "GCP",
  "Kubernetes",
  "Terraform",
  "PostgreSQL",
  "GraphQL",
];

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="Six disciplines. One accountable team."
        description="We don't hand you off between teams. The same senior group that scopes your engagement designs, builds and ships it — end to end."
      />

      <Section size="tight">
        <Container>
          {services.map((service, i) => (
            <ServiceRow key={service.slug} service={service} index={i} />
          ))}
        </Container>
      </Section>

      <Section theme="light">
        <Container>
          <SectionHeading eyebrow="Toolkit" title="A modern, battle-tested stack." align="center" />
          <Reveal delay={0.1} className="mx-auto mt-10 flex max-w-3xl flex-wrap justify-center gap-3">
            {stack.map((tool) => (
              <span
                key={tool}
                className="rounded-full border border-(--color-border-strong) bg-(--color-surface) px-4 py-2 text-sm text-(--color-ink-muted)"
              >
                {tool}
              </span>
            ))}
          </Reveal>
        </Container>
      </Section>

      <Section>
        <Container size="narrow">
          <SectionHeading eyebrow="FAQ" title="Common questions about working with us." />
          <div className="mt-10">
            <Accordion items={generalFaqs} />
          </div>
        </Container>
      </Section>

      <CtaBanner />
    </>
  );
}
