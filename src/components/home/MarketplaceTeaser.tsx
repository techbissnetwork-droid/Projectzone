"use client";

import Link from "next/link";
import { ArrowRight, Download, Star } from "lucide-react";
import { motion } from "framer-motion";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { revealItem, RevealGroup } from "@/components/ui/Reveal";
import { products } from "@/lib/data/products";
import { formatCurrency } from "@/lib/utils";

export function MarketplaceTeaser() {
  const featured = products.filter((p) => p.featured).slice(0, 3);

  return (
    <Section>
      <Container>
        <div className="flex flex-col items-center justify-between gap-6 sm:flex-row sm:items-end">
          <SectionHeading
            align="left"
            eyebrow="Marketplace"
            title="Launch-ready platforms, not just templates."
            description="Browse, preview and purchase premium websites and themes — then deploy in minutes with the Advanced Installer."
            className="sm:items-start sm:text-left"
          />
          <Button href="/marketplace" variant="outline" icon={<ArrowRight className="h-4 w-4" />} className="shrink-0">
            Browse all products
          </Button>
        </div>

        <RevealGroup className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {featured.map((product) => (
            <motion.div key={product.slug} variants={revealItem}>
              <Link
                href={`/marketplace/${product.slug}`}
                className="focus-ring group flex h-full flex-col overflow-hidden rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) transition-all duration-300 hover:-translate-y-1 hover:border-(--color-border-strong) hover:shadow-lg"
              >
                <div
                  className="relative flex h-40 items-center justify-center overflow-hidden"
                  style={{ background: `linear-gradient(135deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
                >
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.25),transparent_60%)]" />
                  <span className="text-3xl font-semibold tracking-tight text-white/90">{product.name.slice(0, 1)}</span>
                  <Badge className="absolute left-3 top-3 border-white/20 bg-black/25 text-white backdrop-blur">
                    {product.category}
                  </Badge>
                </div>
                <div className="flex flex-1 flex-col p-5">
                  <h3 className="text-base font-medium text-(--color-ink)">{product.name}</h3>
                  <p className="mt-1.5 text-sm text-(--color-ink-muted) line-clamp-2">{product.tagline}</p>
                  <div className="mt-4 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="text-base font-medium text-(--color-ink)">{formatCurrency(product.price)}</span>
                      {product.originalPrice && (
                        <span className="text-xs text-(--color-ink-faint) line-through">
                          {formatCurrency(product.originalPrice)}
                        </span>
                      )}
                    </div>
                    <span className="flex items-center gap-1 text-xs text-(--color-ink-muted)">
                      <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> {product.rating}
                    </span>
                  </div>
                </div>
              </Link>
            </motion.div>
          ))}
        </RevealGroup>

        <div className="mt-8 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-xs text-(--color-ink-faint)">
          <span className="inline-flex items-center gap-1.5">
            <Download className="h-3.5 w-3.5" /> 34,000+ deployments launched
          </span>
          <span>Advanced Installer with migration &amp; auto URL detection included</span>
        </div>
      </Container>
    </Section>
  );
}
