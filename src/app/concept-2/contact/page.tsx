import type { Metadata } from "next";
import { company, faqs } from "@/lib/site-data";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { ContactForm } from "@/components/concept-2/ContactForm";
import { FaqAccordion } from "@/components/concept-2/FaqAccordion";

export const metadata: Metadata = {
  title: "Contact",
  description: "Reach TECHBISS directly, or send a project message.",
};

export default function ContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Let's talk about your project."
        description="Reach us directly, or send a message and we'll follow up."
      />

      <Section>
        <div className="grid gap-16 lg:grid-cols-[1fr_1.3fr]">
          <Reveal>
            <div className="space-y-8 border-t border-neutral-200 pt-8">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Email</p>
                <a
                  href={`mailto:${company.email}`}
                  className={`${fontSerif} mt-2 block rounded-sm text-xl text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 sm:text-2xl`}
                >
                  {company.email}
                </a>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Phone</p>
                <p className={`${fontSerif} mt-2 text-xl text-neutral-900 sm:text-2xl`}>{company.phone}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Office</p>
                <p className={`${fontSerif} mt-2 text-xl text-neutral-900 sm:text-2xl`}>{company.address}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Business hours</p>
                <p className="mt-2 text-sm text-neutral-600">[Add hours]</p>
              </div>
            </div>
          </Reveal>
          <Reveal delay={0.1}>
            <ContactForm />
          </Reveal>
        </div>
      </Section>

      <Section tone="off" border="top">
        <Reveal>
          <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Frequently asked</p>
          <h2 className={`${fontSerif} mt-4 max-w-xl text-4xl text-neutral-900 sm:text-5xl`}>Questions we hear often.</h2>
        </Reveal>
        <div className="mt-10">
          <FaqAccordion faqs={faqs} />
        </div>
      </Section>
    </>
  );
}
