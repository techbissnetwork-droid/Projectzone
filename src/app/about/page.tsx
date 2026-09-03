import type { Metadata } from "next";
import { Section, Eyebrow } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { MotionDiv } from "@/components/ui/motion-div";
import { Target, ShieldCheck, Layers, Users } from "lucide-react";

export const metadata: Metadata = {
  title: "About",
  description:
    "TECHBISS is a digital transformation platform helping businesses build, launch and grow a complete online presence.",
};

const values = [
  {
    icon: Target,
    title: "Outcomes over output",
    detail: "We measure success by what your business gains, not the number of pages we ship.",
  },
  {
    icon: Layers,
    title: "One connected system",
    detail: "Website, app, hosting, security, email — designed to work together, not as disconnected vendors.",
  },
  {
    icon: ShieldCheck,
    title: "Engineering discipline",
    detail: "Production-grade code, real performance budgets, and security by default — not an afterthought.",
  },
  {
    icon: Users,
    title: "A long-term partner",
    detail: "We stay involved after launch — maintaining, monitoring and improving as your business grows.",
  },
];

export default function AboutPage() {
  return (
    <>
      <PageHero
        eyebrow="About TECHBISS"
        title="We don't just build websites. We build the entire digital presence of your business."
        subtitle="TECHBISS exists because most businesses shouldn't have to choose between a fast, cheap website and a real, dependable digital operation. We built a platform that gives them both — a marketplace to start fast, and a technology team to go further."
      />

      <Section>
        <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
          <Reveal>
            <Eyebrow>How We Work</Eyebrow>
            <h2 className="mt-5 text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
              Build from scratch. Buy ready. Make it yours.
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="text-[15px] leading-relaxed text-paper-50/60">
              Some businesses need something entirely custom. Others need to
              move fast with a proven foundation. TECHBISS was built to serve
              both — a full custom development team, and a marketplace of
              professionally built products that connect directly into the
              same infrastructure, brand tools and support system.
            </p>
          </Reveal>
        </div>

        <RevealGroup className="mt-16 grid gap-4 sm:grid-cols-2">
          {values.map((v) => (
            <MotionDiv
              key={v.title}
              variants={revealItem}
              className="rounded-2xl border border-line-dark bg-ink-900/40 p-7"
            >
              <v.icon className="size-5 text-gold-400" strokeWidth={1.75} />
              <h3 className="mt-5 text-[16px] font-medium text-paper-50">{v.title}</h3>
              <p className="mt-2 text-[13.5px] leading-relaxed text-paper-50/50">{v.detail}</p>
            </MotionDiv>
          ))}
        </RevealGroup>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal>
          <Eyebrow>The Ecosystem</Eyebrow>
          <h2 className="mt-5 max-w-2xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Themes, apps, domains, hosting, security, email, payments,
            automation, custom development, maintenance — one partner, the
            whole lifecycle.
          </h2>
        </Reveal>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Let&rsquo;s build your digital presence.
          </h2>
          <Button href="/contact" size="lg" arrow>
            Talk to TECHBISS
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
