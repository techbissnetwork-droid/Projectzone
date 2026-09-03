import type { Metadata } from "next";
import { ShieldCheck } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { sslCerts } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "SSL & Security" };

export default function SslPage() {
  return (
    <div>
      <DashboardPageHeader
        title="SSL & Security"
        subtitle="Certificates protecting your websites and your customers' data."
      />

      <div className="grid gap-4">
        {sslCerts.map((c) => (
          <div key={c.domain} className="flex items-center gap-4 rounded-xl border border-line-dark bg-ink-900/40 p-6">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-line-dark bg-ink-950 text-gold-400">
              <ShieldCheck className="size-5" strokeWidth={1.75} />
            </span>
            <div className="flex-1">
              <div className="text-[14.5px] font-medium text-paper-50">{c.domain}</div>
              <div className="mt-0.5 text-[12.5px] text-paper-50/45">
                {c.issuer !== "—" ? `Issued by ${c.issuer}` : "Not yet issued"}
              </div>
            </div>
            <div className="text-right">
              <StatusPill status={c.status} />
              <div className="mt-1.5 text-[12px] text-paper-50/40">Expires {c.expires}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
