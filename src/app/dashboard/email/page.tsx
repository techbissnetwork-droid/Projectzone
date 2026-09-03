import type { Metadata } from "next";
import { Mail } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { Button } from "@/components/ui/button";
import { mailboxes } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "Business Email" };

export default function EmailPage() {
  return (
    <div>
      <DashboardPageHeader
        title="Business Email"
        subtitle="Professional mailboxes on your own domain."
        action={
          <Button size="sm" arrow>
            Add Mailbox
          </Button>
        }
      />

      <div className="grid gap-3">
        {mailboxes.map((m) => (
          <div key={m.address} className="flex items-center gap-4 rounded-xl border border-line-dark bg-ink-900/40 p-5">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line-dark bg-ink-950 text-gold-400">
              <Mail className="size-4.5" strokeWidth={1.75} />
            </span>
            <div className="flex-1">
              <div className="text-[14px] font-medium text-paper-50">{m.address}</div>
              <div className="mt-0.5 text-[12.5px] text-paper-50/45">{m.user}</div>
            </div>
            <div className="hidden w-32 sm:block">
              <div className="flex items-center justify-between text-[11px] text-paper-50/40">
                <span>Storage</span>
                <span>{m.storage}%</span>
              </div>
              <div className="mt-1 h-1 w-full overflow-hidden rounded-full bg-ink-950">
                <div className="h-full rounded-full bg-signal-400" style={{ width: `${m.storage}%` }} />
              </div>
            </div>
            <StatusPill status={m.status} />
          </div>
        ))}
      </div>
    </div>
  );
}
