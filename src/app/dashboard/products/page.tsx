import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, Star } from "lucide-react";
import { ThemeVisual } from "@/components/marketplace/theme-visual";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { getProduct } from "@/lib/data/marketplace";
import { mySites } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "My Products",
  description: "The themes you've purchased and the sites they power.",
};

export default function ProductsPage() {
  const uniqueSlugs = Array.from(new Set(mySites.map((s) => s.themeSlug)));
  const owned = uniqueSlugs
    .map((slug) => ({ product: getProduct(slug), sites: mySites.filter((s) => s.themeSlug === slug) }))
    .filter((entry) => !!entry.product);

  return (
    <div className="flex flex-col gap-10">
      <PageHeader
        eyebrow="Platform"
        title="My Products"
        description="Themes you own, and the sites currently built on each one."
      />

      <div className="grid gap-5 sm:grid-cols-2">
        {owned.map(({ product, sites }) => {
          if (!product) return null;
          return (
            <Card key={product.slug} className="flex flex-col gap-4 !p-4">
              <div className="aspect-[16/10] w-full">
                <ThemeVisual product={product} className="h-full" />
              </div>
              <div>
                <div className="flex items-start justify-between gap-3">
                  <h3 className="text-[15px] font-medium text-[var(--color-ink)]">{product.name}</h3>
                  <span className="flex shrink-0 items-center gap-1 text-[12px] text-[var(--color-ink-faint)]">
                    <Star className="size-3 fill-[var(--color-gold)] text-[var(--color-gold)]" />
                    {product.rating}
                  </span>
                </div>
                <p className="mt-1 text-[13px] text-[var(--color-ink-faint)]">
                  {product.category} · {product.industry}
                </p>
              </div>

              <div className="flex flex-col gap-1.5 border-t border-[var(--color-border)] pt-3.5">
                <span className="font-mono-label text-[10.5px] uppercase text-[var(--color-ink-faint)]">
                  Used on
                </span>
                {sites.map((site) => (
                  <Link
                    key={site.id}
                    href={`/dashboard/brand-studio/${site.id}`}
                    className="flex items-center justify-between text-[13px] text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
                  >
                    <span>{site.name}</span>
                    <span className="inline-flex items-center gap-1 text-[var(--color-accent-ink)]">
                      Customize in Brand Studio
                      <ArrowRight className="size-3.5" strokeWidth={2} />
                    </span>
                  </Link>
                ))}
              </div>
            </Card>
          );
        })}
      </div>

      <Card className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <p className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
            Need more than DIY?
          </p>
          <p className="mt-1.5 text-[15px] font-medium text-[var(--color-ink)]">
            Need help making it yours?
          </p>
          <p className="mt-1 text-[13px] text-[var(--color-ink-muted)]">
            Our team can customize any theme end-to-end — brand, content and integrations included.
          </p>
        </div>
        <Link
          href="/services/website-development"
          className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
        >
          Talk to our team
          <ArrowRight className="size-3.5" strokeWidth={2} />
        </Link>
      </Card>
    </div>
  );
}
