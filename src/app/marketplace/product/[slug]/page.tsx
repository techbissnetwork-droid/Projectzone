import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, Star, Check, ShieldCheck, RefreshCcw, LifeBuoy } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Section, Eyebrow } from "@/components/ui/section";
import { Reveal } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { ProductPreviewPanel } from "@/components/marketplace/product-preview-panel";
import { ProductCard } from "@/components/marketplace/product-card";
import { products, getProduct } from "@/lib/data/marketplace";
import { formatPrice } from "@/lib/utils";

export function generateStaticParams() {
  return products.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) return {};
  return { title: product.name, description: product.tagline };
}

export default async function ProductDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  const related = products
    .filter((p) => p.slug !== product.slug && p.category === product.category)
    .slice(0, 3);

  return (
    <>
      <section className="border-b border-line-dark pt-32 pb-10 sm:pt-40">
        <Container wide>
          <Link
            href="/marketplace"
            className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
          >
            <ArrowLeft className="size-3.5" />
            Marketplace
          </Link>

          <div className="mt-6 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
              <Eyebrow>
                {product.category} · {product.industry}
              </Eyebrow>
              <h1 className="mt-4 max-w-2xl text-balance text-[32px] font-medium leading-[1.08] tracking-[-0.02em] text-paper-50 sm:text-[46px]">
                {product.name}
              </h1>
              <p className="mt-4 max-w-lg text-[15px] leading-relaxed text-paper-50/55">
                {product.tagline}
              </p>
              <div className="mt-4 flex items-center gap-1.5 text-[13px] text-paper-50/50">
                <Star className="size-4 fill-gold-400 text-gold-400" />
                {product.rating}
                <span className="text-paper-50/30">({product.reviews} reviews)</span>
              </div>
            </div>

            <div className="flex shrink-0 flex-col gap-3 rounded-2xl border border-line-dark bg-ink-900/50 p-6 sm:w-64">
              <div className="text-[28px] font-medium text-paper-50">
                {formatPrice(product.priceCents)}
              </div>
              <Button href={`/marketplace/checkout/${product.slug}`} arrow className="w-full justify-center">
                Buy Theme
              </Button>
              <Button
                href={`/marketplace/product/${product.slug}/preview`}
                variant="ghost"
                className="w-full justify-center"
              >
                Live Preview
              </Button>
            </div>
          </div>
        </Container>
      </section>

      <Section className="!pt-10">
        <Reveal>
          <ProductPreviewPanel product={product} />
        </Reveal>
      </Section>

      <Section className="border-t border-line-dark">
        <div className="grid gap-12 lg:grid-cols-[1.3fr_1fr]">
          <div className="flex flex-col gap-12">
            <Reveal>
              <Eyebrow>Overview</Eyebrow>
              <p className="mt-4 text-[16px] leading-relaxed text-paper-50/75">
                {product.description}
              </p>
            </Reveal>

            <Reveal delay={0.06}>
              <Eyebrow>Features</Eyebrow>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {product.features.map((f) => (
                  <div key={f} className="flex items-start gap-2.5">
                    <Check className="mt-0.5 size-4 shrink-0 text-gold-400" />
                    <span className="text-[14px] text-paper-50/70">{f}</span>
                  </div>
                ))}
              </div>
            </Reveal>

            <Reveal delay={0.12}>
              <Eyebrow>Included Pages</Eyebrow>
              <div className="mt-4 flex flex-wrap gap-2">
                {product.pages.map((p) => (
                  <span
                    key={p}
                    className="rounded-full border border-line-dark-strong px-3.5 py-1.5 text-[12.5px] text-paper-50/70"
                  >
                    {p}
                  </span>
                ))}
              </div>
            </Reveal>

            <Reveal delay={0.18}>
              <Eyebrow>Technology</Eyebrow>
              <div className="mt-4 flex flex-wrap gap-2">
                {product.technology.map((t) => (
                  <span
                    key={t}
                    className="font-mono-label rounded-full border border-line-dark px-3 py-1.5 text-[11px] uppercase text-paper-50/50"
                  >
                    {t}
                  </span>
                ))}
              </div>
            </Reveal>
          </div>

          <Reveal delay={0.1} className="flex flex-col gap-3">
            {[
              { icon: ShieldCheck, label: "License", value: product.license },
              { icon: RefreshCcw, label: "Updates", value: product.updates },
              { icon: LifeBuoy, label: "Support", value: product.support },
            ].map((item) => (
              <div key={item.label} className="flex items-start gap-3 rounded-xl border border-line-dark bg-ink-900/40 p-5">
                <item.icon className="mt-0.5 size-4 shrink-0 text-gold-400" strokeWidth={1.75} />
                <div>
                  <div className="text-[12px] uppercase tracking-wide text-paper-50/40">
                    {item.label}
                  </div>
                  <div className="mt-1 text-[13.5px] text-paper-50/75">{item.value}</div>
                </div>
              </div>
            ))}

            <div className="mt-2 rounded-xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-6">
              <div className="text-[15px] font-medium text-paper-50">Need help making it yours?</div>
              <p className="mt-2 text-[13px] leading-relaxed text-paper-50/55">
                TECHBISS can customize this product with your branding,
                content and custom functionality.
              </p>
              <Button href="/contact" variant="ghost" className="mt-4 w-full justify-center">
                Customize My Theme
              </Button>
            </div>
          </Reveal>
        </div>
      </Section>

      {related.length > 0 && (
        <Section className="border-t border-line-dark bg-ink-900/40">
          <Reveal>
            <Eyebrow>Related Products</Eyebrow>
          </Reveal>
          <div className="mt-8 grid gap-5 sm:grid-cols-3">
            {related.map((p) => (
              <ProductCard key={p.slug} product={p} />
            ))}
          </div>
        </Section>
      )}
    </>
  );
}
