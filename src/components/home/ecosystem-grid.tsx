import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ecosystemNodes } from "@/lib/data/ecosystem";

export function EcosystemGrid() {
  return (
    <section className="py-24 sm:py-32">
      <Container>
        <Reveal className="mx-auto max-w-[640px] text-center">
          <Eyebrow className="justify-center">The Ecosystem</Eyebrow>
          <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
            Everything your business needs online.
          </h2>
        </Reveal>

        <RevealGroup className="mt-14 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {ecosystemNodes.map((node) => (
            <RevealItem key={node.key}>
              <Link
                href={node.href}
                className="group relative flex h-[168px] flex-col justify-between overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 transition-all duration-300 hover:border-[var(--color-border-strong)] sm:h-[188px]"
              >
                <div
                  aria-hidden
                  className="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"
                  style={{ background: node.accent }}
                />
                <div className="relative flex items-start justify-between">
                  <span className="size-2 rounded-full" style={{ backgroundColor: node.accent }} />
                  <ArrowUpRight className="size-3.5 text-[var(--color-ink-faint)] opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                </div>
                <div className="relative">
                  <h3 className="text-[14.5px] font-medium text-[var(--color-ink)] sm:text-[15px]">
                    {node.name}
                  </h3>
                  <p className="mt-1.5 line-clamp-2 text-[12.5px] leading-snug text-[var(--color-ink-faint)] transition-colors duration-300 group-hover:text-[var(--color-ink-muted)]">
                    {node.description}
                  </p>
                  <div className="mt-2.5 flex flex-wrap gap-1 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                    {node.tech.slice(0, 2).map((t) => (
                      <span
                        key={t}
                        className="rounded-full bg-white/[0.06] px-1.5 py-0.5 text-[10px] text-[var(--color-ink-faint)]"
                      >
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              </Link>
            </RevealItem>
          ))}
        </RevealGroup>
      </Container>
    </section>
  );
}
