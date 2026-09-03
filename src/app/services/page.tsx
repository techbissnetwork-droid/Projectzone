import type { Metadata } from "next";
import Link from "next/link";
import * as Icons from "lucide-react";
import { Section, Eyebrow } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { services } from "@/lib/data/services";
import { MotionDiv } from "@/components/ui/motion-div";

export const metadata: Metadata = {
  title: "Services",
  description:
    "Websites, apps, e-commerce, hosting, security, email, automation and payments — the complete technology stack for your business, built by TECHBISS.",
};

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="The complete technology stack for your business."
        subtitle="From your first website to the infrastructure that keeps your business running — every service TECHBISS offers, built to work together."
      />

      <Section>
        <RevealGroup className="grid gap-px overflow-hidden rounded-2xl border border-line-dark bg-line-dark sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service, i) => (
            <MotionDiv key={service.slug} variants={revealItem}>
              <Link
                href={`/services/${service.slug}`}
                className="group flex h-full flex-col justify-between bg-ink-950 p-8 transition-colors hover:bg-ink-900/60"
              >
                <div>
                  <div className="flex items-center justify-between">
                    <span className="flex size-11 items-center justify-center rounded-xl border border-line-dark bg-ink-900 text-gold-400">
                      <Icon name={service.icon} className="size-5" />
                    </span>
                    <span className="font-mono-label text-[11px] text-paper-50/30">
                      {String(i + 1).padStart(2, "0")}
                    </span>
                  </div>
                  <h3 className="mt-6 text-[19px] font-medium text-paper-50">{service.name}</h3>
                  <p className="mt-2 text-[13.5px] leading-relaxed text-paper-50/50">
                    {service.short}
                  </p>
                </div>
                <span className="mt-8 flex items-center gap-1.5 text-[13px] font-medium text-paper-50/60 group-hover:text-gold-400">
                  Learn more
                  <Icons.ArrowUpRight className="size-3.5" />
                </span>
              </Link>
            </MotionDiv>
          ))}
        </RevealGroup>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <Eyebrow className="justify-center">Not sure where to start?</Eyebrow>
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Tell us about your business — we&rsquo;ll recommend the right stack.
          </h2>
          <Button href="/contact" size="lg" arrow>
            Talk to TECHBISS
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
