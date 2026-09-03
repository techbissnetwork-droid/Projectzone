import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("web-application-development")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function WebApplicationDevelopmentPage() {
  return (
    <ServiceDetailLayout
      slug="web-application-development"
      visual="dashboard"
      relatedSlugs={["business-digitization", "ssl-security", "website-development"]}
      processStepIndexes={[0, 2, 3]}
      processIntro="Custom platforms carry more risk than marketing sites, so we lean harder on discovery, architecture, and testing before anything reaches your team or your customers."
    />
  );
}
