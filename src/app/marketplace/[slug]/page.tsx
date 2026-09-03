import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { Check, ChevronRight, Star } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Badge } from "@/components/ui/Badge";
import { Reveal } from "@/components/ui/Reveal";
import { ProductPreview } from "@/components/marketplace/ProductPreview";
import { ProductActions } from "@/components/marketplace/ProductActions";
import { ProductCard } from "@/components/marketplace/ProductCard";
import { RevealGroup } from "@/components/ui/Reveal";
import { products } from "@/lib/data/products";
import { formatCurrency } from "@/lib/utils";

export function generateStaticParams() {
  return products.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata(props: PageProps<"/marketplace/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  const product = products.find((p) => p.slug === slug);
  if (!product) return {};
  return { title: product.name, description: product.tagline };
}

export default async function ProductPage(props: PageProps<"/marketplace/[slug]">) {
  const { slug } = await props.params;
  const product = products.find((p) => p.slug === slug);
  if (!product) notFound();

  const related = products.filter((p) => p.category === product.category && p.slug !== product.slug).slice(0, 3);

  return (
    <>
      <Section size="tight">
        <Container size="wide">
          <nav className="mb-8 flex items-center gap-1.5 text-sm text-(--color-ink-faint)">
            <Link href="/marketplace" className="hover:text-(--color-ink-muted)">
              Marketplace
            </Link>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="text-(--color-ink-muted)">{product.category}</span>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="text-(--color-ink)">{product.name}</span>
          </nav>

          <div className="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-8">
            <div className="lg:col-span-8">
              <Reveal>
                <ProductPreview product={product} />
              </Reveal>

              <Reveal delay={0.1} className="mt-10">
                <h2 className="text-lg font-medium text-(--color-ink)">Overview</h2>
                <p className="mt-3 text-sm leading-relaxed text-(--color-ink-muted) sm:text-base">
                  {product.description}
                </p>
              </Reveal>

              <Reveal delay={0.15} className="mt-8">
                <h2 className="text-lg font-medium text-(--color-ink)">What&apos;s included</h2>
                <ul className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {product.features.map((f) => (
                    <li key={f} className="flex items-start gap-2.5 text-sm text-(--color-ink)">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-(--color-accent-2)" />
                      {f}
                    </li>
                  ))}
                </ul>
              </Reveal>

              <Reveal delay={0.2} className="mt-8">
                <h2 className="text-lg font-medium text-(--color-ink)">Built with</h2>
                <div className="mt-4 flex flex-wrap gap-2">
                  {product.stack.map((s) => (
                    <Badge key={s} variant="outline">
                      {s}
                    </Badge>
                  ))}
                </div>
              </Reveal>
            </div>

            <div className="lg:col-span-4">
              <Reveal delay={0.05} className="lg:sticky lg:top-24">
                <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                  <div className="flex flex-wrap gap-1.5">
                    <Badge variant="accent">{product.category}</Badge>
                    {product.new && <Badge variant="success">New</Badge>}
                  </div>
                  <h1 className="mt-4 text-2xl font-medium tracking-tight text-(--color-ink)">{product.name}</h1>
                  <p className="mt-1.5 text-sm text-(--color-ink-muted)">{product.tagline}</p>

                  <div className="mt-4 flex items-center gap-3 text-sm text-(--color-ink-muted)">
                    <span className="flex items-center gap-1">
                      <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                      <span className="font-medium text-(--color-ink)">{product.rating}</span>({product.reviews})
                    </span>
                    <span>·</span>
                    <span>{product.sales.toLocaleString()} sales</span>
                  </div>

                  <div className="mt-5 flex items-baseline gap-2 border-y border-(--color-border) py-5">
                    <span className="text-3xl font-medium text-(--color-ink)">{formatCurrency(product.price)}</span>
                    {product.originalPrice && (
                      <span className="text-sm text-(--color-ink-faint) line-through">
                        {formatCurrency(product.originalPrice)}
                      </span>
                    )}
                    <span className="text-xs text-(--color-ink-faint)">one-time</span>
                  </div>

                  <div className="mt-5">
                    <ProductActions product={product} />
                  </div>

                  <p className="mt-4 text-center text-xs text-(--color-ink-faint)">
                    14-day guarantee · Lifetime updates · Full source included
                  </p>
                </div>
              </Reveal>
            </div>
          </div>
        </Container>
      </Section>

      {related.length > 0 && (
        <Section theme="light">
          <Container>
            <h2 className="mb-8 text-2xl font-medium tracking-tight text-(--color-ink)">You might also like</h2>
            <RevealGroup className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((p) => (
                <ProductCard key={p.slug} product={p} />
              ))}
            </RevealGroup>
          </Container>
        </Section>
      )}
    </>
  );
}
