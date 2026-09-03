import { ArrowRight } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/ui/Reveal";

export function CtaBanner() {
  return (
    <section className="relative overflow-hidden py-24 sm:py-28">
      <div className="pointer-events-none absolute left-1/2 top-1/2 h-[30rem] w-[60rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(75,91,255,0.2),transparent)] blur-2xl" />
      <Container>
        <Reveal className="relative flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-2xl text-balance text-3xl font-medium tracking-tight text-(--color-ink) sm:text-4xl">
            Ready to build what&apos;s next?
          </h2>
          <p className="max-w-xl text-balance text-base text-(--color-ink-muted) sm:text-lg">
            Tell us about your project and we&apos;ll put together a scoped plan within 48 hours — no obligation.
          </p>
          <div className="mt-2 flex flex-col items-center gap-3 sm:flex-row">
            <Button href="/contact" variant="secondary" size="lg" icon={<ArrowRight className="h-4 w-4" />}>
              Start a Conversation
            </Button>
            <Button href="/work" variant="outline" size="lg">
              View our work
            </Button>
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
