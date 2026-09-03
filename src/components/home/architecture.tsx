import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";

const LAYERS = [
  { label: "Brand", note: "Identity & design system" },
  { label: "Website", note: "Marketing & content" },
  { label: "App", note: "iOS · Android · Web" },
  { label: "Backend", note: "Business logic & APIs" },
  { label: "Database", note: "Structured, secure data" },
  { label: "Payments", note: "Transactions & billing" },
  { label: "Hosting", note: "Cloud infrastructure" },
  { label: "Security", note: "SSL · WAF · monitoring" },
  { label: "Analytics", note: "Performance & insight" },
];

export function Architecture() {
  return (
    <section className="border-y border-[var(--color-border)] bg-[var(--color-bg-soft)] py-24 sm:py-32">
      <Container>
        <div className="grid gap-14 lg:grid-cols-2 lg:gap-10">
          <Reveal className="lg:sticky lg:top-32 lg:self-start">
            <Eyebrow>System Architecture</Eyebrow>
            <h2 className="mt-6 max-w-[15ch] text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
              We build digital systems, not just websites.
            </h2>
            <p className="mt-5 max-w-[46ch] text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
              Every layer of your business — from brand to backend, payments to
              infrastructure — engineered to work as one connected system, not a
              collection of disconnected tools.
            </p>
          </Reveal>

          <div className="relative">
            <div
              aria-hidden
              className="absolute left-[15px] top-2 bottom-2 w-px bg-[var(--color-border-strong)] sm:left-[19px]"
            />
            <RevealGroup className="relative flex flex-col gap-3" stagger={0.06}>
              {LAYERS.map((layer, i) => (
                <RevealItem key={layer.label}>
                  <div className="group flex items-center gap-4 rounded-xl border border-transparent px-2 py-2.5 transition-colors duration-300 hover:border-[var(--color-border)] hover:bg-[var(--color-surface)]">
                    <span className="relative flex size-[30px] shrink-0 items-center justify-center rounded-full border border-[var(--color-border-strong)] bg-[var(--color-bg)] font-mono-label text-[10px] text-[var(--color-ink-faint)] sm:size-[38px]">
                      {String(i + 1).padStart(2, "0")}
                      <span className="absolute -right-0.5 -top-0.5 size-1.5 rounded-full bg-[var(--color-live)] opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                    </span>
                    <div className="flex flex-1 items-baseline justify-between gap-3 border-b border-[var(--color-border)] pb-2.5">
                      <span className="text-[15px] font-medium text-[var(--color-ink)]">
                        {layer.label}
                      </span>
                      <span className="text-right text-[12.5px] text-[var(--color-ink-faint)]">
                        {layer.note}
                      </span>
                    </div>
                  </div>
                </RevealItem>
              ))}
            </RevealGroup>
          </div>
        </div>
      </Container>
    </section>
  );
}
