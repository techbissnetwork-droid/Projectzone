"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatCard } from "@/components/dashboard/StatCard";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { adminNav } from "@/components/dashboard/navConfig";
import { useAuth } from "@/lib/auth-context";
import { recentOrders } from "@/lib/data/dashboard";

export default function AdminDashboardPage() {
  return (
    <RequireRole role="admin">
      <DashboardShell navItems={adminNav} title="Overview">
        <AdminOverview />
      </DashboardShell>
    </RequireRole>
  );
}

function AdminOverview() {
  const { user } = useAuth();

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h2 className="text-xl font-medium text-(--color-ink)">Welcome back, {user?.name.split(" ")[0]}</h2>
        <p className="mt-1 text-sm text-(--color-ink-muted)">Platform-wide performance at a glance.</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Monthly revenue" value="$482,900" delta="+12.4%" />
        <StatCard label="Active clients" value="248" delta="+18" />
        <StatCard label="Marketplace sales (30d)" value="1,204" delta="+9.1%" />
        <StatCard label="Platform uptime" value="99.99%" />
      </div>

      <div>
        <h3 className="mb-4 text-base font-medium text-(--color-ink)">Recent marketplace orders</h3>
        <Table>
          <Thead columns={["Order", "Client", "Product", "Amount", "Status", "Date"]} />
          <Tbody>
            {recentOrders.map((o) => (
              <tr key={o.id}>
                <td className="px-4 py-3 text-(--color-ink)">{o.id}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{o.client}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{o.product}</td>
                <td className="px-4 py-3 text-(--color-ink)">{o.amount}</td>
                <td className="px-4 py-3">
                  <StatusPill status={o.status} />
                </td>
                <td className="px-4 py-3 text-(--color-ink-faint)">{o.date}</td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </div>
    </div>
  );
}
