import type { Metadata } from "next";
import Link from "next/link";
import { Globe, TrendingUp, Zap, LifeBuoy, ArrowUpRight, Plus } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatCard } from "@/components/dashboard/stat-card";
import { StatusPill } from "@/components/ui/status-pill";
import { Button } from "@/components/ui/button";
import { websites, analyticsSummary, supportTickets } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "Dashboard" };

export default function DashboardOverviewPage() {
  const liveSites = websites.filter((w) => w.status === "Live").length;
  const openTickets = supportTickets.filter((t) => t.status !== "Resolved").length;

  return (
    <div>
      <DashboardPageHeader
        title="Welcome back."
        subtitle="Here's what's happening across your digital operations."
        action={
          <Button href="/marketplace" size="sm" arrow>
            Browse Marketplace
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={Globe} label="Live Websites" value={String(liveSites)} hint={`of ${websites.length} total`} />
        <StatCard
          icon={TrendingUp}
          label="Visitors (30d)"
          value={analyticsSummary.visitors30d.toLocaleString()}
          hint={`${analyticsSummary.conversion}% conversion`}
        />
        <StatCard icon={Zap} label="Avg. Load Time" value={`${analyticsSummary.avgLoad}s`} hint="Across all sites" />
        <StatCard icon={LifeBuoy} label="Open Support Tickets" value={String(openTickets)} hint="Avg response < 1 day" />
      </div>

      <div className="mt-10 flex items-center justify-between">
        <h2 className="text-[16px] font-medium text-paper-50">My Websites</h2>
        <Link href="/dashboard/websites" className="text-[13px] font-medium text-paper-50/50 hover:text-paper-50">
          View all
        </Link>
      </div>

      <div className="mt-4 overflow-hidden rounded-xl border border-line-dark">
        <div className="no-scrollbar overflow-x-auto">
          <table className="w-full min-w-[640px] text-left">
            <thead>
              <tr className="border-b border-line-dark bg-ink-900/40 text-[11px] uppercase tracking-wide text-paper-50/40">
                <th className="px-5 py-3 font-medium">Website</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 font-medium">Hosting</th>
                <th className="px-5 py-3 font-medium">SSL</th>
                <th className="px-5 py-3 font-medium">Visitors (30d)</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody>
              {websites.map((w) => (
                <tr key={w.id} className="border-b border-line-dark last:border-0 hover:bg-ink-900/30">
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">
                      <span
                        className="size-8 shrink-0 rounded-lg"
                        style={{ background: `linear-gradient(135deg, ${w.accent}44, transparent)` }}
                      />
                      <div>
                        <div className="text-[13.5px] font-medium text-paper-50">{w.name}</div>
                        <div className="text-[12px] text-paper-50/40">{w.domain ?? "No domain yet"}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <StatusPill status={w.status} />
                  </td>
                  <td className="px-5 py-4">
                    <StatusPill status={w.hosting} />
                  </td>
                  <td className="px-5 py-4">
                    <StatusPill status={w.ssl} />
                  </td>
                  <td className="px-5 py-4 text-[13px] text-paper-50/60">
                    {w.visitors30d.toLocaleString()}
                  </td>
                  <td className="px-5 py-4 text-right">
                    <Link
                      href={`/dashboard/brand-studio/${w.product.toLowerCase().replace(/\s+/g, "-")}`}
                      className="inline-flex items-center gap-1 text-[12.5px] font-medium text-paper-50/60 hover:text-gold-400"
                    >
                      Manage
                      <ArrowUpRight className="size-3.5" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="mt-10 grid gap-4 sm:grid-cols-3">
        <Link
          href="/marketplace"
          className="flex flex-col justify-between rounded-xl border border-line-dark bg-ink-900/40 p-6 hover:border-line-dark-strong"
        >
          <Plus className="size-5 text-gold-400" />
          <div className="mt-6">
            <div className="text-[14px] font-medium text-paper-50">Buy a new product</div>
            <div className="mt-1 text-[12.5px] text-paper-50/45">Browse the marketplace</div>
          </div>
        </Link>
        <Link
          href="/contact"
          className="flex flex-col justify-between rounded-xl border border-line-dark bg-ink-900/40 p-6 hover:border-line-dark-strong"
        >
          <Zap className="size-5 text-gold-400" />
          <div className="mt-6">
            <div className="text-[14px] font-medium text-paper-50">Start a custom project</div>
            <div className="mt-1 text-[12.5px] text-paper-50/45">Talk to TECHBISS</div>
          </div>
        </Link>
        <Link
          href="/dashboard/support"
          className="flex flex-col justify-between rounded-xl border border-line-dark bg-ink-900/40 p-6 hover:border-line-dark-strong"
        >
          <LifeBuoy className="size-5 text-gold-400" />
          <div className="mt-6">
            <div className="text-[14px] font-medium text-paper-50">Get support</div>
            <div className="mt-1 text-[12.5px] text-paper-50/45">{openTickets} open tickets</div>
          </div>
        </Link>
      </div>
    </div>
  );
}
