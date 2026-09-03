import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("website-development")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function WebsiteDevelopmentPage() {
  return (
    <ServiceDetailLayout
      slug="website-development"
      visual="browser"
      relatedSlugs={["domain-hosting", "ssl-security", "business-email"]}
      processStepIndexes={[0, 1, 2]}
      processIntro="A premium website starts with the same discipline as any serious build: understand the goal, design a system around it, then engineer it with performance and clarity in mind."
    />
  );
}
