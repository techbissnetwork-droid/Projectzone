import type { Metadata } from "next";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";
import { ServicesIndexList } from "@/components/services/services-index-list";
import { services } from "@/lib/data/services";

export const metadata: Metadata = {
  title: "Services",
  description:
    "Ten connected services covering every layer of your digital business — websites, apps, e-commerce, infrastructure, security and more, engineered by TECHBISS.",
};

export default function ServicesPage() {
  return (
    <>
      <section className="pt-36 sm:pt-40 md:pt-44">
        <Container>
          <Reveal className="max-w-[720px]">
            <Eyebrow index={`01–${services.length.toString().padStart(2, "0")}`}>Services</Eyebrow>
            <h1 className="mt-6 text-balance text-[38px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[54px] md:text-[64px]">
              Everything your business needs, engineered.
            </h1>
            <p className="mt-6 max-w-[56ch] text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Ten disciplines, one connected system. From the first line of code to
              the infrastructure it runs on, every service is built to work together
              — not as disconnected vendors, but as a single digital foundation for
              your business.
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="mt-20 pb-24 sm:mt-24 sm:pb-32">
        <Container>
          <ServicesIndexList />
        </Container>
      </section>

      <section className="relative overflow-hidden border-t border-[var(--color-border)] py-24 sm:py-32">
        <div
          aria-hidden
          className="pointer-events-none absolute left-1/2 top-1/2 h-[420px] w-[820px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(81,112,255,0.14),transparent)] blur-2xl"
        />
        <Container className="relative text-center">
          <Reveal>
            <h2 className="mx-auto max-w-[20ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[46px]">
              Not sure which service you need?
            </h2>
          </Reveal>
          <Reveal delay={0.1}>
            <p className="mx-auto mt-5 max-w-[54ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[17px]">
              Tell us about your business and we&apos;ll map out exactly what you
              need — whether that&apos;s one service or the full system.
            </p>
          </Reveal>
          <Reveal delay={0.2} className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              Start a Project
            </Button>
            <Button href="/marketplace" variant="secondary" size="lg">
              Browse Marketplace
            </Button>
          </Reveal>
        </Container>
      </section>
    </>
  );
}
