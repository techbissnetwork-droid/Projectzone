import type { Metadata } from "next";
import Link from "next/link";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { categoryGroups, getProductsByGroup } from "@/lib/data/marketplace";

export const metadata: Metadata = {
  title: "Categories",
  description: "Browse every marketplace category — website, e-commerce, application and digital product themes.",
};

export default function CategoriesPage() {
  return (
    <section className="pb-24 pt-36 sm:pb-32 sm:pt-40 md:pt-44">
      <Container>
        <Reveal className="mx-auto max-w-[640px] text-center">
          <Eyebrow className="justify-center">Marketplace</Eyebrow>
          <h1 className="mt-6 text-balance text-[36px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[52px]">
            Every category, at a glance.
          </h1>
          <p className="mt-5 text-pretty text-[15.5px] leading-relaxed text-[var(--color-ink-muted)]">
            Four product groups, dozens of categories. Pick the one closest to your business
            and jump straight to it.
          </p>
        </Reveal>

        <div className="mt-20 flex flex-col gap-16">
          {categoryGroups.map((group, i) => {
            const count = getProductsByGroup(group.key).length;
            return (
              <Reveal key={group.key} delay={i * 0.05}>
                <div className="rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 sm:p-10">
                  <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                      <Eyebrow index={`0${i + 1}`}>{group.title}</Eyebrow>
                      <p className="mt-4 max-w-[52ch] text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
                        {group.description}
                      </p>
                    </div>
                    <span className="shrink-0 font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
                      {count} products
                    </span>
                  </div>

                  <RevealGroup className="mt-8 flex flex-wrap gap-2.5">
                    {group.categories.map((category) => (
                      <RevealItem key={category}>
                        <Link
                          href={`/marketplace?group=${group.key}&category=${encodeURIComponent(category)}#catalog`}
                          className="inline-flex items-center rounded-full border border-[var(--color-border-strong)] px-4 py-2 text-[13.5px] font-medium text-[var(--color-ink-muted)] transition-colors duration-200 hover:border-[var(--color-ink)] hover:text-[var(--color-ink)]"
                        >
                          {category}
                        </Link>
                      </RevealItem>
                    ))}
                  </RevealGroup>
                </div>
              </Reveal>
            );
          })}
        </div>
      </Container>
    </section>
  );
}
