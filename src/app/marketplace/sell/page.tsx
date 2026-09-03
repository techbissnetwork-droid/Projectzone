import type { Metadata } from "next";
import {
  LayoutTemplate,
  Smartphone,
  Component,
  LayoutDashboard,
  Rocket,
  Boxes,
  UploadCloud,
  ClipboardCheck,
  BadgeCheck,
  TrendingUp,
  Package,
  DollarSign,
  Users,
  Star,
  RefreshCw,
  LifeBuoy,
  BarChart3,
} from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/eyebrow";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { SellApplyForm } from "@/components/marketplace/sell-apply-form";

export const metadata: Metadata = {
  title: "Sell on TECHBISS",
  description:
    "Submit your themes, templates and digital systems to the TECHBISS Marketplace and earn from every sale.",
};

const SUBMISSION_TYPES = [
  { icon: LayoutTemplate, label: "Website Themes" },
  { icon: Smartphone, label: "App Templates" },
  { icon: Component, label: "UI Kits" },
  { icon: LayoutDashboard, label: "Dashboards" },
  { icon: Rocket, label: "SaaS Templates" },
  { icon: Boxes, label: "Digital Systems" },
  { icon: Package, label: "Components" },
];

const PROCESS = [
  {
    step: "01",
    title: "Apply",
    description: "Tell us about you and what you build. Share a portfolio link so we can see your work.",
    icon: UploadCloud,
  },
  {
    step: "02",
    title: "Review",
    description: "Our creator team evaluates craft, code quality and market fit — usually within 5 business days.",
    icon: ClipboardCheck,
  },
  {
    step: "03",
    title: "Approval",
    description: "Approved creators get access to the Creator Dashboard to prepare their first listing.",
    icon: BadgeCheck,
  },
  {
    step: "04",
    title: "Launch & Earn",
    description: "Publish your product to the Marketplace and start earning from every sale.",
    icon: TrendingUp,
  },
];

const DASHBOARD_FEATURES = [
  { icon: Package, label: "Products", description: "Manage listings, versions and assets in one place." },
  { icon: DollarSign, label: "Sales", description: "Track every purchase as it happens, in real time." },
  { icon: BarChart3, label: "Earnings", description: "See payouts, balances and historical revenue." },
  { icon: Users, label: "Customers", description: "Understand who's buying and how they use your product." },
  { icon: Star, label: "Reviews", description: "Read and respond to buyer feedback directly." },
  { icon: RefreshCw, label: "Updates", description: "Ship new versions to every existing customer." },
  { icon: LifeBuoy, label: "Support", description: "Handle buyer questions without leaving the dashboard." },
  { icon: BarChart3, label: "Analytics", description: "Views, conversion rate and traffic sources per listing." },
];

export default function SellPage() {
  return (
    <>
      <section className="pb-20 pt-36 sm:pb-28 sm:pt-40 md:pt-44">
        <Container>
          <Reveal className="mx-auto max-w-[680px] text-center">
            <Eyebrow className="justify-center">Sell on TECHBISS</Eyebrow>
            <h1 className="mt-6 text-balance font-serif-display text-[40px] leading-[1.05] tracking-[-0.01em] sm:text-[60px]">
              Build once. Earn from every launch.
            </h1>
            <p className="mt-6 text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)]">
              TECHBISS Marketplace connects skilled builders with businesses who want to
              launch fast. Submit your themes and systems, get discovered by thousands of
              buyers, and earn from every purchase and upgrade.
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal>
            <Eyebrow>What You Can Submit</Eyebrow>
            <h2 className="mt-6 max-w-[560px] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
              Any polished digital product has a home here.
            </h2>
          </Reveal>

          <RevealGroup className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {SUBMISSION_TYPES.map(({ icon: Icon, label }) => (
              <RevealItem key={label}>
                <div className="flex h-[140px] flex-col justify-between rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
                  <Icon className="size-5 text-[var(--color-accent-ink)]" strokeWidth={1.5} />
                  <span className="text-[14px] font-medium">{label}</span>
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal>
            <Eyebrow>How It Works</Eyebrow>
            <h2 className="mt-6 max-w-[560px] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
              From application to your first sale.
            </h2>
          </Reveal>

          <RevealGroup className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {PROCESS.map(({ step, title, description, icon: Icon }) => (
              <RevealItem key={step}>
                <div className="flex h-full flex-col rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6">
                  <div className="flex items-center justify-between">
                    <span className="font-mono-label text-[11px] text-[var(--color-accent)]">{step}</span>
                    <Icon className="size-4 text-[var(--color-ink-faint)]" strokeWidth={1.5} />
                  </div>
                  <h3 className="mt-6 text-[16px] font-medium">{title}</h3>
                  <p className="mt-2.5 text-[13.5px] leading-relaxed text-[var(--color-ink-muted)]">
                    {description}
                  </p>
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <div className="grid gap-14 lg:grid-cols-2 lg:gap-10">
            <Reveal>
              <Eyebrow>Creator Dashboard</Eyebrow>
              <h2 className="mt-6 max-w-[440px] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
                Everything you need to run a product business.
              </h2>
              <p className="mt-5 max-w-[46ch] text-[14.5px] leading-relaxed text-[var(--color-ink-muted)]">
                Every approved creator gets a dedicated dashboard to manage listings, track
                performance and support customers — no separate tools required.
              </p>
            </Reveal>

            <RevealGroup className="grid grid-cols-2 gap-3">
              {DASHBOARD_FEATURES.map(({ icon: Icon, label, description }) => (
                <RevealItem key={label}>
                  <div className="flex h-full flex-col gap-2 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <Icon className="size-4 text-[var(--color-accent-ink)]" strokeWidth={1.5} />
                    <span className="text-[13.5px] font-medium">{label}</span>
                    <span className="text-[12px] leading-snug text-[var(--color-ink-faint)]">
                      {description}
                    </span>
                  </div>
                </RevealItem>
              ))}
            </RevealGroup>
          </div>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal className="mx-auto max-w-[720px] rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 text-center sm:p-12">
            <Eyebrow className="justify-center">Revenue Model</Eyebrow>
            <p className="mt-6 text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)]">
              Earn from one-time purchases, plus optional free-to-paid upgrade tiers and
              add-ons you define yourself. You set your pricing; we handle payments, license
              delivery and customer support infrastructure.
            </p>
          </Reveal>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <Reveal className="mx-auto max-w-[560px] text-center">
            <Eyebrow className="justify-center">Apply to Sell</Eyebrow>
            <h2 className="mt-6 text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
              Tell us what you build.
            </h2>
          </Reveal>

          <Reveal delay={0.08} className="mx-auto mt-10 max-w-[640px]">
            <SellApplyForm />
          </Reveal>
        </Container>
      </section>
    </>
  );
}
