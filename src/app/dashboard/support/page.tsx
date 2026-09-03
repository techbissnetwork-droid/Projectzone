import type { Metadata } from "next";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { supportTickets } from "@/lib/data/dashboard";
import { NewTicketForm } from "@/components/dashboard/new-ticket-form";

export const metadata: Metadata = { title: "Support" };

const priorityColor: Record<string, string> = {
  High: "text-danger-500",
  Normal: "text-warning-500",
  Low: "text-paper-50/40",
};

export default function SupportPage() {
  return (
    <div>
      <DashboardPageHeader title="Support" subtitle="Direct access to the TECHBISS team." />

      <div className="grid gap-8 lg:grid-cols-[1.3fr_1fr]">
        <div className="flex flex-col gap-3">
          {supportTickets.map((t) => (
            <div key={t.id} className="rounded-xl border border-line-dark bg-ink-900/40 p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="font-mono-label text-[11px] text-paper-50/35">{t.id}</div>
                  <div className="mt-1 text-[14.5px] font-medium text-paper-50">{t.subject}</div>
                  <div className="mt-1 text-[12.5px] text-paper-50/45">{t.site}</div>
                </div>
                <StatusPill status={t.status} />
              </div>
              <div className="mt-4 flex items-center justify-between border-t border-line-dark pt-3 text-[12px] text-paper-50/40">
                <span className={priorityColor[t.priority]}>{t.priority} priority</span>
                <span>Updated {t.updated}</span>
              </div>
            </div>
          ))}
        </div>

        <NewTicketForm />
      </div>
    </div>
  );
}
