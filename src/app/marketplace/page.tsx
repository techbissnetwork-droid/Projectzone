import type { Metadata } from "next";
import Link from "next/link";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { ProductCard } from "@/components/marketplace/product-card";
import { MarketplaceBrowser } from "@/components/marketplace/marketplace-browser";
import { products, categories } from "@/lib/data/marketplace";

export const metadata: Metadata = {
  title: "Marketplace",
  description:
    "Professionally built website themes, e-commerce stores, applications and digital products. Preview it, buy it, brand it, launch it.",
};

const trending = products.filter((p) => p.badge === "Trending" || p.badge === "Best Seller").slice(0, 4);

export default function MarketplacePage() {
  return (
    <>
      <section className="relative overflow-hidden border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-x-0 top-0 h-[420px] bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(200,161,101,0.12),rgba(0,0,0,0))]"
        />
        <Container wide className="relative">
          <Eyebrow>The Marketplace</Eyebrow>
          <h1 className="mt-6 max-w-2xl text-balance text-[38px] font-medium leading-[1.06] tracking-[-0.02em] text-paper-50 sm:text-[58px]">
            Start with something great.
          </h1>
          <p className="mt-6 max-w-xl text-[15px] leading-relaxed text-paper-50/55 sm:text-[17px]">
            Professionally built digital themes and products. Choose one,
            make it yours, and launch faster.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button href="#categories" arrow>
              Explore Marketplace
            </Button>
            <Button href="/services" variant="ghost">
              Build From Scratch
            </Button>
          </div>
        </Container>
      </section>

      <Section>
        <Reveal className="flex items-end justify-between">
          <h2 className="text-[13px] font-medium uppercase tracking-wide text-paper-50/40">
            Trending Now
          </h2>
          <Link href="#categories" className="text-[13px] font-medium text-paper-50/60 hover:text-paper-50">
            View all
          </Link>
        </Reveal>
        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {trending.map((p) => (
            <ProductCard key={p.slug} product={p} />
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal>
          <h2 className="text-[13px] font-medium uppercase tracking-wide text-paper-50/40">
            Browse by Category
          </h2>
        </Reveal>
        <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {categories.map((c) => (
            <div key={c.group} className="rounded-xl border border-line-dark bg-ink-950/40 p-5">
              <div className="text-[14px] font-medium text-paper-50">{c.group}</div>
              <div className="mt-3 flex flex-wrap gap-1.5">
                {c.items.slice(0, 6).map((item) => (
                  <span
                    key={item.slug}
                    className="rounded-full border border-line-dark px-2.5 py-1 text-[11px] text-paper-50/50"
                  >
                    {item.name}
                  </span>
                ))}
                {c.items.length > 6 && (
                  <span className="rounded-full border border-line-dark px-2.5 py-1 text-[11px] text-paper-50/35">
                    +{c.items.length - 6} more
                  </span>
                )}
              </div>
            </div>
          ))}
        </div>
      </Section>

      <Section className="border-t border-line-dark">
        <MarketplaceBrowser />
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal className="flex flex-col items-center gap-6 rounded-3xl border border-line-dark bg-ink-950/40 p-10 text-center sm:p-16">
          <Eyebrow className="justify-center">Sell on TECHBISS</Eyebrow>
          <h2 className="max-w-lg text-[26px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[32px]">
            Built something great? Sell it to businesses on TECHBISS.
          </h2>
          <Button href="/marketplace/sell" arrow>
            Become a Seller
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
