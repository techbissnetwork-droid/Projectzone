import type { Metadata } from "next";
import { PageHero } from "@/components/concept-2/PageHero";
import { Section } from "@/components/concept-2/Section";
import { Reveal } from "@/components/concept-2/Reveal";
import { fontSerif } from "@/components/concept-2/fonts";
import { InquiryForm } from "@/components/concept-2/InquiryForm";

export const metadata: Metadata = {
  title: "Get Started",
  description: "Tell us about your project and we'll follow up with next steps.",
};

const reasons = [
  "One team accountable for the entire build, start to finish.",
  "A fixed six-stage process, so you always know what's next.",
  "Security and performance built in, not added later.",
  "Support that continues after launch, not just until invoice.",
];

export default function GetStartedPage() {
  return (
    <>
      <PageHero
        eyebrow="Get Started"
        title="Tell us about your project."
        description="A few details help us prepare for a focused first conversation — there's no commitment at this stage."
      />

      <Section>
        <div className="grid gap-16 lg:grid-cols-[1fr_1.3fr]">
          <div>
            <Reveal>
              <div className="border-t border-neutral-200 pt-8">
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">What happens next</p>
                <p className="mt-4 text-sm leading-relaxed text-neutral-600">
                  We respond within [X] business days with a short discovery call proposal. There&apos;s no
                  obligation — it&apos;s a conversation to understand your goals before any scope or pricing is
                  discussed.
                </p>
              </div>
            </Reveal>
            <Reveal delay={0.1}>
              <div className="mt-10 border-t border-neutral-200 pt-8">
                <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Why TECHBISS</p>
                <h2 className={`${fontSerif} mt-4 text-2xl text-neutral-900`}>
                  Four reasons founders choose us twice.
                </h2>
                <ul className="mt-5 space-y-4">
                  {reasons.map((r) => (
                    <li key={r} className="text-sm leading-relaxed text-neutral-600">
                      {r}
                    </li>
                  ))}
                </ul>
              </div>
            </Reveal>
          </div>
          <Reveal delay={0.15}>
            <InquiryForm />
          </Reveal>
        </div>
      </Section>
    </>
  );
}
