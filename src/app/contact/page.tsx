import type { Metadata } from "next";
import { Mail, Phone, MapPin, Clock } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/shared/PageHero";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/shared/Reveal";
import { Accordion } from "@/components/shared/Accordion";
import { ContactForm } from "@/components/contact/ContactForm";
import { site } from "@/lib/data/site";

export const metadata: Metadata = {
  title: "Contact",
  description:
    "Start your digital transformation. Tell TECHBISS about your business and get a response from our team within one business day.",
};

const contactDetails = [
  { icon: Mail, label: "Email", value: site.email, href: `mailto:${site.email}` },
  { icon: Phone, label: "Phone", value: site.phone, href: `tel:${site.phone.replace(/[^+\d]/g, "")}` },
  { icon: MapPin, label: "Studio", value: site.address, href: undefined },
  { icon: Clock, label: "Response Time", value: "Within 1 business day", href: undefined },
];

const steps = [
  { title: "We review your project", desc: "Every inquiry is read by a real person on the team, within one business day." },
  { title: "We schedule a call", desc: "A short conversation to understand your business, goals and timeline." },
  { title: "You get a proposal", desc: "Clear scope, timeline and pricing — no obligation, no pressure." },
];

const faqs = [
  {
    q: "How much does a typical project cost?",
    a: "It depends on scope — a brand website starts differently than a full digital ecosystem. Tell us about your business and we'll give you a clear, honest range early in the conversation.",
  },
  {
    q: "How long does a project take?",
    a: "Most website projects launch in 4–8 weeks. Larger builds involving apps, custom platforms or full digitization typically run 8–16 weeks.",
  },
  {
    q: "Do you work with businesses outside the US?",
    a: "Yes — TECHBISS works with businesses internationally. All communication and delivery happens remotely and asynchronously where needed.",
  },
  {
    q: "What if I'm not sure what I need yet?",
    a: "That's what Discovery is for. Most clients arrive with a problem, not a spec — we help translate that into the right services.",
  },
];

export default function ContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Let's build your digital presence."
        lead="Tell us about your business. We'll figure out the technology, the timeline and the right team to make it happen."
      />

      <section className="pb-24 md:pb-32">
        <Container>
          <div className="grid gap-14 lg:grid-cols-[0.85fr_1.15fr] lg:gap-20">
            <div className="flex flex-col gap-12">
              <Reveal>
                <div className="flex flex-col gap-6">
                  {contactDetails.map((c) => (
                    <div key={c.label} className="flex items-start gap-4">
                      <span className="flex size-11 shrink-0 items-center justify-center rounded-full border border-line-strong text-gold-bright">
                        <c.icon className="size-[1.1rem]" aria-hidden />
                      </span>
                      <div>
                        <p className="text-eyebrow text-paper-faint">{c.label}</p>
                        {c.href ? (
                          <a
                            href={c.href}
                            className="mt-1 block text-[0.95rem] text-paper transition-colors hover:text-gold-bright"
                          >
                            {c.value}
                          </a>
                        ) : (
                          <p className="mt-1 text-[0.95rem] text-paper">{c.value}</p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </Reveal>

              <Reveal delay={0.1}>
                <div className="flex flex-col gap-6 border-t border-line pt-10">
                  <span className="text-eyebrow text-paper-faint">What Happens Next</span>
                  {steps.map((s, i) => (
                    <div key={s.title} className="flex gap-4">
                      <span className="text-eyebrow shrink-0 text-gold-bright">0{i + 1}</span>
                      <div>
                        <p className="font-medium text-paper">{s.title}</p>
                        <p className="mt-1 text-sm leading-relaxed text-paper-dim">{s.desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </Reveal>
            </div>

            <Reveal delay={0.05}>
              <div className="rounded-2xl border border-line bg-ink-raised-2 p-7 sm:p-10">
                <ContactForm />
              </div>
            </Reveal>
          </div>
        </Container>
      </section>

      <section className="border-t border-line bg-ink-raised py-24 md:py-32">
        <Container className="mx-auto max-w-3xl">
          <SectionHeading align="center" eyebrow="Before You Reach Out" title="A few common questions." />
          <div className="mt-12">
            <Accordion items={faqs} />
          </div>
        </Container>
      </section>
    </>
  );
}
