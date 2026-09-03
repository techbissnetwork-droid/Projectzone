"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { staffNav } from "@/components/dashboard/navConfig";
import { assignedClients } from "@/lib/data/dashboard";

export default function StaffClientsPage() {
  return (
    <RequireRole role="staff">
      <DashboardShell navItems={staffNav} title="My Clients">
        <Table>
          <Thead columns={["Client", "Engagement", "Phase", "Next milestone"]} />
          <Tbody>
            {assignedClients.map((c) => (
              <tr key={c.name}>
                <td className="px-4 py-3 font-medium text-(--color-ink)">{c.name}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{c.engagement}</td>
                <td className="px-4 py-3">
                  <StatusPill status={c.phase} />
                </td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{c.nextMilestone}</td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </DashboardShell>
    </RequireRole>
  );
}
