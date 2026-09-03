import type { Metadata } from "next";
import { Download, ShieldCheck, Zap } from "lucide-react";
import { PageHero } from "@/components/ui/PageHero";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Reveal } from "@/components/ui/Reveal";
import { MarketplaceBrowser } from "@/components/marketplace/MarketplaceBrowser";
import { Button } from "@/components/ui/Button";

export const metadata: Metadata = {
  title: "Marketplace",
  description: "Browse, preview and purchase ready-made websites, themes and platforms — deploy in minutes with the Advanced Installer.",
};

const trust = [
  { icon: ShieldCheck, label: "Secure checkout & 14-day guarantee" },
  { icon: Zap, label: "Deploy in minutes with the Advanced Installer" },
  { icon: Download, label: "Lifetime access to your purchase" },
];

export default function MarketplacePage() {
  return (
    <>
      <PageHero
        eyebrow="Marketplace"
        title="Launch-ready platforms, built by TECHBISS."
        description="Every product ships with full source, a component library and one-click deployment through our Advanced Installer — no drag-and-drop compromises."
      >
        <div className="flex flex-wrap justify-center gap-3">
          <Button href="/installer" variant="outline">
            Explore the Advanced Installer
          </Button>
        </div>
      </PageHero>

      <div className="border-y border-(--color-border) bg-(--color-surface)">
        <Container size="wide">
          <Reveal className="flex flex-wrap items-center justify-center gap-x-10 gap-y-3 py-6">
            {trust.map(({ icon: Icon, label }) => (
              <span key={label} className="flex items-center gap-2 text-xs text-(--color-ink-muted) sm:text-sm">
                <Icon className="h-4 w-4 text-(--color-accent-2)" />
                {label}
              </span>
            ))}
          </Reveal>
        </Container>
      </div>

      <Section>
        <Container size="wide">
          <MarketplaceBrowser />
        </Container>
      </Section>
    </>
  );
}
