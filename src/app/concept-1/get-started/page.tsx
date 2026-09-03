import type { Metadata } from "next";
import { ShieldCheck, Layers, Handshake, Rocket } from "lucide-react";
import { PageHero } from "@/components/concept-1/PageHero";
import { Section } from "@/components/concept-1/Section";
import { Reveal } from "@/components/concept-1/Reveal";
import { InquiryForm } from "@/components/concept-1/InquiryForm";

export const metadata: Metadata = {
  title: "Get Started",
  description:
    "Start your TECHBISS project — tell us about your goals, budget, and timeline, and we'll respond with next steps.",
};

const reasons = [
  {
    icon: Layers,
    title: "One team, full ownership",
    description: "Design, engineering, and infrastructure handled under one accountable roof.",
  },
  {
    icon: ShieldCheck,
    title: "Security built in",
    description: "SSL, hardening, and monitoring are standard on every project, not an add-on.",
  },
  {
    icon: Rocket,
    title: "Engineered for performance",
    description: "Fast, scalable builds designed to hold up as your traffic and team grow.",
  },
  {
    icon: Handshake,
    title: "Support that continues",
    description: "Every project includes a post-launch window, with ongoing plans available.",
  },
];

export default function ConceptOneGetStartedPage() {
  return (
    <>
      <PageHero
        eyebrow="Project Inquiry"
        title="Tell us what you're building."
        description="Share a few details about your project and goals. We'll review your inquiry and follow up with next steps and any clarifying questions."
      />

      <Section className="pt-0">
        <div className="grid gap-10 lg:grid-cols-[1fr_0.8fr] lg:gap-16">
          <Reveal>
            <InquiryForm />
          </Reveal>

          <Reveal delay={0.08}>
            <div className="space-y-6">
              <div className="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <h2 className="text-base font-semibold tracking-tight text-neutral-50">
                  What happens after you submit
                </h2>
                <p className="mt-3 text-sm leading-relaxed text-neutral-400">
                  We respond within [X] business days with next steps and, where useful, a few
                  clarifying questions before we scope your project. No automated sales funnel —
                  a real member of our team reviews every inquiry.
                </p>
              </div>

              <div className="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
                <h2 className="text-base font-semibold tracking-tight text-neutral-50">
                  Why TECHBISS
                </h2>
                <ul className="mt-4 space-y-4">
                  {reasons.map((reason) => (
                    <li key={reason.title} className="flex items-start gap-3">
                      <span className="flex h-9 w-9 flex-none items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                        <reason.icon className="h-4 w-4 text-neutral-100" aria-hidden="true" />
                      </span>
                      <div>
                        <p className="text-sm font-medium text-neutral-100">{reason.title}</p>
                        <p className="mt-0.5 text-xs leading-relaxed text-neutral-500">
                          {reason.description}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </Reveal>
        </div>
      </Section>
    </>
  );
}
