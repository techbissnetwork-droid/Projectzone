import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/section";
import { products, getProduct } from "@/lib/data/marketplace";
import { LaunchChecklist } from "@/components/marketplace/launch-checklist";

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
  return { title: `Launch — ${product.name}` };
}

export default async function LaunchPage({
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
          href={`/dashboard/brand-studio/${product.slug}`}
          className="inline-flex items-center gap-1.5 text-[13px] text-paper-50/45 hover:text-paper-50"
        >
          <ArrowLeft className="size-3.5" />
          Back to Brand Studio
        </Link>
        <Eyebrow className="mt-8">Launch Center</Eyebrow>
        <h1 className="mt-4 text-[32px] font-medium tracking-tight text-paper-50 sm:text-[40px]">
          Launch {product.name}.
        </h1>
        <p className="mt-4 max-w-lg text-[15px] leading-relaxed text-paper-50/55">
          A guided, visual checklist covering everything your website needs
          before it goes live.
        </p>

        <div className="mt-10">
          <LaunchChecklist product={product} />
        </div>
      </Container>
    </section>
  );
}
