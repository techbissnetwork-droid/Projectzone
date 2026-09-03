import type { Metadata } from "next";
import { CreditCard } from "lucide-react";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { Badge } from "@/components/ui/eyebrow";
import { PaymentMethodCard } from "@/components/dashboard/payment-method-card";
import { invoices, mySites } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Billing",
  description: "Your plan, invoices and payment method.",
};

function formatDate(d: string) {
  return new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export default function BillingPage() {
  const monthlyTotal = mySites.length * 89 > 0 ? invoices
    .filter((i) => i.status === "paid")
    .slice(0, 1)
    .reduce((sum) => sum + 89, 0) : 0;

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Billing"
        description="Your current plan, invoice history and payment method."
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-[var(--color-ink-faint)]">
            <CreditCard className="size-4" strokeWidth={1.75} />
            <span className="font-mono-label text-[10.5px] uppercase">Current plan</span>
          </div>
          <p className="text-[20px] font-medium text-[var(--color-ink)]">Business Hosting</p>
          <p className="text-[13px] text-[var(--color-ink-muted)]">
            {mySites.length} websites · ${monthlyTotal || 89}/mo · renews monthly
          </p>
        </Card>
        <PaymentMethodCard />
      </div>

      <Card className="!p-0 overflow-hidden">
        <div className="border-b border-[var(--color-border)] px-6 py-4">
          <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Invoices</h3>
        </div>
        <div className="overflow-x-auto scrollbar-none">
          <table className="w-full min-w-[520px] text-left">
            <thead>
              <tr className="text-[11px] font-mono-label uppercase text-[var(--color-ink-faint)]">
                <th className="px-6 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Description</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-6 py-3 font-medium text-right">Status</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((inv) => (
                <tr key={inv.id} className="border-t border-[var(--color-border)]">
                  <td className="px-6 py-3.5 text-[13.5px] text-[var(--color-ink-muted)]">
                    {formatDate(inv.date)}
                  </td>
                  <td className="px-4 py-3.5 text-[13.5px] text-[var(--color-ink)]">{inv.description}</td>
                  <td className="px-4 py-3.5 text-[13.5px] text-[var(--color-ink-muted)]">${inv.amount}</td>
                  <td className="px-6 py-3.5 text-right">
                    <Badge tone={inv.status === "paid" ? "live" : "build"}>{inv.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
