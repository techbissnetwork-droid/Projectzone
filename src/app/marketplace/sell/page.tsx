import type { Metadata } from "next";
import { Section, Eyebrow } from "@/components/ui/section";
import { PageHero } from "@/components/ui/page-hero";
import { Reveal, RevealGroup, revealItem } from "@/components/ui/reveal";
import { Button } from "@/components/ui/button";
import { MotionDiv } from "@/components/ui/motion-div";
import {
  Upload,
  ShieldCheck,
  BarChart3,
  Wallet,
  MessageSquare,
  RefreshCcw,
} from "lucide-react";

export const metadata: Metadata = {
  title: "Sell on TECHBISS",
  description:
    "Submit website themes, applications, UI kits and digital systems to the TECHBISS marketplace and reach businesses ready to buy.",
};

const steps = [
  { n: "01", title: "Submit", detail: "Upload your product with screenshots, description and pricing." },
  { n: "02", title: "Review", detail: "The TECHBISS team reviews for quality, security and completeness." },
  { n: "03", title: "Publish", detail: "Approved products go live in the marketplace." },
  { n: "04", title: "Earn", detail: "Get paid as businesses purchase and customize your product." },
];

const dashboardFeatures = [
  { icon: Upload, label: "Products", detail: "Manage listings, versions and pricing." },
  { icon: BarChart3, label: "Sales & Analytics", detail: "Track views, conversion and revenue." },
  { icon: Wallet, label: "Earnings", detail: "Payouts and transaction history." },
  { icon: MessageSquare, label: "Reviews", detail: "Respond to customer feedback." },
  { icon: RefreshCcw, label: "Updates", detail: "Ship new versions to existing customers." },
  { icon: ShieldCheck, label: "Support", detail: "Handle customer questions directly." },
];

export default function SellPage() {
  return (
    <>
      <PageHero
        eyebrow="Sell on TECHBISS"
        title="Built something great? Put it in front of businesses ready to buy."
        subtitle="TECHBISS reviews and publishes website themes, applications, UI kits and business systems from approved creators — with sales, support and updates handled through a dedicated seller dashboard."
      >
        <div className="mt-8 flex flex-wrap gap-3">
          <Button href="/contact" size="lg" arrow>
            Apply to Sell
          </Button>
        </div>
      </PageHero>

      <Section>
        <Reveal>
          <Eyebrow>How It Works</Eyebrow>
        </Reveal>
        <RevealGroup className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((s) => (
            <MotionDiv
              key={s.n}
              variants={revealItem}
              className="rounded-xl border border-line-dark bg-ink-900/40 p-6"
            >
              <div className="font-mono-label text-[11px] text-gold-400">{s.n}</div>
              <div className="mt-3 text-[16px] font-medium text-paper-50">{s.title}</div>
              <p className="mt-2 text-[13px] leading-relaxed text-paper-50/50">{s.detail}</p>
            </MotionDiv>
          ))}
        </RevealGroup>
      </Section>

      <Section className="border-t border-line-dark bg-ink-900/40">
        <Reveal>
          <Eyebrow>Seller Dashboard</Eyebrow>
          <h2 className="mt-5 max-w-lg text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Everything you need to run a product business.
          </h2>
        </Reveal>
        <RevealGroup className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {dashboardFeatures.map((f) => (
            <MotionDiv
              key={f.label}
              variants={revealItem}
              className="rounded-xl border border-line-dark bg-ink-950/40 p-6"
            >
              <f.icon className="size-5 text-gold-400" strokeWidth={1.75} />
              <div className="mt-4 text-[15px] font-medium text-paper-50">{f.label}</div>
              <p className="mt-1.5 text-[13px] leading-relaxed text-paper-50/50">{f.detail}</p>
            </MotionDiv>
          ))}
        </RevealGroup>
      </Section>

      <Section className="border-t border-line-dark">
        <Reveal className="flex flex-col items-center gap-6 text-center">
          <h2 className="max-w-xl text-[28px] font-medium leading-tight tracking-tight text-paper-50 sm:text-[36px]">
            Ready to submit your first product?
          </h2>
          <Button href="/contact" size="lg" arrow>
            Apply to Sell
          </Button>
        </Reveal>
      </Section>
    </>
  );
}
