"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { adminNav } from "@/components/dashboard/navConfig";
import { staffDirectory } from "@/lib/data/dashboard";

export default function AdminStaffPage() {
  return (
    <RequireRole role="admin">
      <DashboardShell navItems={adminNav} title="Staff">
        <Table>
          <Thead columns={["Name", "Role", "Assigned clients", "Status"]} />
          <Tbody>
            {staffDirectory.map((s) => (
              <tr key={s.name}>
                <td className="px-4 py-3 font-medium text-(--color-ink)">{s.name}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{s.role}</td>
                <td className="px-4 py-3 text-(--color-ink)">{s.clients}</td>
                <td className="px-4 py-3">
                  <StatusPill status={s.status} />
                </td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </DashboardShell>
    </RequireRole>
  );
}
