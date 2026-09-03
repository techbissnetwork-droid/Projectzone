import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, ShieldCheck, ShieldAlert } from "lucide-react";
import { Badge } from "@/components/ui/eyebrow";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { getProduct } from "@/lib/data/marketplace";
import { mySites, type SiteStatus } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "My Websites",
  description: "Manage every website you've built or bought with TECHBISS in one place.",
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

export default function WebsitesPage() {
  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="My Websites"
        description="Every site you've launched, built or are still setting up."
      />

      {/* Desktop table */}
      <Card className="hidden overflow-hidden !p-0 lg:block">
        <div className="overflow-x-auto scrollbar-none">
          <table className="w-full min-w-[880px] text-left">
            <thead>
              <tr className="border-b border-[var(--color-border)] text-[11px] font-mono-label uppercase text-[var(--color-ink-faint)]">
                <th className="px-6 py-3.5 font-medium">Website</th>
                <th className="px-4 py-3.5 font-medium">Status</th>
                <th className="px-4 py-3.5 font-medium">Domain</th>
                <th className="px-4 py-3.5 font-medium">Theme</th>
                <th className="px-4 py-3.5 font-medium">Hosting</th>
                <th className="px-4 py-3.5 font-medium">SSL</th>
                <th className="px-4 py-3.5 font-medium">Updated</th>
                <th className="px-6 py-3.5 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {mySites.map((site) => {
                const theme = getProduct(site.themeSlug);
                return (
                  <tr key={site.id} className="border-b border-[var(--color-border)] last:border-0">
                    <td className="px-6 py-4 text-[14px] font-medium text-[var(--color-ink)]">{site.name}</td>
                    <td className="px-4 py-4">
                      <Badge tone={statusTone[site.status]}>{site.status}</Badge>
                    </td>
                    <td className="px-4 py-4 text-[13px] text-[var(--color-ink-muted)]">
                      {site.domain ?? "Not connected"}
                    </td>
                    <td className="px-4 py-4 text-[13px] text-[var(--color-ink-muted)]">
                      {theme?.name ?? site.themeSlug}
                    </td>
                    <td className="px-4 py-4 text-[13px] text-[var(--color-ink-muted)]">{site.hostingPlan}</td>
                    <td className="px-4 py-4">
                      {site.ssl ? (
                        <span className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-live)]">
                          <ShieldCheck className="size-3.5" strokeWidth={1.75} /> Secured
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-ink-faint)]">
                          <ShieldAlert className="size-3.5" strokeWidth={1.75} /> Pending
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-4 text-[13px] text-[var(--color-ink-faint)]">
                      {formatDate(site.lastUpdate)}
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center justify-end gap-4 text-[12.5px] font-medium">
                        <Link
                          href={`/dashboard/brand-studio/${site.id}`}
                          className="text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
                        >
                          Edit
                        </Link>
                        <Link
                          href={`/marketplace/theme/${site.themeSlug}/preview`}
                          className="text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
                        >
                          Preview
                        </Link>
                        <Link
                          href={`/dashboard/launch/${site.id}`}
                          className="text-[var(--color-accent-ink)]"
                        >
                          Launch
                        </Link>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Mobile / tablet stacked cards */}
      <div className="flex flex-col gap-4 lg:hidden">
        {mySites.map((site) => {
          const theme = getProduct(site.themeSlug);
          return (
            <Card key={site.id} className="flex flex-col gap-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-[15px] font-medium text-[var(--color-ink)]">{site.name}</p>
                  <p className="mt-1 text-[12.5px] text-[var(--color-ink-faint)]">
                    {theme?.name ?? site.themeSlug}
                  </p>
                </div>
                <Badge tone={statusTone[site.status]}>{site.status}</Badge>
              </div>

              <div className="grid grid-cols-2 gap-y-2.5 text-[12.5px]">
                <span className="text-[var(--color-ink-faint)]">Domain</span>
                <span className="text-right text-[var(--color-ink-muted)]">
                  {site.domain ?? "Not connected"}
                </span>
                <span className="text-[var(--color-ink-faint)]">Hosting</span>
                <span className="text-right text-[var(--color-ink-muted)]">{site.hostingPlan}</span>
                <span className="text-[var(--color-ink-faint)]">SSL</span>
                <span className="text-right text-[var(--color-ink-muted)]">
                  {site.ssl ? "Secured" : "Pending"}
                </span>
                <span className="text-[var(--color-ink-faint)]">Updated</span>
                <span className="text-right text-[var(--color-ink-muted)]">{formatDate(site.lastUpdate)}</span>
              </div>

              <div className="flex items-center gap-4 border-t border-[var(--color-border)] pt-3.5 text-[12.5px] font-medium">
                <Link href={`/dashboard/brand-studio/${site.id}`} className="text-[var(--color-ink-muted)]">
                  Edit
                </Link>
                <Link
                  href={`/marketplace/theme/${site.themeSlug}/preview`}
                  className="text-[var(--color-ink-muted)]"
                >
                  Preview
                </Link>
                <Link
                  href={`/dashboard/launch/${site.id}`}
                  className="ml-auto inline-flex items-center gap-1 text-[var(--color-accent-ink)]"
                >
                  Launch
                  <ArrowRight className="size-3.5" strokeWidth={2} />
                </Link>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
