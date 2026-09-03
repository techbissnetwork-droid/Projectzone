"use client";

import Link from "next/link";
import { ExternalLink, Rocket } from "lucide-react";
import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatCard } from "@/components/dashboard/StatCard";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { Button } from "@/components/ui/Button";
import { clientNav } from "@/components/dashboard/navConfig";
import { useAuth } from "@/lib/auth-context";
import { clientOwnedProducts, invoices, supportTickets } from "@/lib/data/dashboard";

export default function ClientDashboardPage() {
  return (
    <RequireRole role="client">
      <DashboardShell navItems={clientNav} title="Overview">
        <ClientOverview />
      </DashboardShell>
    </RequireRole>
  );
}

function ClientOverview() {
  const { user } = useAuth();

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h2 className="text-xl font-medium text-(--color-ink)">Welcome back, {user?.name.split(" ")[0]}</h2>
        <p className="mt-1 text-sm text-(--color-ink-muted)">Here&apos;s what&apos;s happening across your account.</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Active sites" value="2" />
        <StatCard label="Open tickets" value="1" />
        <StatCard label="Total spent" value="$1,628" delta="+$249 this month" />
        <StatCard label="Client since" value="2024" />
      </div>

      <div>
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-medium text-(--color-ink)">My products</h3>
          <Link href="/dashboard/client/products" className="focus-ring text-sm text-(--color-accent-2) hover:underline">
            View all
          </Link>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {clientOwnedProducts.map((p) => (
            <div key={p.name} className="flex items-center gap-4 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-5">
              <div
                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-(--radius-md) text-sm font-medium text-white"
                style={{ background: `linear-gradient(135deg, ${p.gradient[0]}, ${p.gradient[1]})` }}
              >
                {p.name.slice(0, 1)}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-(--color-ink)">{p.name}</p>
                <p className="truncate text-xs text-(--color-ink-faint)">{p.url}</p>
              </div>
              <StatusPill status={p.status} />
            </div>
          ))}
        </div>
      </div>

      <div>
        <h3 className="mb-4 text-base font-medium text-(--color-ink)">Recent invoices</h3>
        <Table>
          <Thead columns={["Invoice", "Date", "Amount", "Status"]} />
          <Tbody>
            {invoices.map((inv) => (
              <tr key={inv.id}>
                <td className="px-4 py-3 text-(--color-ink)">{inv.id}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{inv.date}</td>
                <td className="px-4 py-3 text-(--color-ink)">{inv.amount}</td>
                <td className="px-4 py-3">
                  <StatusPill status={inv.status} />
                </td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </div>

      <div>
        <h3 className="mb-4 text-base font-medium text-(--color-ink)">Support</h3>
        <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface)">
          {supportTickets.slice(0, 2).map((t) => (
            <div key={t.id} className="flex items-center justify-between gap-4 p-4">
              <div className="min-w-0">
                <p className="truncate text-sm text-(--color-ink)">{t.subject}</p>
                <p className="text-xs text-(--color-ink-faint)">
                  {t.id} · Updated {t.updated}
                </p>
              </div>
              <StatusPill status={t.status} />
            </div>
          ))}
        </div>
      </div>

      <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-(--color-ink)">Ready to deploy something new?</p>
            <p className="mt-1 text-xs text-(--color-ink-faint)">Browse the marketplace and launch it with the Advanced Installer.</p>
          </div>
          <div className="flex gap-2">
            <Button href="/marketplace" variant="outline" icon={<ExternalLink className="h-4 w-4" />} iconPosition="left">
              Marketplace
            </Button>
            <Button href="/installer" variant="secondary" icon={<Rocket className="h-4 w-4" />} iconPosition="left">
              Launch Installer
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
