import type { Metadata } from "next";
import { Mail, MapPin, MessageCircle } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { ContactForm } from "@/components/contact/contact-form";

export const metadata: Metadata = {
  title: "Contact",
  description: "Tell TECHBISS about your business — custom development or marketplace, we'll help you choose.",
};

const info = [
  { icon: Mail, label: "Email", value: "hello@techbiss.com" },
  { icon: MessageCircle, label: "Response time", value: "Within one business day" },
  { icon: MapPin, label: "Working with businesses", value: "Worldwide, remote-first" },
];

export default function ContactPage() {
  return (
    <>
      <section className="border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20">
        <Container wide>
          <Eyebrow>Contact</Eyebrow>
          <h1 className="mt-6 max-w-2xl text-balance text-[38px] font-medium leading-[1.06] tracking-[-0.02em] text-paper-50 sm:text-[56px]">
            Let&rsquo;s talk about your business.
          </h1>
          <p className="mt-6 max-w-xl text-[15px] leading-relaxed text-paper-50/55 sm:text-[17px]">
            Whether you need something built from scratch or want help
            choosing from the marketplace, tell us what you&rsquo;re working
            on.
          </p>
        </Container>
      </section>

      <Section className="!pt-0">
        <div className="grid gap-16 lg:grid-cols-[1fr_1.3fr]">
          <Reveal>
            <div className="flex flex-col gap-6">
              {info.map((item) => (
                <div key={item.label} className="flex items-start gap-4">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line-dark bg-ink-900 text-gold-400">
                    <item.icon className="size-4.5" strokeWidth={1.75} />
                  </span>
                  <div>
                    <div className="text-[12px] uppercase tracking-wide text-paper-50/40">
                      {item.label}
                    </div>
                    <div className="mt-1 text-[15px] font-medium text-paper-50">{item.value}</div>
                  </div>
                </div>
              ))}
            </div>
          </Reveal>

          <Reveal delay={0.1}>
            <ContactForm />
          </Reveal>
        </div>
      </Section>
    </>
  );
}
