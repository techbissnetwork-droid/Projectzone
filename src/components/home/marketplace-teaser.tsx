import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ThemeCard } from "@/components/marketplace/theme-card";
import { products } from "@/lib/data/marketplace";

const featured = products.filter((p) => p.badges.includes("Featured")).slice(0, 4);

export function MarketplaceTeaser() {
  return (
    <section className="py-24 sm:py-32">
      <Container>
        <div className="flex flex-col items-end justify-between gap-6 sm:flex-row">
          <Reveal className="max-w-[560px]">
            <Eyebrow>TECHBISS Marketplace</Eyebrow>
            <h2 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[44px]">
              Start with something great.
            </h2>
            <p className="mt-4 max-w-[46ch] text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
              Professionally built digital themes and products. Choose one, make it yours
              and launch faster.
            </p>
          </Reveal>
          <Reveal delay={0.1} className="hidden shrink-0 sm:block">
            <Button href="/marketplace" variant="secondary">
              Explore Marketplace
            </Button>
          </Reveal>
        </div>

        <RevealGroup className="mt-12 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
          {featured.map((product) => (
            <RevealItem key={product.slug}>
              <ThemeCard product={product} />
            </RevealItem>
          ))}
        </RevealGroup>

        <Reveal className="mt-10 sm:hidden">
          <Button href="/marketplace" variant="secondary" className="w-full">
            Explore Marketplace
          </Button>
        </Reveal>
      </Container>
    </section>
  );
}
