import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { BrandStudio } from "@/components/dashboard/brand-studio";
import { getSite } from "@/lib/data/dashboard";
import { getProduct } from "@/lib/data/marketplace";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ siteId: string }>;
}): Promise<Metadata> {
  const { siteId } = await params;
  const site = getSite(siteId);
  return {
    title: site ? `Brand Studio — ${site.name}` : "Brand Studio",
    description: "Customize your website's brand, business details and content in real time.",
  };
}

export default async function BrandStudioPage({
  params,
}: {
  params: Promise<{ siteId: string }>;
}) {
  const { siteId } = await params;
  const site = getSite(siteId);
  if (!site) notFound();

  const theme = getProduct(site.themeSlug);

  return <BrandStudio site={site} themeName={theme?.name ?? site.themeSlug} />;
}
