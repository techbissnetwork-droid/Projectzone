import type { Metadata } from "next";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { Button } from "@/components/ui/button";
import { hostingPlans } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "Hosting" };

export default function HostingPage() {
  return (
    <div>
      <DashboardPageHeader
        title="Hosting"
        subtitle="Infrastructure powering each of your websites."
        action={
          <Button size="sm" arrow>
            Upgrade Plan
          </Button>
        }
      />

      <div className="grid gap-4">
        {hostingPlans.map((h) => (
          <div key={h.site} className="rounded-xl border border-line-dark bg-ink-900/40 p-6">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
              <div>
                <div className="text-[15px] font-medium text-paper-50">{h.site}</div>
                <div className="mt-1 text-[13px] text-paper-50/45">
                  {h.plan} Plan · {h.region}
                </div>
              </div>
              <div className="flex items-center gap-3">
                <StatusPill status={h.status} />
                <span className="text-[12.5px] text-paper-50/45">Renews {h.renews}</span>
              </div>
            </div>
            <div className="mt-4">
              <div className="flex items-center justify-between text-[12px] text-paper-50/40">
                <span>Resource usage</span>
                <span>{h.usage}%</span>
              </div>
              <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-ink-950">
                <div
                  className="h-full rounded-full bg-gold-400"
                  style={{ width: `${h.usage}%` }}
                />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
