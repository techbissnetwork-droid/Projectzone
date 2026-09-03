import type { Metadata } from "next";
import { Building2, Mail, MapPin, Phone } from "lucide-react";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Accordion } from "@/components/ui/Accordion";
import { Reveal } from "@/components/ui/Reveal";
import { ContactForm } from "@/components/contact/ContactForm";
import { generalFaqs } from "@/lib/data/faqs";
import { offices } from "@/lib/data/about";

export const metadata: Metadata = {
  title: "Contact",
  description: "Tell TECHBISS about your project — we'll put together a scoped plan within 48 hours.",
};

export default function ContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Let's talk about what you're building."
        description="Whether it's a full modernization program or a focused product build, tell us the goal and we'll tell you what it takes."
      />

      <Section size="tight">
        <Container size="wide">
          <div className="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-8">
            <div className="lg:col-span-7">
              <ContactForm />
            </div>
            <div className="flex flex-col gap-4 lg:col-span-5">
              <Reveal className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                <h3 className="text-sm font-medium uppercase tracking-wide text-(--color-ink-faint)">Direct contact</h3>
                <div className="mt-4 flex flex-col gap-3 text-sm text-(--color-ink)">
                  <a href="mailto:hello@techbiss.com" className="focus-ring flex items-center gap-2.5 hover:text-(--color-accent-2)">
                    <Mail className="h-4 w-4 text-(--color-ink-faint)" /> hello@techbiss.com
                  </a>
                  <a href="tel:+18005551234" className="focus-ring flex items-center gap-2.5 hover:text-(--color-accent-2)">
                    <Phone className="h-4 w-4 text-(--color-ink-faint)" /> +1 (800) 555-1234
                  </a>
                </div>
              </Reveal>

              {offices.map((o, i) => (
                <Reveal key={o.city} delay={0.05 + i * 0.04} className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                  <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-(--radius-sm) bg-(--color-surface-raised) text-(--color-ink-muted)">
                      <Building2 className="h-4 w-4" />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-(--color-ink)">
                        {o.city} <span className="font-normal text-(--color-ink-faint)">— {o.role}</span>
                      </p>
                      <p className="mt-1 flex items-center gap-1.5 text-xs text-(--color-ink-faint)">
                        <MapPin className="h-3 w-3" /> {o.region}
                      </p>
                    </div>
                  </div>
                </Reveal>
              ))}
            </div>
          </div>
        </Container>
      </Section>

      <Section theme="light">
        <Container size="narrow">
          <SectionHeading eyebrow="FAQ" title="Before you reach out." />
          <div className="mt-10">
            <Accordion items={generalFaqs} />
          </div>
        </Container>
      </Section>
    </>
  );
}
