import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("mobile-app-development")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function MobileAppDevelopmentPage() {
  return (
    <ServiceDetailLayout
      slug="mobile-app-development"
      visual="phone"
      relatedSlugs={["web-application-development", "ssl-security", "business-digitization"]}
      processStepIndexes={[1, 2, 4]}
      processIntro="Mobile experiences live or die on polish and reliability, so design fidelity, careful engineering, and a coordinated launch across app stores all matter equally."
    />
  );
}
