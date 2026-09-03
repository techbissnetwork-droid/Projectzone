import { ArrowUpRight, MessageSquare } from "lucide-react";
import { Section } from "./Section";
import { Button } from "./Button";
import { Reveal } from "./Reveal";

export function CtaSection({
  title = "Ready to build what moves your business forward?",
  description = "Tell us where you are today and where you want to be. We'll map the fastest credible path to get there.",
  primaryLabel = "Start Your Project",
  primaryHref = "/concept-3/get-started",
  secondaryLabel = "Talk to Us",
  secondaryHref = "/concept-3/contact",
}: {
  title?: string;
  description?: string;
  primaryLabel?: string;
  primaryHref?: string;
  secondaryLabel?: string;
  secondaryHref?: string;
}) {
  return (
    <Section aria-label="Call to action">
      <Reveal>
        <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-violet-600/20 via-[#0f1020] to-blue-600/10 px-6 py-14 text-center sm:px-12 sm:py-16">
          <div
            className="pointer-events-none absolute -top-24 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-violet-500/30 blur-3xl"
            aria-hidden="true"
          />
          <div className="relative flex flex-col items-center gap-6">
            <h2 className="font-display max-w-2xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
              {title}
            </h2>
            <p className="max-w-xl text-base text-slate-300 sm:text-lg">{description}</p>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button href={primaryHref} icon={ArrowUpRight}>
                {primaryLabel}
              </Button>
              <Button href={secondaryHref} variant="secondary" icon={MessageSquare} iconPosition="leading">
                {secondaryLabel}
              </Button>
            </div>
          </div>
        </div>
      </Reveal>
    </Section>
  );
}
