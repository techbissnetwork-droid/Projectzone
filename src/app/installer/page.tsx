import { Suspense } from "react";
import type { Metadata } from "next";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { InstallerWizard } from "@/components/installer/InstallerWizard";

export const metadata: Metadata = {
  title: "Advanced Installer",
  description: "Deploy any TECHBISS marketplace product with automatic URL detection, migration and guided configuration.",
};

export default function InstallerPage() {
  return (
    <>
      <PageHero
        eyebrow="Advanced Installer"
        title="From purchase to production, automatically."
        description="Automatic URL detection, clean installs, existing-site migration and guided configuration — all in one guided flow."
      />
      <Section size="tight">
        <Container size="narrow">
          <Suspense fallback={<div className="py-20 text-center text-sm text-(--color-ink-faint)">Loading installer...</div>}>
            <InstallerWizard />
          </Suspense>
        </Container>
      </Section>
    </>
  );
}
