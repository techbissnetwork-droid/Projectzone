import type { Metadata } from "next";
import { Users, Percent, Zap } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatCard } from "@/components/dashboard/stat-card";
import { analyticsSummary, websites } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "Analytics" };

export default function AnalyticsPage() {
  return (
    <div>
      <DashboardPageHeader title="Analytics" subtitle="Performance across every website in your account." />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard icon={Users} label="Visitors (30d)" value={analyticsSummary.visitors30d.toLocaleString()} />
        <StatCard icon={Percent} label="Conversion Rate" value={`${analyticsSummary.conversion}%`} />
        <StatCard icon={Zap} label="Avg. Load Time" value={`${analyticsSummary.avgLoad}s`} />
      </div>

      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        <div className="rounded-xl border border-line-dark bg-ink-900/40 p-6">
          <div className="text-[14px] font-medium text-paper-50">Traffic Sources</div>
          <div className="mt-5 flex flex-col gap-4">
            {analyticsSummary.topSources.map((s) => (
              <div key={s.source}>
                <div className="flex items-center justify-between text-[13px] text-paper-50/60">
                  <span>{s.source}</span>
                  <span>{s.pct}%</span>
                </div>
                <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-ink-950">
                  <div className="h-full rounded-full bg-gold-400" style={{ width: `${s.pct}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-line-dark bg-ink-900/40 p-6">
          <div className="text-[14px] font-medium text-paper-50">Visitors by Website</div>
          <div className="mt-5 flex flex-col gap-4">
            {websites.map((w) => {
              const max = Math.max(...websites.map((s) => s.visitors30d), 1);
              return (
                <div key={w.id}>
                  <div className="flex items-center justify-between text-[13px] text-paper-50/60">
                    <span>{w.name}</span>
                    <span>{w.visitors30d.toLocaleString()}</span>
                  </div>
                  <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-ink-950">
                    <div
                      className="h-full rounded-full"
                      style={{ width: `${(w.visitors30d / max) * 100}%`, background: w.accent }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
