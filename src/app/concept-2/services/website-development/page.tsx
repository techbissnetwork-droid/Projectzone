import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetail } from "@/components/concept-2/ServiceDetail";

const slug = "website-development";
const service = serviceBySlug(slug);

export const metadata: Metadata = {
  title: service?.title ?? "Service",
  description: service?.shortDescription ?? "A TECHBISS service.",
};

export default function WebsiteDevelopmentPage() {
  return <ServiceDetail slug={slug} index="01" />;
}
