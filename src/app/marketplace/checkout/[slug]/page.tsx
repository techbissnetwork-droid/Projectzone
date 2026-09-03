import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { Container } from "@/components/ui/container";
import { products, getProduct } from "@/lib/data/marketplace";
import { CheckoutFlow } from "@/components/marketplace/checkout-flow";

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
  return { title: `Checkout — ${product.name}` };
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
    <section className="border-b border-line-dark pt-32 pb-24 sm:pt-40">
      <Container>
        <Link
          href={`/marketplace/product/${product.slug}`}
          className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
        >
          <ArrowLeft className="size-3.5" />
          Back to {product.name}
        </Link>
        <h1 className="mt-6 text-[30px] font-medium tracking-tight text-paper-50 sm:text-[38px]">
          Checkout
        </h1>
        <div className="mt-10">
          <CheckoutFlow product={product} />
        </div>
      </Container>
    </section>
  );
}
