"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatCard } from "@/components/dashboard/StatCard";
import { StatusPill } from "@/components/dashboard/Table";
import { staffNav } from "@/components/dashboard/navConfig";
import { useAuth } from "@/lib/auth-context";
import { assignedClients, staffTickets } from "@/lib/data/dashboard";

export default function StaffDashboardPage() {
  return (
    <RequireRole role="staff">
      <DashboardShell navItems={staffNav} title="Overview">
        <StaffOverview />
      </DashboardShell>
    </RequireRole>
  );
}

function StaffOverview() {
  const { user } = useAuth();

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h2 className="text-xl font-medium text-(--color-ink)">Welcome back, {user?.name.split(" ")[0]}</h2>
        <p className="mt-1 text-sm text-(--color-ink-muted)">Here&apos;s your engagement queue for today.</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Assigned clients" value="8" />
        <StatCard label="Open tickets" value="3" />
        <StatCard label="Deployments this week" value="5" delta="+2" />
        <StatCard label="SLA compliance" value="98.4%" delta="+0.6%" />
      </div>

      <div>
        <h3 className="mb-4 text-base font-medium text-(--color-ink)">Active engagements</h3>
        <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface)">
          {assignedClients.map((c) => (
            <div key={c.name} className="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-sm font-medium text-(--color-ink)">{c.name}</p>
                <p className="mt-1 text-xs text-(--color-ink-faint)">{c.engagement}</p>
              </div>
              <div className="flex items-center gap-4">
                <span className="text-xs text-(--color-ink-faint)">{c.nextMilestone}</span>
                <StatusPill status={c.phase} />
              </div>
            </div>
          ))}
        </div>
      </div>

      <div>
        <h3 className="mb-4 text-base font-medium text-(--color-ink)">Ticket queue</h3>
        <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface)">
          {staffTickets.map((t) => (
            <div key={t.id} className="flex items-center justify-between gap-4 p-4">
              <div className="min-w-0">
                <p className="truncate text-sm text-(--color-ink)">{t.subject}</p>
                <p className="text-xs text-(--color-ink-faint)">
                  {t.client} · Updated {t.updated}
                </p>
              </div>
              <StatusPill status={t.priority} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
