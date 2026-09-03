import type { Metadata } from "next";
import { RefreshCcw } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { Button } from "@/components/ui/button";
import { domains } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "Domains" };

export default function DomainsPage() {
  return (
    <div>
      <DashboardPageHeader
        title="Domains"
        subtitle="Manage the domains connected to your TECHBISS websites."
        action={
          <Button size="sm" arrow>
            Register a Domain
          </Button>
        }
      />

      <div className="overflow-hidden rounded-xl border border-line-dark">
        <div className="no-scrollbar overflow-x-auto">
          <table className="w-full min-w-[640px] text-left">
            <thead>
              <tr className="border-b border-line-dark bg-ink-900/40 text-[11px] uppercase tracking-wide text-paper-50/40">
                <th className="px-5 py-3 font-medium">Domain</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 font-medium">Registrar</th>
                <th className="px-5 py-3 font-medium">Expires</th>
                <th className="px-5 py-3 font-medium">Auto-Renew</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody>
              {domains.map((d) => (
                <tr key={d.name} className="border-b border-line-dark last:border-0 hover:bg-ink-900/30">
                  <td className="px-5 py-4 text-[13.5px] font-medium text-paper-50">{d.name}</td>
                  <td className="px-5 py-4">
                    <StatusPill status={d.status} />
                  </td>
                  <td className="px-5 py-4 text-[13px] text-paper-50/55">{d.registrar}</td>
                  <td className="px-5 py-4 text-[13px] text-paper-50/55">{d.expires}</td>
                  <td className="px-5 py-4 text-[13px] text-paper-50/55">
                    {d.autoRenew ? "On" : "Off"}
                  </td>
                  <td className="px-5 py-4 text-right">
                    <button className="inline-flex items-center gap-1 text-[12.5px] font-medium text-paper-50/60 hover:text-gold-400">
                      <RefreshCcw className="size-3.5" />
                      Manage
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
