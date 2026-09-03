import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ShieldCheck } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal } from "@/components/ui/reveal";
import { ThemeVisual } from "@/components/marketplace/theme-visual";
import { CheckoutForm } from "@/components/marketplace/checkout-form";
import { getProduct, products } from "@/lib/data/marketplace";

export async function generateStaticParams() {
  return products.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) return { title: "Checkout Not Found" };
  return {
    title: `Checkout — ${product.name}`,
    description: `Complete your purchase of ${product.name}.`,
  };
}

export default async function CheckoutPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  return (
    <section className="pb-24 pt-36 sm:pb-32 sm:pt-40 md:pt-44">
      <Container>
        <Reveal className="mx-auto max-w-[560px] text-center">
          <Eyebrow className="justify-center">Checkout</Eyebrow>
          <h1 className="mt-6 text-balance text-[32px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[42px]">
            You&rsquo;re one step from launching.
          </h1>
        </Reveal>

        <div className="mx-auto mt-14 grid max-w-[900px] gap-8 lg:grid-cols-[320px_1fr]">
          <Reveal>
            <div className="rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6">
              <div className="aspect-[4/3] overflow-hidden rounded-xl">
                <ThemeVisual product={product} className="h-full" />
              </div>
              <h3 className="mt-5 text-[16px] font-medium">{product.name}</h3>
              <p className="mt-1 text-[13px] text-[var(--color-ink-faint)]">
                {product.category} · {product.industry}
              </p>

              <div className="mt-5 flex items-center justify-between border-t border-[var(--color-border)] pt-5">
                <span className="text-[13.5px] text-[var(--color-ink-muted)]">
                  Single business-use license
                </span>
                <span className="text-[16px] font-medium">
                  {product.free ? "Free" : `$${product.price}`}
                </span>
              </div>

              <div className="mt-5 flex items-start gap-2 text-[12.5px] leading-relaxed text-[var(--color-ink-faint)]">
                <ShieldCheck className="mt-0.5 size-3.5 shrink-0" />
                Includes 12 months of free updates and 90 days of setup support.
              </div>
            </div>
          </Reveal>

          <Reveal delay={0.08}>
            <CheckoutForm product={product} />
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
