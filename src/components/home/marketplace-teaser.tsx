"use client";

import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { ProductCard } from "@/components/marketplace/product-card";
import { products } from "@/lib/data/marketplace";
import { motion } from "framer-motion";

const featured = products.filter((p) =>
  ["restaurant-pro", "nova-fashion", "orbit-booking", "atlas-admin"].includes(p.slug)
);

export function MarketplaceTeaser() {
  return (
    <section className="border-b border-line-dark py-24 sm:py-32">
      <Container wide>
        <Reveal className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <div>
            <Eyebrow>The Marketplace</Eyebrow>
            <h2 className="mt-5 max-w-xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
              Start with something great, already built.
            </h2>
            <p className="mt-4 max-w-lg text-[15px] leading-relaxed text-paper-50/55">
              Professionally designed themes, applications and digital
              products. Preview it, buy it, make it yours.
            </p>
          </div>
          <Button href="/marketplace" arrow variant="ghost" className="shrink-0">
            Explore Marketplace
          </Button>
        </Reveal>

        <RevealGroup className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {featured.map((product) => (
            <motion.div key={product.slug} variants={revealItem}>
              <ProductCard product={product} />
            </motion.div>
          ))}
        </RevealGroup>
      </Container>
    </section>
  );
}
