import { ArrowRight } from "lucide-react";
import { Reveal } from "@/components/concept-1/Reveal";
import { Button } from "@/components/concept-1/Button";
import { Section } from "@/components/concept-1/Section";

export function CtaBanner({
  title = "Ready to build what's next?",
  description = "Tell us about your project and we'll map out the fastest, most reliable path from where you are to where you want to be.",
  primaryLabel = "Start Your Project",
  primaryHref = "/concept-1/get-started",
  secondaryLabel = "Talk to Us",
  secondaryHref = "/concept-1/contact",
}: {
  title?: string;
  description?: string;
  primaryLabel?: string;
  primaryHref?: string;
  secondaryLabel?: string;
  secondaryHref?: string;
}) {
  return (
    <Section className="py-16 sm:py-20">
      <Reveal>
        <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 px-6 py-14 text-center backdrop-blur-xl sm:px-12 sm:py-20">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 opacity-70"
            style={{
              background:
                "radial-gradient(60% 100% at 50% 0%, rgba(99,102,241,0.25) 0%, rgba(0,0,0,0) 70%)",
            }}
          />
          <div className="relative">
            <h2 className="mx-auto max-w-2xl text-3xl font-semibold tracking-tight text-neutral-50 sm:text-4xl lg:text-5xl">
              {title}
            </h2>
            <p className="mx-auto mt-5 max-w-xl text-base leading-relaxed text-neutral-400 sm:text-lg">
              {description}
            </p>
            <div className="mt-9 flex flex-wrap items-center justify-center gap-4">
              <Button href={primaryHref} variant="primary">
                {primaryLabel}
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Button>
              <Button href={secondaryHref} variant="secondary">
                {secondaryLabel}
              </Button>
            </div>
          </div>
        </div>
      </Reveal>
    </Section>
  );
}
