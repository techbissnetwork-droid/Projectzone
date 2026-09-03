import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { Reveal } from "@/components/shared/Reveal";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";
import { ServiceCard } from "@/components/services/ServiceCard";
import { services } from "@/lib/data/services";

export const metadata: Metadata = {
  title: "Services",
  description:
    "The complete TECHBISS service ecosystem — websites, apps, hosting, security, email, e-commerce, automation and payments, all under one connected team.",
};

const categories = [
  {
    name: "Digital Presence",
    blurb: "The face of your business online — designed, built and engineered to convert.",
  },
  {
    name: "Infrastructure",
    blurb: "The foundation your digital presence runs on — fast, secure and always available.",
  },
  {
    name: "Growth & Operations",
    blurb: "The systems that keep your business running, selling and improving in the background.",
  },
] as const;

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="Everything your business needs to operate online."
        lead="Twelve disciplines. One connected team. Mix and match what you need — or let TECHBISS build the entire digital ecosystem end to end."
        stats={[
          { value: "12", label: "Core services" },
          { value: "9", label: "In-depth specialties" },
          { value: "1", label: "Team, start to finish" },
        ]}
      />

      {categories.map((category, ci) => {
        const items = services.filter((s) => s.category === category.name);
        return (
          <section
            key={category.name}
            className={
              ci % 2 === 1
                ? "border-y border-line bg-ink-raised py-20 md:py-28"
                : "py-20 md:py-28"
            }
          >
            <Container>
              <div className="flex flex-col justify-between gap-4 border-b border-line pb-8 md:flex-row md:items-end">
                <Reveal>
                  <div className="flex items-center gap-3">
                    <span className="text-eyebrow text-paper-faint">0{ci + 1}</span>
                    <h2 className="text-h3 font-medium text-paper">{category.name}</h2>
                  </div>
                </Reveal>
                <Reveal delay={0.08}>
                  <p className="max-w-md text-[0.95rem] text-paper-dim">{category.blurb}</p>
                </Reveal>
              </div>

              <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((service, i) => (
                  <Reveal key={service.slug} delay={0.04 * i}>
                    <ServiceCard service={service} />
                  </Reveal>
                ))}
              </div>
            </Container>
          </section>
        );
      })}

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone="gold">Not Sure Where to Start?</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Tell us about your business. We&apos;ll map the right services to it.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Start a Project
            </Button>
            <Button href="/process" variant="secondary" size="lg">
              See How We Work
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}
