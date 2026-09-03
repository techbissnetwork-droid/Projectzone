import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Button } from "@/components/ui/button";
import { products, getProduct } from "@/lib/data/marketplace";
import { formatPrice } from "@/lib/utils";
import { FullPreview } from "@/components/marketplace/full-preview";

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
  return { title: `Live Preview — ${product.name}`, description: product.tagline };
}

export default async function ProductPreviewPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  return (
    <section className="border-b border-line-dark pt-32 pb-20 sm:pt-40">
      <Container wide>
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <Link
              href={`/marketplace/product/${product.slug}`}
              className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
            >
              <ArrowLeft className="size-3.5" />
              Back to {product.name}
            </Link>
            <h1 className="mt-4 text-[26px] font-medium tracking-tight text-paper-50">
              Live Preview
            </h1>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-[18px] font-medium text-paper-50">
              {formatPrice(product.priceCents)}
            </span>
            <Button href={`/marketplace/checkout/${product.slug}`} arrow>
              Buy Theme
            </Button>
          </div>
        </div>

        <div className="mt-10">
          <FullPreview product={product} />
        </div>
      </Container>
    </section>
  );
}
