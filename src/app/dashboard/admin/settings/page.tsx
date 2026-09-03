"use client";

import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui/Field";
import { adminNav } from "@/components/dashboard/navConfig";
import { useAuth } from "@/lib/auth-context";

export default function AdminSettingsPage() {
  const { user } = useAuth();

  return (
    <RequireRole role="admin">
      <DashboardShell navItems={adminNav} title="Settings">
        <div className="max-w-lg rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
          <h3 className="text-base font-medium text-(--color-ink)">Account details</h3>
          <div className="mt-5 flex flex-col gap-4">
            <div>
              <Label htmlFor="name">Full name</Label>
              <Input id="name" defaultValue={user?.name} />
            </div>
            <div>
              <Label htmlFor="email">Email</Label>
              <Input id="email" defaultValue={user?.email} />
            </div>
          </div>
          <Button variant="secondary" className="mt-6">
            Save changes
          </Button>
        </div>
      </DashboardShell>
    </RequireRole>
  );
}
