import type { Metadata } from "next";
import { Palette, Cpu, Handshake, Gauge, PenTool, Code2, Server, LineChart } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/shared/Reveal";
import { AnimatedHeadline } from "@/components/shared/AnimatedHeadline";
import { site } from "@/lib/data/site";

export const metadata: Metadata = {
  title: "About",
  description:
    "TECHBISS is a digital transformation partner for businesses moving from offline to online — our philosophy, approach and the team behind it.",
};

const principles = [
  {
    icon: Palette,
    title: "Craft over templates",
    desc: "Every project starts from your brand and your customers — never a theme we bend your business to fit.",
    size: "lg",
  },
  {
    icon: Cpu,
    title: "Systems over one-offs",
    desc: "We build infrastructure that compounds, not disconnected tools that need replacing in a year.",
    size: "sm",
  },
  {
    icon: Gauge,
    title: "Speed without shortcuts",
    desc: "Fast doesn't mean fragile. We move quickly on foundations built to last.",
    size: "sm",
  },
  {
    icon: Handshake,
    title: "Partnership, not a handoff",
    desc: "Launch is the beginning of the relationship, not the end of the invoice.",
    size: "lg",
  },
];

const disciplines = [
  { icon: PenTool, label: "Brand & Product Design" },
  { icon: Code2, label: "Software Engineering" },
  { icon: Server, label: "Cloud & Infrastructure" },
  { icon: LineChart, label: "Growth & Strategy" },
];

const stats = [
  { value: `${site.founded}`, label: "Founded" },
  { value: "150+", label: "Projects launched" },
  { value: "6", label: "Industries served" },
  { value: "94%", label: "Client retention" },
];

export default function AboutPage() {
  return (
    <>
      <PageHero
        eyebrow="About TECHBISS"
        title="We build the digital infrastructure ambitious businesses run on."
        lead="TECHBISS exists for one reason: most businesses aren't short on ambition — they're short on the technology to match it."
      />

      <section className="border-y border-line py-24 md:py-32">
        <Container className="flex flex-col items-center text-center">
          <Reveal>
            <Eyebrow tone="gold">Our Mission</Eyebrow>
          </Reveal>
          <AnimatedHeadline
            text="We don't just build websites. We build the entire digital presence of your business."
            as="h2"
            delay={0.1}
            className="text-h1 mt-6 max-w-4xl text-balance font-medium text-paper"
          />
        </Container>
      </section>

      <section className="py-16 md:py-20">
        <Container>
          <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
            {stats.map((s, i) => (
              <Reveal key={s.label} delay={0.06 * i}>
                <div className="border-t-2 border-line-strong pt-5">
                  <p className="text-h2 font-medium text-paper">{s.value}</p>
                  <p className="text-eyebrow mt-2 text-paper-faint">{s.label}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="py-24 md:py-32">
        <Container>
          <SectionHeading
            eyebrow="How We Think"
            title="Four ideas behind every project we take on."
          />
          <div className="mt-14 grid gap-5 md:grid-cols-2">
            {principles.map((p, i) => (
              <Reveal
                key={p.title}
                delay={0.06 * i}
                className={p.size === "lg" ? "md:row-span-1" : ""}
              >
                <div className="flex h-full flex-col gap-5 rounded-2xl border border-line bg-ink-raised-2 p-8 sm:p-10">
                  <span className="flex size-12 items-center justify-center rounded-full border border-gold/40 bg-gold-dim text-gold-bright">
                    <p.icon className="size-5" aria-hidden />
                  </span>
                  <div>
                    <h3 className="text-xl font-medium text-paper">{p.title}</h3>
                    <p className="mt-3 max-w-md text-[0.95rem] leading-relaxed text-paper-dim">
                      {p.desc}
                    </p>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-line bg-ink-raised py-24 md:py-32">
        <Container>
          <div className="grid gap-12 lg:grid-cols-2 lg:gap-20">
            <div>
              <SectionHeading
                align="left"
                tone="signal"
                eyebrow="Who We Are"
                title="One team, every discipline your digital business needs."
              />
              <p className="mt-6 max-w-lg text-[0.95rem] leading-relaxed text-paper-dim">
                No handoffs between agencies for design, a freelancer for the
                app, and a different vendor for hosting. TECHBISS is one
                connected team — designers, engineers, infrastructure
                specialists and strategists — accountable for the whole
                outcome, not just their slice of it.
              </p>
            </div>
            <div className="grid grid-cols-2 gap-4">
              {disciplines.map((d, i) => (
                <Reveal key={d.label} delay={0.06 * i}>
                  <div className="flex h-full flex-col justify-between gap-8 rounded-2xl border border-line bg-ink-raised-2 p-6">
                    <d.icon className="size-6 text-signal-bright" aria-hidden />
                    <span className="font-medium text-paper">{d.label}</span>
                  </div>
                </Reveal>
              ))}
            </div>
          </div>
        </Container>
      </section>

      <section className="py-24 md:py-32">
        <Container className="flex flex-col items-center gap-6 rounded-2xl border border-line bg-ink-raised-2 px-8 py-16 text-center sm:px-16">
          <Eyebrow tone="gold">Let&apos;s Build Together</Eyebrow>
          <h2 className="text-h2 max-w-2xl text-balance font-medium text-paper">
            Your business is ready. Let&apos;s make it official, online.
          </h2>
          <div className="flex flex-col gap-4 pt-2 sm:flex-row">
            <Button href="/contact" size="lg">
              Start a Project
            </Button>
            <Button href="/work" variant="secondary" size="lg">
              See Our Work
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}
