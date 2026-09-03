import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("ssl-security")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function SslSecurityPage() {
  return (
    <ServiceDetailLayout
      slug="ssl-security"
      visual="shield"
      relatedSlugs={["domain-hosting", "business-email", "web-application-development"]}
      processStepIndexes={[0, 3, 5]}
      processIntro="Security isn't a checkbox we add before launch — it's assessed from day one, verified through testing, and monitored continuously after your systems go live."
    />
  );
}
