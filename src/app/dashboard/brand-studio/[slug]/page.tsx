import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { products, getProduct } from "@/lib/data/marketplace";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { BrandStudioEditor } from "@/components/brand-studio/brand-studio-editor";

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
  return { title: `Brand Studio — ${product.name}` };
}

export default async function BrandStudioProductPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  return (
    <div>
      <DashboardPageHeader
        title="Brand Studio"
        subtitle={`Turn ${product.name} into your business — changes update the preview instantly.`}
      />
      <BrandStudioEditor product={product} />
    </div>
  );
}
