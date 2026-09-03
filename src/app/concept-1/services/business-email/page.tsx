import type { Metadata } from "next";
import { serviceBySlug } from "@/lib/site-data";
import { ServiceDetailLayout } from "@/components/concept-1/ServiceDetailLayout";

const service = serviceBySlug("business-email")!;

export const metadata: Metadata = {
  title: service.title,
  description: service.shortDescription,
};

export default function BusinessEmailPage() {
  return (
    <ServiceDetailLayout
      slug="business-email"
      visual="envelope"
      relatedSlugs={["domain-hosting", "ssl-security", "website-development"]}
      processStepIndexes={[0, 2, 4]}
      processIntro="Professional email is often the first thing a customer notices — and the first thing overlooked. We configure it correctly, securely, and with your team ready to use it from day one."
    />
  );
}
