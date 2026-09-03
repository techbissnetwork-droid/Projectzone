import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetail } from "@/components/concept-2/ServiceDetail";

const slug = "business-email";
const service = serviceBySlug(slug);

export const metadata: Metadata = {
  title: service?.title ?? "Service",
  description: service?.shortDescription ?? "A TECHBISS service.",
};

export default function BusinessEmailPage() {
  return <ServiceDetail slug={slug} index="07" />;
}
