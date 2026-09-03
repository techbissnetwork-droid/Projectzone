import type { Metadata } from "next";
import { ShieldCheck, ShieldAlert, Server, Activity } from "lucide-react";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { mySites, hostingUsage } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Hosting",
  description: "Hosting plans, uptime and SSL status across your websites.",
};

export default function HostingPage() {
  const usage = hostingUsage();
  const usedPct = Math.min(100, Math.round((usage.usedGB / usage.limitGB) * 100));

  const planCounts = mySites.reduce<Record<string, number>>((acc, site) => {
    acc[site.hostingPlan] = (acc[site.hostingPlan] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Hosting"
        description="Infrastructure health, storage usage and SSL coverage for every website."
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <Card className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-[var(--color-ink-faint)]">
            <Activity className="size-4" strokeWidth={1.75} />
            <span className="font-mono-label text-[10.5px] uppercase">Uptime (90 days)</span>
          </div>
          <p className="text-[26px] font-medium text-[var(--color-live)]">{usage.uptime}</p>
        </Card>
        <Card className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-[var(--color-ink-faint)]">
            <Server className="size-4" strokeWidth={1.75} />
            <span className="font-mono-label text-[10.5px] uppercase">Active hosting plans</span>
          </div>
          <p className="text-[26px] font-medium text-[var(--color-ink)]">{mySites.length}</p>
        </Card>
        <Card className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-[var(--color-ink-faint)]">
            <ShieldCheck className="size-4" strokeWidth={1.75} />
            <span className="font-mono-label text-[10.5px] uppercase">SSL secured</span>
          </div>
          <p className="text-[26px] font-medium text-[var(--color-ink)]">
            {mySites.filter((s) => s.ssl).length}/{mySites.length}
          </p>
        </Card>
      </div>

      <Card className="flex flex-col gap-5">
        <div className="flex items-center justify-between">
          <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Storage usage</h3>
          <span className="text-[12.5px] text-[var(--color-ink-muted)]">
            {usage.usedGB} GB / {usage.limitGB} GB
          </span>
        </div>
        <div className="h-2 w-full overflow-hidden rounded-full bg-white/[0.06]">
          <div
            className="h-full rounded-full bg-[var(--color-accent)]"
            style={{ width: `${usedPct}%` }}
          />
        </div>
        <div className="flex flex-wrap gap-4 border-t border-[var(--color-border)] pt-4 text-[12.5px] text-[var(--color-ink-faint)]">
          {Object.entries(planCounts).map(([plan, count]) => (
            <span key={plan}>
              {plan} <span className="text-[var(--color-ink-muted)]">× {count}</span>
            </span>
          ))}
        </div>
      </Card>

      <Card className="!p-0">
        <div className="border-b border-[var(--color-border)] px-6 py-4">
          <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">SSL status by website</h3>
        </div>
        <ul>
          {mySites.map((site, i) => (
            <li
              key={site.id}
              className={
                "flex items-center justify-between px-6 py-4" +
                (i !== mySites.length - 1 ? " border-b border-[var(--color-border)]" : "")
              }
            >
              <div>
                <p className="text-[14px] font-medium text-[var(--color-ink)]">{site.name}</p>
                <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-faint)]">{site.hostingPlan}</p>
              </div>
              {site.ssl ? (
                <span className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-live)]">
                  <ShieldCheck className="size-4" strokeWidth={1.75} /> Secured
                </span>
              ) : (
                <span className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-build)]">
                  <ShieldAlert className="size-4" strokeWidth={1.75} /> Pending
                </span>
              )}
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
