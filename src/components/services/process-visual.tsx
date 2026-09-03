import { ArrowRight } from "lucide-react";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import type { Service } from "@/lib/data/services";

function pad(i: number) {
  return String(i + 1).padStart(2, "0");
}

/** 01 · 05 · 09 — vertical connected layers */
function VerticalLayers({ service }: { service: Service }) {
  return (
    <div className="relative">
      <div
        aria-hidden
        className="absolute left-[19px] top-2 bottom-2 w-px bg-[var(--color-border-strong)]"
      />
      <RevealGroup className="relative flex flex-col gap-3" stagger={0.06}>
        {service.process.map((step, i) => (
          <RevealItem key={step.title}>
            <div className="group flex items-start gap-4 rounded-xl border border-transparent px-2 py-3 transition-colors duration-300 hover:border-[var(--color-border)] hover:bg-[var(--color-surface)]">
              <span
                className="relative flex size-[38px] shrink-0 items-center justify-center rounded-full border font-mono-label text-[11px]"
                style={{
                  borderColor: `${service.accent}55`,
                  color: service.accent,
                  backgroundColor: "var(--color-bg)",
                }}
              >
                {pad(i)}
              </span>
              <div className="flex-1 border-b border-[var(--color-border)] pb-3">
                <span className="text-[15.5px] font-medium text-[var(--color-ink)]">{step.title}</span>
                <p className="mt-1.5 max-w-[56ch] text-[13.5px] leading-relaxed text-[var(--color-ink-faint)]">
                  {step.description}
                </p>
              </div>
            </div>
          </RevealItem>
        ))}
      </RevealGroup>
    </div>
  );
}

/** 02 · 06 · 10 — horizontal numbered pill chain */
function PillChain({ service }: { service: Service }) {
  return (
    <div className="scrollbar-none -mx-6 overflow-x-auto px-6 sm:mx-0 sm:overflow-visible sm:px-0">
      <div className="flex min-w-max items-stretch gap-3 sm:min-w-0 sm:flex-wrap">
        {service.process.map((step, i) => (
          <Reveal key={step.title} delay={i * 0.05} className="flex items-stretch gap-3">
            <div className="flex w-[220px] flex-col rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-5 sm:w-[230px]">
              <span
                className="inline-flex w-fit rounded-full px-2.5 py-1 font-mono-label text-[10.5px]"
                style={{ backgroundColor: `${service.accent}1f`, color: service.accent }}
              >
                {pad(i)}
              </span>
              <span className="mt-4 text-[15px] font-medium text-[var(--color-ink)]">{step.title}</span>
              <p className="mt-2 text-[13px] leading-relaxed text-[var(--color-ink-faint)]">
                {step.description}
              </p>
            </div>
            {i < service.process.length - 1 && (
              <ArrowRight className="my-auto size-4 shrink-0 text-[var(--color-ink-faint)]" />
            )}
          </Reveal>
        ))}
      </div>
    </div>
  );
}

/** 03 · 07 — alternating left/right timeline */
function ZigzagTimeline({ service }: { service: Service }) {
  return (
    <div className="relative">
      <div
        aria-hidden
        className="absolute left-1/2 top-0 bottom-0 hidden w-px -translate-x-1/2 bg-[var(--color-border-strong)] sm:block"
      />
      <div className="flex flex-col gap-3 sm:gap-1">
        {service.process.map((step, i) => {
          const rightSide = i % 2 === 1;
          return (
            <Reveal
              key={step.title}
              delay={i * 0.06}
              className={`relative flex sm:items-center ${rightSide ? "sm:justify-end" : "sm:justify-start"}`}
            >
              <div
                aria-hidden
                className="absolute left-1/2 top-6 hidden size-2.5 -translate-x-1/2 rounded-full sm:block"
                style={{ backgroundColor: service.accent }}
              />
              <div
                className={`w-full rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 sm:w-[calc(50%-32px)] ${
                  rightSide ? "sm:text-right" : ""
                }`}
              >
                <span className="font-mono-label text-[11px]" style={{ color: service.accent }}>
                  {pad(i)}
                </span>
                <h4 className="mt-2 text-[15.5px] font-medium text-[var(--color-ink)]">{step.title}</h4>
                <p className="mt-1.5 text-[13.5px] leading-relaxed text-[var(--color-ink-faint)]">
                  {step.description}
                </p>
              </div>
            </Reveal>
          );
        })}
      </div>
    </div>
  );
}

/** 04 · 08 — numbered grid cards */
function GridCards({ service }: { service: Service }) {
  return (
    <RevealGroup className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" stagger={0.05}>
      {service.process.map((step, i) => (
        <RevealItem key={step.title}>
          <div className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 transition-colors duration-300 hover:border-[var(--color-border-strong)]">
            <div
              aria-hidden
              className="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"
              style={{ background: service.accent }}
            />
            <span className="relative font-serif-display text-[30px] italic leading-none text-[var(--color-ink-faint)]">
              {pad(i)}
            </span>
            <h4 className="relative mt-4 text-[15px] font-medium text-[var(--color-ink)]">{step.title}</h4>
            <p className="relative mt-2 text-[13px] leading-relaxed text-[var(--color-ink-faint)]">
              {step.description}
            </p>
          </div>
        </RevealItem>
      ))}
    </RevealGroup>
  );
}

const VARIANTS = [VerticalLayers, PillChain, ZigzagTimeline, GridCards];

export function ProcessVisual({ service }: { service: Service }) {
  const variantIndex = (parseInt(service.index, 10) - 1) % VARIANTS.length;
  const Variant = VARIANTS[variantIndex];
  return <Variant service={service} />;
}
