"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { adminNav } from "@/components/dashboard/navConfig";
import { adminClients } from "@/lib/data/dashboard";

export default function AdminClientsPage() {
  return (
    <RequireRole role="admin">
      <DashboardShell navItems={adminNav} title="Clients">
        <Table>
          <Thead columns={["Client", "Plan", "MRR", "Health", "Client since"]} />
          <Tbody>
            {adminClients.map((c) => (
              <tr key={c.name}>
                <td className="px-4 py-3 font-medium text-(--color-ink)">{c.name}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{c.plan}</td>
                <td className="px-4 py-3 text-(--color-ink)">{c.mrr}</td>
                <td className="px-4 py-3">
                  <StatusPill status={c.health} />
                </td>
                <td className="px-4 py-3 text-(--color-ink-faint)">{c.since}</td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </DashboardShell>
    </RequireRole>
  );
}
