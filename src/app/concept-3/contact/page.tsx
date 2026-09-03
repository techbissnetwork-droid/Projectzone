import type { Metadata } from "next";
import { Mail, Phone, MapPin, Clock } from "lucide-react";
import { company, faqs } from "@/lib/site-data";
import { Section, SectionHeading, Eyebrow } from "@/components/concept-3/Section";
import { Reveal } from "@/components/concept-3/Reveal";
import { ContactForm } from "@/components/concept-3/ContactForm";
import { FaqAccordion } from "@/components/concept-3/FaqAccordion";

export const metadata: Metadata = {
  title: "Contact",
  description: "Get in touch with TECHBISS — send a message, browse contact details, or explore frequently asked questions.",
};

const contactDetails = [
  { label: "Email", value: company.email, href: `mailto:${company.email}`, icon: Mail },
  { label: "Phone", value: company.phone, href: undefined, icon: Phone },
  { label: "Office", value: company.address, href: undefined, icon: MapPin },
  { label: "Business hours", value: "[Add hours]", href: undefined, icon: Clock },
];

export default function ContactPage() {
  return (
    <>
      <Section className="pb-8 pt-14 sm:pt-20" aria-label="Contact">
        <Reveal className="max-w-3xl">
          <Eyebrow>Contact</Eyebrow>
          <h1 className="font-display mt-5 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Let&apos;s talk about your project.
          </h1>
          <p className="mt-6 text-lg leading-relaxed text-slate-400">
            Send a message with a bit of detail and the right person on our team will follow up.
          </p>
        </Reveal>
      </Section>

      <Section aria-label="Contact form and details" className="pt-0">
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_1.3fr] lg:gap-12">
          <Reveal>
            <div className="flex flex-col gap-4">
              {contactDetails.map((detail) => {
                const Icon = detail.icon;
                return (
                  <div key={detail.label} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                    <div className="flex items-center gap-3">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
                        <Icon className="h-4 w-4" aria-hidden="true" />
                      </span>
                      <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{detail.label}</p>
                        {detail.href ? (
                          <a
                            href={detail.href}
                            className="truncate text-sm font-medium text-white hover:text-violet-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 rounded"
                          >
                            {detail.value}
                          </a>
                        ) : (
                          <p className="truncate text-sm font-medium text-white">{detail.value}</p>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </Reveal>

          <Reveal delay={0.1}>
            <ContactForm />
          </Reveal>
        </div>
      </Section>

      <Section aria-label="Frequently asked questions" className="border-y border-white/5 bg-white/[0.015]">
        <Reveal>
          <SectionHeading eyebrow="FAQ" title="Frequently asked questions" align="center" />
        </Reveal>
        <div className="mx-auto mt-10 max-w-2xl">
          <FaqAccordion faqs={faqs} />
        </div>
      </Section>
    </>
  );
}
