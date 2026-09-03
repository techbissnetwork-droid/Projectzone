import type { Metadata } from "next";
import Link from "next/link";
import { Globe, Link2, Server, Receipt, ArrowRight } from "lucide-react";
import { Badge } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { getProduct } from "@/lib/data/marketplace";
import { mySites, domains, hostingUsage, nextInvoice, type SiteStatus } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Dashboard Overview",
  description: "Your TECHBISS account — websites, domains, hosting and billing at a glance.",
};

const statusTone: Record<SiteStatus, "live" | "accent" | "build" | "neutral"> = {
  live: "live",
  ready: "accent",
  building: "build",
  draft: "neutral",
};

function formatDate(d: string) {
  return new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export default function DashboardOverviewPage() {
  const liveCount = mySites.filter((s) => s.status === "live").length;
  const usage = hostingUsage();
  const upcoming = nextInvoice();
  const unfinished = mySites.find((s) => s.status !== "live");

  const stats = [
    { label: "Websites live", value: `${liveCount}`, sub: `of ${mySites.length} total`, icon: Globe },
    { label: "Domains connected", value: `${domains.length}`, sub: "under management", icon: Link2 },
    {
      label: "Hosting storage",
      value: `${usage.usedGB} GB`,
      sub: `of ${usage.limitGB} GB used`,
      icon: Server,
    },
    {
      label: "Next invoice",
      value: upcoming ? `$${upcoming.amount}` : "$0",
      sub: upcoming ? `due ${formatDate(upcoming.date)}` : "nothing due",
      icon: Receipt,
    },
  ];

  return (
    <div className="flex flex-col gap-10">
      <PageHeader
        eyebrow="Dashboard"
        title="Welcome back, Alex."
        description="Here's what's happening across your websites, domains and hosting today."
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.label} className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <span className="font-mono-label text-[10.5px] uppercase text-[var(--color-ink-faint)]">
                {stat.label}
              </span>
              <stat.icon className="size-4 text-[var(--color-ink-faint)]" strokeWidth={1.75} />
            </div>
            <div>
              <p className="text-[26px] font-medium tracking-tight text-[var(--color-ink)]">{stat.value}</p>
              <p className="mt-1 text-[12.5px] text-[var(--color-ink-faint)]">{stat.sub}</p>
            </div>
          </Card>
        ))}
      </div>

      <div className="flex flex-wrap gap-3">
        <Button href="/marketplace" variant="secondary" size="sm">
          Browse Marketplace
        </Button>
        <Button href="/dashboard/domains" variant="secondary" size="sm">
          Connect a Domain
        </Button>
        <Button href="/dashboard/support" variant="secondary" size="sm">
          Contact Support
        </Button>
      </div>

      {unfinished && (
        <Card className="flex flex-col items-start justify-between gap-4 border-[var(--color-build)]/30 bg-[var(--color-build-soft)] sm:flex-row sm:items-center">
          <div>
            <p className="font-mono-label text-[11px] uppercase text-[var(--color-build)]">Action needed</p>
            <p className="mt-1.5 text-[15px] font-medium text-[var(--color-ink)]">
              Finish launching {unfinished.name}
            </p>
            <p className="mt-1 text-[13px] text-[var(--color-ink-muted)]">
              Complete the launch checklist to take this site live.
            </p>
          </div>
          <Link
            href={`/dashboard/launch/${unfinished.id}`}
            className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white"
          >
            Finish launching
            <ArrowRight className="size-3.5" strokeWidth={2} />
          </Link>
        </Card>
      )}

      <div>
        <div className="flex items-center justify-between">
          <h2 className="text-[17px] font-medium text-[var(--color-ink)]">My Websites</h2>
          <Link
            href="/dashboard/websites"
            className="inline-flex items-center gap-1 text-[13px] font-medium text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
          >
            View all
            <ArrowRight className="size-3.5" strokeWidth={2} />
          </Link>
        </div>
        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          {mySites.slice(0, 3).map((site) => {
            const theme = getProduct(site.themeSlug);
            return (
              <Card key={site.id} className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                  <p className="text-[14.5px] font-medium text-[var(--color-ink)]">{site.name}</p>
                  <Badge tone={statusTone[site.status]}>{site.status}</Badge>
                </div>
                <p className="text-[12.5px] text-[var(--color-ink-faint)]">
                  {theme?.name ?? site.themeSlug} · {site.domain ?? "Not connected"}
                </p>
                <Link
                  href={
                    site.status === "live"
                      ? `/dashboard/websites`
                      : `/dashboard/launch/${site.id}`
                  }
                  className="mt-1 inline-flex items-center gap-1 text-[12.5px] font-medium text-[var(--color-accent-ink)]"
                >
                  {site.status === "live" ? "Manage" : "Continue setup"}
                  <ArrowRight className="size-3.5" strokeWidth={2} />
                </Link>
              </Card>
            );
          })}
        </div>
      </div>
    </div>
  );
}
