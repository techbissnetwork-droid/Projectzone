import type { Metadata } from "next";
import { Download, CreditCard } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { invoices } from "@/lib/data/dashboard";
import { formatPrice } from "@/lib/utils";

export const metadata: Metadata = { title: "Billing" };

export default function BillingPage() {
  const total = invoices.reduce((sum, i) => sum + i.amountCents, 0);
  const overdue = invoices.filter((i) => i.status === "Overdue").length;

  return (
    <div>
      <DashboardPageHeader title="Billing" subtitle="Invoices and payment history across your TECHBISS account." />

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-line-dark bg-ink-900/40 p-5">
          <div className="text-[12px] uppercase tracking-wide text-paper-50/40">Total Billed</div>
          <div className="mt-2 text-[24px] font-medium text-paper-50">{formatPrice(total)}</div>
        </div>
        <div className="rounded-xl border border-line-dark bg-ink-900/40 p-5">
          <div className="text-[12px] uppercase tracking-wide text-paper-50/40">Overdue Invoices</div>
          <div className="mt-2 text-[24px] font-medium text-paper-50">{overdue}</div>
        </div>
        <div className="flex items-center gap-3 rounded-xl border border-line-dark bg-ink-900/40 p-5">
          <CreditCard className="size-5 text-gold-400" />
          <div>
            <div className="text-[13px] font-medium text-paper-50">Visa •••• 4242</div>
            <div className="text-[12px] text-paper-50/40">Default payment method</div>
          </div>
        </div>
      </div>

      <div className="mt-8 overflow-hidden rounded-xl border border-line-dark">
        <div className="no-scrollbar overflow-x-auto">
          <table className="w-full min-w-[640px] text-left">
            <thead>
              <tr className="border-b border-line-dark bg-ink-900/40 text-[11px] uppercase tracking-wide text-paper-50/40">
                <th className="px-5 py-3 font-medium">Invoice</th>
                <th className="px-5 py-3 font-medium">Description</th>
                <th className="px-5 py-3 font-medium">Date</th>
                <th className="px-5 py-3 font-medium">Amount</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody>
              {invoices.map((inv) => (
                <tr key={inv.id} className="border-b border-line-dark last:border-0 hover:bg-ink-900/30">
                  <td className="px-5 py-4 text-[13px] font-mono-label text-paper-50/60">{inv.id}</td>
                  <td className="px-5 py-4 text-[13.5px] text-paper-50">{inv.description}</td>
                  <td className="px-5 py-4 text-[13px] text-paper-50/55">{inv.date}</td>
                  <td className="px-5 py-4 text-[13.5px] font-medium text-paper-50">
                    {formatPrice(inv.amountCents)}
                  </td>
                  <td className="px-5 py-4">
                    <StatusPill status={inv.status} />
                  </td>
                  <td className="px-5 py-4 text-right">
                    <button className="inline-flex items-center gap-1 text-[12.5px] font-medium text-paper-50/60 hover:text-gold-400">
                      <Download className="size-3.5" />
                      PDF
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
