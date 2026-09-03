"use client";

import Link from "next/link";
import { ArrowRight, ShoppingBag, Trash2 } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { PageHero } from "@/components/ui/PageHero";
import { useCart } from "@/lib/cart-context";
import { formatCurrency } from "@/lib/utils";

export default function CartPage() {
  const { items, remove, subtotal } = useCart();

  return (
    <>
      <PageHero eyebrow="Your Cart" title="Review your selection" description="Purchases include full source, lifetime updates and one-click deployment via the Advanced Installer." />

      <Section size="tight">
        <Container size="narrow">
          {items.length === 0 ? (
            <div className="flex flex-col items-center gap-4 rounded-(--radius-lg) border border-dashed border-(--color-border-strong) py-20 text-center">
              <ShoppingBag className="h-8 w-8 text-(--color-ink-faint)" />
              <p className="text-lg font-medium text-(--color-ink)">Your cart is empty</p>
              <p className="max-w-xs text-sm text-(--color-ink-muted)">
                Browse the marketplace to find your next platform, theme or template.
              </p>
              <Button href="/marketplace" variant="secondary" className="mt-2">
                Browse Marketplace
              </Button>
            </div>
          ) : (
            <div className="flex flex-col gap-8">
              <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-lg) border border-(--color-border)">
                {items.map(({ product }) => (
                  <div key={product.slug} className="flex items-center gap-4 p-5">
                    <div
                      className="flex h-16 w-16 shrink-0 items-center justify-center rounded-(--radius-md) text-lg font-semibold text-white"
                      style={{ background: `linear-gradient(135deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
                    >
                      {product.name.slice(0, 1)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <Link href={`/marketplace/${product.slug}`} className="focus-ring text-sm font-medium text-(--color-ink) hover:underline">
                        {product.name}
                      </Link>
                      <p className="mt-0.5 truncate text-xs text-(--color-ink-faint)">{product.tagline}</p>
                    </div>
                    <span className="shrink-0 text-sm font-medium text-(--color-ink)">{formatCurrency(product.price)}</span>
                    <button
                      type="button"
                      onClick={() => remove(product.slug)}
                      aria-label={`Remove ${product.name}`}
                      className="focus-ring flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-(--color-ink-faint) transition-colors hover:bg-(--color-surface-raised) hover:text-red-400"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                ))}
              </div>

              <div className="flex flex-col gap-4 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                <div className="flex items-center justify-between text-sm text-(--color-ink-muted)">
                  <span>Subtotal</span>
                  <span className="text-(--color-ink)">{formatCurrency(subtotal)}</span>
                </div>
                <div className="flex items-center justify-between text-sm text-(--color-ink-muted)">
                  <span>Taxes</span>
                  <span className="text-(--color-ink)">Calculated at checkout</span>
                </div>
                <div className="flex items-center justify-between border-t border-(--color-border) pt-4 text-base font-medium text-(--color-ink)">
                  <span>Total</span>
                  <span>{formatCurrency(subtotal)}</span>
                </div>
                <Button href="/marketplace/checkout" variant="secondary" size="lg" icon={<ArrowRight className="h-4 w-4" />}>
                  Proceed to Checkout
                </Button>
              </div>
            </div>
          )}
        </Container>
      </Section>
    </>
  );
}
