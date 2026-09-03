import type { Metadata } from "next";
import { Section } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { ProcessSteps } from "@/components/process/process-steps";

export const metadata: Metadata = {
  title: "Process",
  description:
    "Discover, Choose, Build, Brand, Launch, Grow — the TECHBISS process for taking a business from idea to a complete digital operation.",
};

export default function ProcessPage() {
  return (
    <>
      <PageHero
        eyebrow="Our Process"
        title="One clear path, six deliberate steps."
        subtitle="Every project — custom-built or marketplace-based — follows the same disciplined process, from first conversation to ongoing growth."
      />

      <Section className="!pt-0">
        <ProcessSteps />
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Ready to start step one?
          </h2>
          <Button href="/contact" size="lg" arrow>
            Start a Project
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
