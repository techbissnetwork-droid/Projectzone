import type { Metadata } from "next";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { ResourcesBrowser } from "@/components/resources/ResourcesBrowser";
import { CtaBanner } from "@/components/home/CtaBanner";

export const metadata: Metadata = {
  title: "Resources",
  description: "Guides, insights, playbooks and reports from the TECHBISS team on modernization, AI, design and security.",
};

export default function ResourcesPage() {
  return (
    <>
      <PageHero
        eyebrow="Resources"
        title="Ideas worth stealing for your roadmap."
        description="Practical guides and field notes from engagements across enterprise modernization, applied AI, design systems and security."
      />
      <Section size="tight">
        <Container size="wide">
          <ResourcesBrowser />
        </Container>
      </Section>
      <CtaBanner />
    </>
  );
}
