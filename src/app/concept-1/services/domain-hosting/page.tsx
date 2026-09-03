import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("domain-hosting")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function DomainHostingPage() {
  return (
    <ServiceDetailLayout
      slug="domain-hosting"
      visual="server"
      relatedSlugs={["ssl-security", "business-email", "website-development"]}
      processStepIndexes={[0, 2, 4]}
      processIntro="Getting your foundation right — domain, DNS, and hosting — prevents the fragile setups that cause outages and slow load times later. We configure it correctly the first time."
    />
  );
}
