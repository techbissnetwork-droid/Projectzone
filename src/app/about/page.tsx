import type { Metadata } from "next";
import { Building2, MapPin } from "lucide-react";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { RevealGroup, revealItem, Reveal } from "@/components/ui/Reveal";
import { Stat } from "@/components/ui/Stat";
import { CtaBanner } from "@/components/home/CtaBanner";
import { AboutMotionGrid, AboutMotionItem } from "@/components/about/AboutMotion";
import { team } from "@/lib/data/testimonials";
import { values, milestones, offices } from "@/lib/data/about";

export const metadata: Metadata = {
  title: "About",
  description: "TECHBISS is a global digital transformation partner — meet the team, our values and our story.",
};

const stats = [
  { value: "2016", label: "Founded" },
  { value: "120+", label: "Engineers & strategists" },
  { value: "4", label: "Global offices" },
  { value: "400+", label: "Companies transformed" },
];

export default function AboutPage() {
  return (
    <>
      <PageHero
        eyebrow="About TECHBISS"
        title="We build the technology ambitious companies run on."
        description="Founded in 2016, TECHBISS has grown from a three-person studio into a global partner for enterprises, startups and everything in between — without losing the senior-led, no-nonsense approach we started with."
      />

      <div className="border-y border-(--color-border)">
        <Container size="wide">
          <div className="grid grid-cols-2 gap-8 py-14 sm:grid-cols-4">
            {stats.map((s, i) => (
              <Stat key={s.label} value={s.value} label={s.label} delay={i * 0.05} />
            ))}
          </div>
        </Container>
      </div>

      <Section theme="light">
        <Container>
          <SectionHeading eyebrow="What we believe" title="The principles behind every engagement." />
          <AboutMotionGrid className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {values.map((v) => (
              <AboutMotionItem key={v.title} className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                <h3 className="text-base font-medium text-(--color-ink)">{v.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-(--color-ink-muted)">{v.description}</p>
              </AboutMotionItem>
            ))}
          </AboutMotionGrid>
        </Container>
      </Section>

      <Section>
        <Container>
          <SectionHeading eyebrow="Leadership" title="The team steering the ship." />
          <AboutMotionGrid className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {team.map((member) => (
              <AboutMotionItem
                key={member.name}
                className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6"
              >
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[linear-gradient(135deg,#4b5bff,#17c3ff)] text-sm font-medium text-white">
                  {member.initials}
                </div>
                <h3 className="mt-4 text-base font-medium text-(--color-ink)">{member.name}</h3>
                <p className="text-xs text-(--color-ink-faint)">{member.role}</p>
                <p className="mt-3 text-sm leading-relaxed text-(--color-ink-muted)">{member.bio}</p>
              </AboutMotionItem>
            ))}
          </AboutMotionGrid>
        </Container>
      </Section>

      <Section theme="light">
        <Container size="narrow">
          <SectionHeading eyebrow="Our story" title="A decade of momentum." />
          <div className="mt-12 flex flex-col gap-6">
            {milestones.map((m) => (
              <Reveal key={m.year} className="flex gap-6">
                <span className="w-16 shrink-0 text-sm font-medium text-(--color-accent-2)">{m.year}</span>
                <div className="flex-1 border-l border-(--color-border) pb-6 pl-6">
                  <h3 className="text-base font-medium text-(--color-ink)">{m.title}</h3>
                  <p className="mt-1.5 text-sm leading-relaxed text-(--color-ink-muted)">{m.description}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </Section>

      <Section>
        <Container>
          <SectionHeading eyebrow="Where we work" title="Global offices, one standard." />
          <RevealGroup className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {offices.map((o) => (
              <div
                key={o.city}
                className="flex flex-col gap-3 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6"
              >
                <div className="flex h-10 w-10 items-center justify-center rounded-(--radius-md) bg-[linear-gradient(135deg,rgba(75,91,255,0.12),rgba(23,195,255,0.12))] text-(--color-accent)">
                  <Building2 className="h-4.5 w-4.5" />
                </div>
                <div>
                  <p className="text-base font-medium text-(--color-ink)">{o.city}</p>
                  <p className="flex items-center gap-1 text-xs text-(--color-ink-faint)">
                    <MapPin className="h-3 w-3" /> {o.region}
                  </p>
                </div>
                <span className="text-xs text-(--color-ink-muted)">{o.role}</span>
              </div>
            ))}
          </RevealGroup>
        </Container>
      </Section>

      <CtaBanner />
    </>
  );
}
