"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatCard } from "@/components/dashboard/StatCard";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { adminNav } from "@/components/dashboard/navConfig";
import { recentOrders } from "@/lib/data/dashboard";
import { products } from "@/lib/data/products";

export default function AdminMarketplacePage() {
  const topProducts = [...products].sort((a, b) => b.sales - a.sales).slice(0, 5);

  return (
    <RequireRole role="admin">
      <DashboardShell navItems={adminNav} title="Marketplace">
        <div className="flex flex-col gap-8">
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="Total products" value={String(products.length)} />
            <StatCard label="Total sales" value="34,010" delta="+6.2%" />
            <StatCard label="Avg. rating" value="4.79" />
            <StatCard label="Refund rate" value="1.2%" />
          </div>

          <div>
            <h3 className="mb-4 text-base font-medium text-(--color-ink)">Top-selling products</h3>
            <Table>
              <Thead columns={["Product", "Category", "Price", "Rating", "Sales"]} />
              <Tbody>
                {topProducts.map((p) => (
                  <tr key={p.slug}>
                    <td className="px-4 py-3 font-medium text-(--color-ink)">{p.name}</td>
                    <td className="px-4 py-3 text-(--color-ink-muted)">{p.category}</td>
                    <td className="px-4 py-3 text-(--color-ink)">${p.price}</td>
                    <td className="px-4 py-3 text-(--color-ink-muted)">{p.rating}</td>
                    <td className="px-4 py-3 text-(--color-ink-faint)">{p.sales.toLocaleString()}</td>
                  </tr>
                ))}
              </Tbody>
            </Table>
          </div>

          <div>
            <h3 className="mb-4 text-base font-medium text-(--color-ink)">Recent orders</h3>
            <Table>
              <Thead columns={["Order", "Client", "Product", "Amount", "Status"]} />
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
