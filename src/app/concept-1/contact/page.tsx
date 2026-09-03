import type { Metadata } from "next";
import { Mail, Phone, MapPin, Clock, type LucideIcon } from "lucide-react";
import { company, faqs } from "@/lib/site-data";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section, SectionHeading } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { ContactForm } from "@/components/concept-1/ContactForm";
import { FaqAccordion } from "@/components/concept-1/FaqAccordion";

export const metadata: Metadata = {
  title: "Contact",
  description:
    "Get in touch with TECHBISS — email, phone, office details, and answers to frequently asked questions.",
};

type ContactDetail = {
  icon: LucideIcon;
  label: string;
  value: string;
  href?: string;
};

const contactDetails: ContactDetail[] = [
  { icon: Mail, label: "Email", value: company.email, href: `mailto:${company.email}` },
  { icon: Phone, label: "Phone", value: company.phone },
  { icon: MapPin, label: "Office", value: company.address },
  { icon: Clock, label: "Business hours", value: "[Add hours]" },
];

export default function ConceptOneContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Let's talk about your project."
        description="Reach out directly, or send us the details below and we'll get back to you shortly."
      />

      <Section className="pt-0">
        <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
          <Reveal>
            <div className="space-y-4">
              {contactDetails.map((detail) => (
                <div
                  key={detail.label}
                  className="flex items-start gap-4 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl"
                >
                  <span className="flex h-11 w-11 flex-none items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                    <detail.icon className="h-5 w-5 text-neutral-100" aria-hidden="true" />
                  </span>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-[0.2em] text-neutral-500">
                      {detail.label}
                    </p>
                    {detail.href ? (
                      <a
                        href={detail.href}
                        className="mt-1 block text-sm font-medium text-neutral-100 transition-colors hover:text-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded"
                      >
                        {detail.value}
                      </a>
                    ) : (
                      <p className="mt-1 text-sm font-medium text-neutral-100">{detail.value}</p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </Reveal>

          <Reveal delay={0.08}>
            <ContactForm />
          </Reveal>
        </div>
      </Section>

      <Section className="border-t border-white/5">
        <SectionHeading eyebrow="FAQ" title="Frequently asked questions." />
        <div className="mt-10 max-w-3xl">
          <FaqAccordion items={faqs} />
        </div>
      </Section>
    </>
  );
}
