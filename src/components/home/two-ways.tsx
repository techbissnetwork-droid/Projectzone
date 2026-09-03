"use client";

import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { Hammer, Store } from "lucide-react";

export function TwoWays() {
  return (
    <section className="border-b border-line-dark py-24 sm:py-32">
      <Container wide>
        <Reveal>
          <Eyebrow>Two Ways to Start</Eyebrow>
          <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
            However you start, TECHBISS takes you the rest of the way.
          </h2>
        </Reveal>

        <div className="mt-16 grid gap-6 lg:grid-cols-2">
          <Reveal delay={0.05}>
            <div className="group relative flex h-full flex-col justify-between overflow-hidden rounded-3xl border border-line-dark bg-ink-900/60 p-9 transition-colors hover:border-line-dark-strong sm:p-11">
              <div>
                <span className="flex size-11 items-center justify-center rounded-xl border border-line-dark bg-ink-850 text-paper-50">
                  <Hammer className="size-5" strokeWidth={1.75} />
                </span>
                <h3 className="mt-8 text-[26px] font-medium tracking-tight text-paper-50">
                  Build Custom
                </h3>
                <p className="mt-3 max-w-sm text-[15px] leading-relaxed text-paper-50/55">
                  Your business. Your technology. Built from zero — for
                  businesses that need unique functionality no template can
                  offer.
                </p>
              </div>
              <div className="mt-10">
                <Button href="/services" arrow variant="ghost">
                  Build With TECHBISS
                </Button>
              </div>
            </div>
          </Reveal>

          <Reveal delay={0.12}>
            <div className="group relative flex h-full flex-col justify-between overflow-hidden rounded-3xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-9 transition-colors hover:border-gold-500/45 sm:p-11">
              <div>
                <span className="flex size-11 items-center justify-center rounded-xl border border-gold-500/30 bg-gold-500/10 text-gold-400">
                  <Store className="size-5" strokeWidth={1.75} />
                </span>
                <h3 className="mt-8 text-[26px] font-medium tracking-tight text-paper-50">
                  Buy Ready-Made
                </h3>
                <p className="mt-3 max-w-sm text-[15px] leading-relaxed text-paper-50/55">
                  Start with something already built. Choose a professionally
                  designed product, then customize, brand and launch it as
                  your own.
                </p>
              </div>
              <div className="mt-10">
                <Button href="/marketplace" arrow>
                  Browse Marketplace
                </Button>
              </div>
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
