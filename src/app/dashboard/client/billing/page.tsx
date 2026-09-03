"use client";

import { CreditCard } from "lucide-react";
import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { Button } from "@/components/ui/Button";
import { clientNav } from "@/components/dashboard/navConfig";
import { invoices } from "@/lib/data/dashboard";

export default function ClientBillingPage() {
  return (
    <RequireRole role="client">
      <DashboardShell navItems={clientNav} title="Billing">
        <div className="flex flex-col gap-8">
          <div className="flex flex-col items-start justify-between gap-4 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:flex-row sm:items-center">
            <div className="flex items-center gap-4">
              <div className="flex h-11 w-11 items-center justify-center rounded-(--radius-md) bg-(--color-surface-raised) text-(--color-ink-muted)">
                <CreditCard className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-medium text-(--color-ink)">Visa ending in 4242</p>
                <p className="text-xs text-(--color-ink-faint)">Expires 09/28</p>
              </div>
            </div>
            <Button variant="outline" size="sm">
              Update payment method
            </Button>
          </div>

          <div>
            <h3 className="mb-4 text-base font-medium text-(--color-ink)">Invoice history</h3>
            <Table>
              <Thead columns={["Invoice", "Date", "Amount", "Status", ""]} />
              <Tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id}>
                    <td className="px-4 py-3 text-(--color-ink)">{inv.id}</td>
                    <td className="px-4 py-3 text-(--color-ink-muted)">{inv.date}</td>
                    <td className="px-4 py-3 text-(--color-ink)">{inv.amount}</td>
                    <td className="px-4 py-3">
                      <StatusPill status={inv.status} />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button className="focus-ring text-xs text-(--color-accent-2) hover:underline">Download</button>
                    </td>
                  </tr>
                ))}
              </Tbody>
            </Table>
          </div>
        </div>
      </DashboardShell>
    </RequireRole>
  );
}
