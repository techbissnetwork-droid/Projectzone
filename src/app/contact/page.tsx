import type { Metadata } from "next";
import Link from "next/link";
import { Mail, Clock, Globe } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ContactForm } from "@/components/contact/contact-form";

export const metadata: Metadata = {
  title: "Contact",
  description:
    "Tell TECHBISS about your project — custom build, marketplace theme, or both — and hear back from our team within one business day.",
};

const NEXT_STEPS = [
  {
    step: "01",
    title: "We review your project",
    description: "Our team reads every detail you send and maps it against similar systems we've built.",
  },
  {
    step: "02",
    title: "We schedule a call",
    description: "A short call to understand goals, timeline and budget — no sales script, just questions.",
  },
  {
    step: "03",
    title: "We propose a plan",
    description: "A clear scope, timeline and price — whether that's a custom build or a marketplace theme.",
  },
];

export default function ContactPage() {
  return (
    <section className="pb-24 pt-36 sm:pb-32 sm:pt-40 md:pt-44">
      <Container>
        <div className="grid grid-cols-1 gap-16 lg:grid-cols-[1fr_1.15fr] lg:gap-14">
          <div>
            <Reveal>
              <Eyebrow>Get In Touch</Eyebrow>
              <h1 className="mt-6 max-w-[16ch] text-balance text-[36px] font-medium leading-[1.06] tracking-[-0.02em] sm:text-[48px]">
                Let&apos;s build your digital presence.
              </h1>
              <p className="mt-6 max-w-[46ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[16px]">
                Whether you need a fully custom system from{" "}
                <Link href="/services" className="text-[var(--color-ink)] underline decoration-[var(--color-border-strong)] underline-offset-4 hover:decoration-[var(--color-ink)]">
                  our services team
                </Link>{" "}
                or want to start faster with a{" "}
                <Link href="/marketplace" className="text-[var(--color-ink)] underline decoration-[var(--color-border-strong)] underline-offset-4 hover:decoration-[var(--color-ink)]">
                  marketplace theme
                </Link>
                , tell us about the project and we&apos;ll point you the right direction.
              </p>
            </Reveal>

            <Reveal delay={0.08} className="mt-10 flex flex-col gap-5">
              <ContactDetail icon={<Mail className="size-4" />} label="Email">
                hello@techbiss.com
              </ContactDetail>
              <ContactDetail icon={<Globe className="size-4" />} label="Region">
                Remote-first, serving clients globally
              </ContactDetail>
              <ContactDetail icon={<Clock className="size-4" />} label="Hours">
                Mon – Fri, 9:00 – 18:00 (replies within 1 business day)
              </ContactDetail>
            </Reveal>

            <Reveal delay={0.14} className="mt-12 border-t border-[var(--color-border)] pt-10">
              <span className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
                What Happens Next
              </span>
              <RevealGroup className="mt-6 flex flex-col gap-6" stagger={0.06}>
                {NEXT_STEPS.map((s) => (
                  <RevealItem key={s.step}>
                    <div className="flex gap-4">
                      <span className="flex size-8 shrink-0 items-center justify-center rounded-full border border-[var(--color-border-strong)] font-mono-label text-[11px] text-[var(--color-ink-faint)]">
                        {s.step}
                      </span>
                      <div>
                        <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">{s.title}</h3>
                        <p className="mt-1 max-w-[38ch] text-[13px] leading-relaxed text-[var(--color-ink-faint)]">
                          {s.description}
                        </p>
                      </div>
                    </div>
                  </RevealItem>
                ))}
              </RevealGroup>
            </Reveal>
          </div>

          <Reveal delay={0.1}>
            <ContactForm />
          </Reveal>
        </div>
      </Container>
    </section>
  );
}

function ContactDetail({
  icon,
  label,
  children,
}: {
  icon: React.ReactNode;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex items-center gap-3.5">
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-ink-faint)]">
        {icon}
      </span>
      <div>
        <div className="font-mono-label text-[10.5px] uppercase text-[var(--color-ink-faint)]">{label}</div>
        <div className="mt-0.5 text-[14.5px] text-[var(--color-ink)]">{children}</div>
      </div>
    </div>
  );
}
