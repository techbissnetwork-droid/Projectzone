import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ServiceDetailTemplate } from "@/components/services/ServiceDetailTemplate";
import { detailedServices, getServiceBySlug } from "@/lib/data/services";

export function generateStaticParams() {
  return detailedServices.map((service) => ({ slug: service.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const service = getServiceBySlug(slug);
  if (!service) return {};

  return {
    title: service.fullName,
    description: service.heroDescription,
    openGraph: {
      title: `${service.fullName} — TECHBISS`,
      description: service.heroDescription,
    },
  };
}

export default async function ServiceDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const service = getServiceBySlug(slug);

  if (!service || !service.hasDetailPage) {
    notFound();
  }

  return <ServiceDetailTemplate service={service} />;
}
