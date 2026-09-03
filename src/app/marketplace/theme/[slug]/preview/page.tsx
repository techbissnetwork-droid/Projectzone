import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Container } from "@/components/ui/container";
import { LivePreview } from "@/components/marketplace/live-preview";
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
  if (!product) return { title: "Preview Not Found" };
  return {
    title: `${product.name} — Live Preview`,
    description: `Interactively preview ${product.name} across desktop, tablet and mobile.`,
  };
}

export default async function ThemePreviewPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  return (
    <section className="pb-24 pt-36 sm:pb-32 sm:pt-40 md:pt-44">
      <Container wide>
        <LivePreview product={product} />
      </Container>
    </section>
  );
}
