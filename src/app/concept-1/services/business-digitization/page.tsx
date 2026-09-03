import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("business-digitization")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function BusinessDigitizationPage() {
  return (
    <ServiceDetailLayout
      slug="business-digitization"
      visual="building"
      relatedSlugs={["website-development", "web-application-development", "domain-hosting"]}
      processStepIndexes={[0, 4, 5]}
      processIntro="Moving an entire operation online is a phased effort. We start with a clear-eyed audit, roll out changes in stages to avoid disruption, and stay engaged well past go-live."
    />
  );
}
