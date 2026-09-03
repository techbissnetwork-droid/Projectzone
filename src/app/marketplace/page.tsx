import type { Metadata } from "next";
import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ThemeCard } from "@/components/marketplace/theme-card";
import { CatalogBrowser } from "@/components/marketplace/catalog-browser";
import { categoryGroups, getProductsByGroup, products } from "@/lib/data/marketplace";

export const metadata: Metadata = {
  title: "Marketplace",
  description:
    "Professionally built digital themes and products. Choose one, make it yours and launch faster.",
};

const spotlight = products.filter(
  (p) => p.badges.includes("Featured") && ["market-multivendor", "meridian-health"].includes(p.slug),
);

export default async function MarketplacePage({
  searchParams,
}: {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
}) {
  const params = await searchParams;
  const first = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const initialSort = first(params.sort);
  const initialGroup = first(params.group);
  const initialCategory = first(params.category);

  return (
    <>
      <section className="pt-36 pb-20 sm:pt-40 sm:pb-28 md:pt-44">
        <Container>
          <Reveal className="mx-auto max-w-[720px] text-center">
            <Eyebrow className="justify-center">TECHBISS Marketplace</Eyebrow>
            <h1 className="mt-6 text-balance font-serif-display text-[42px] leading-[1.05] tracking-[-0.01em] sm:text-[64px] md:text-[74px]">
              Start with something great.
            </h1>
            <p className="mt-6 text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)] sm:text-[18px]">
              Professionally built digital themes and products. Choose one, make it yours
              and launch faster.
            </p>
          </Reveal>

          <Reveal delay={0.1} className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="#catalog" variant="primary" size="lg">
              Explore Marketplace
            </Button>
            <Button href="/contact" variant="secondary" size="lg">
              Build From Scratch
            </Button>
          </Reveal>
        </Container>
      </section>

      {spotlight.length > 0 && (
        <section className="pb-24 sm:pb-32">
          <Container>
            <Reveal>
              <Eyebrow>Spotlight</Eyebrow>
              <h2 className="mt-6 max-w-[560px] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
                A closer look at what&rsquo;s launching best.
              </h2>
            </Reveal>

            <RevealGroup className="mt-10 grid gap-6 lg:grid-cols-2">
              {spotlight.map((product) => (
                <RevealItem key={product.slug}>
                  <ThemeCard product={product} size="lg" />
                </RevealItem>
              ))}
            </RevealGroup>
          </Container>
        </section>
      )}

      <section className="border-t border-[var(--color-border)] pb-24 pt-24 sm:pb-32 sm:pt-32">
        <Container>
          <Reveal className="mx-auto max-w-[640px] text-center">
            <Eyebrow className="justify-center">Browse by Category</Eyebrow>
            <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
              Four ways to launch.
            </h2>
          </Reveal>

          <div className="mt-16 flex flex-col gap-20">
            {categoryGroups.map((group) => {
              const groupProducts = getProductsByGroup(group.key).slice(0, 6);
              return (
                <div key={group.key}>
                  <Reveal className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                      <h3 className="text-[22px] font-medium tracking-[-0.01em] sm:text-[26px]">
                        {group.title}
                      </h3>
                      <p className="mt-2 max-w-[48ch] text-[14.5px] leading-relaxed text-[var(--color-ink-muted)]">
                        {group.description}
                      </p>
                    </div>
                    <Link
                      href={`/marketplace?group=${group.key}#catalog`}
                      className="group inline-flex shrink-0 items-center gap-1.5 text-[13.5px] font-medium text-[var(--color-ink)]"
                    >
                      View all in {group.title}
                      <ArrowUpRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                    </Link>
                  </Reveal>

                  <div className="mt-8 -mx-6 flex snap-x snap-mandatory gap-5 overflow-x-auto scrollbar-none px-6 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                    {groupProducts.map((product) => (
                      <div
                        key={product.slug}
                        className="w-[240px] shrink-0 snap-start sm:w-auto"
                      >
                        <ThemeCard product={product} />
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        </Container>
      </section>

      <section id="catalog" className="scroll-mt-24 border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <CatalogBrowser
            initialSort={initialSort}
            initialGroup={initialGroup}
            initialCategory={initialCategory}
          />
        </Container>
      </section>
    </>
  );
}
