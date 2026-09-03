"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Table, Thead, Tbody, StatusPill } from "@/components/dashboard/Table";
import { staffNav } from "@/components/dashboard/navConfig";
import { staffTickets } from "@/lib/data/dashboard";

export default function StaffTicketsPage() {
  return (
    <RequireRole role="staff">
      <DashboardShell navItems={staffNav} title="Ticket Queue">
        <Table>
          <Thead columns={["Ticket", "Client", "Subject", "Priority", "Updated"]} />
          <Tbody>
            {staffTickets.map((t) => (
              <tr key={t.id}>
                <td className="px-4 py-3 text-(--color-ink)">{t.id}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{t.client}</td>
                <td className="px-4 py-3 text-(--color-ink-muted)">{t.subject}</td>
                <td className="px-4 py-3">
                  <StatusPill status={t.priority} />
                </td>
                <td className="px-4 py-3 text-(--color-ink-faint)">{t.updated}</td>
              </tr>
            ))}
          </Tbody>
        </Table>
      </DashboardShell>
    </RequireRole>
  );
}
