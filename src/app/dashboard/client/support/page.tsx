"use client";

import * as React from "react";
import { Plus } from "lucide-react";
import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatusPill } from "@/components/dashboard/Table";
import { Button } from "@/components/ui/Button";
import { Input, Label, Textarea } from "@/components/ui/Field";
import { clientNav } from "@/components/dashboard/navConfig";
import { supportTickets } from "@/lib/data/dashboard";

export default function ClientSupportPage() {
  const [open, setOpen] = React.useState(false);

  return (
    <RequireRole role="client">
      <DashboardShell navItems={clientNav} title="Support">
        <div className="flex flex-col gap-6">
          <div className="flex items-center justify-between">
            <p className="text-sm text-(--color-ink-muted)">{supportTickets.length} tickets on your account</p>
            <Button variant="secondary" size="sm" icon={<Plus className="h-4 w-4" />} iconPosition="left" onClick={() => setOpen((v) => !v)}>
              New ticket
            </Button>
          </div>

          {open && (
            <form className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
              <div className="flex flex-col gap-4">
                <div>
                  <Label htmlFor="subject" required>
                    Subject
                  </Label>
                  <Input id="subject" placeholder="What do you need help with?" />
                </div>
                <div>
                  <Label htmlFor="details" required>
                    Details
                  </Label>
                  <Textarea id="details" placeholder="Describe the issue in detail..." />
                </div>
              </div>
              <div className="mt-5 flex gap-2">
                <Button type="submit" variant="secondary" onClick={(e) => { e.preventDefault(); setOpen(false); }}>
                  Submit ticket
                </Button>
                <Button variant="ghost" onClick={() => setOpen(false)}>
                  Cancel
                </Button>
              </div>
            </form>
          )}

          <div className="flex flex-col divide-y divide-(--color-border) rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface)">
            {supportTickets.map((t) => (
              <div key={t.id} className="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm font-medium text-(--color-ink)">{t.subject}</p>
                  <p className="mt-1 text-xs text-(--color-ink-faint)">
                    {t.id} · {t.priority} priority · Updated {t.updated}
                  </p>
                </div>
                <StatusPill status={t.status} />
              </div>
            ))}
          </div>
        </div>
      </DashboardShell>
    </RequireRole>
  );
}
