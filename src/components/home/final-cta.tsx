"use client";

import { Container } from "@/components/ui/container";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/ui/reveal";

export function FinalCta() {
  return (
    <section className="relative overflow-hidden py-28 sm:py-36">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-full bg-[radial-gradient(ellipse_60%_60%_at_50%_40%,rgba(200,161,101,0.12),rgba(0,0,0,0))]"
      />
      <Container className="relative text-center">
        <Reveal>
          <h2 className="mx-auto max-w-3xl text-balance text-[34px] font-medium leading-[1.08] tracking-[-0.02em] text-paper-50 sm:text-[54px]">
            Ready to take your business online?
          </h2>
          <p className="mx-auto mt-6 max-w-lg text-[16px] leading-relaxed text-paper-50/55">
            Tell us what you&rsquo;re building. We&rsquo;ll help you choose
            the right technology — completely custom, or a ready-made theme
            to start faster.
          </p>
          <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
            <Button href="/contact" size="lg" arrow>
              Start Your Project
            </Button>
            <Button href="/marketplace" size="lg" variant="ghost">
              Browse Themes
            </Button>
          </div>
          <div className="mt-5">
            <Button href="/contact" size="sm" variant="ghost" className="border-0 text-paper-50/50 hover:text-paper-50">
              Talk to TECHBISS
            </Button>
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
