"use client";

import { Rocket, Settings } from "lucide-react";
import { RequireRole } from "@/components/dashboard/RequireRole";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { StatusPill } from "@/components/dashboard/Table";
import { Button } from "@/components/ui/Button";
import { clientNav } from "@/components/dashboard/navConfig";
import { clientOwnedProducts } from "@/lib/data/dashboard";

export default function ClientProductsPage() {
  return (
    <RequireRole role="client">
      <DashboardShell navItems={clientNav} title="My Products">
        <div className="flex flex-col gap-5">
          <p className="text-sm text-(--color-ink-muted)">
            Manage and redeploy the platforms you&apos;ve purchased from the marketplace.
          </p>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {clientOwnedProducts.map((p) => (
              <div key={p.name} className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6">
                <div className="flex items-start justify-between">
                  <div
                    className="flex h-12 w-12 items-center justify-center rounded-(--radius-md) text-base font-medium text-white"
                    style={{ background: `linear-gradient(135deg, ${p.gradient[0]}, ${p.gradient[1]})` }}
                  >
                    {p.name.slice(0, 1)}
                  </div>
                  <StatusPill status={p.status} />
                </div>
                <h3 className="mt-4 text-base font-medium text-(--color-ink)">{p.name}</h3>
                <p className="mt-1 text-sm text-(--color-ink-faint)">{p.url}</p>
                <p className="mt-1 text-xs text-(--color-ink-faint)">Updated {p.updated}</p>
                <div className="mt-5 flex gap-2">
                  <Button href="/installer" variant="secondary" size="sm" icon={<Rocket className="h-3.5 w-3.5" />} iconPosition="left">
                    Redeploy
                  </Button>
                  <Button variant="outline" size="sm" icon={<Settings className="h-3.5 w-3.5" />} iconPosition="left">
                    Manage
                  </Button>
                </div>
              </div>
            ))}
          </div>
          <div className="rounded-(--radius-lg) border border-dashed border-(--color-border-strong) p-8 text-center">
            <p className="text-sm text-(--color-ink-muted)">Looking for something new?</p>
            <Button href="/marketplace" variant="outline" className="mt-4">
              Browse Marketplace
            </Button>
          </div>
        </div>
      </DashboardShell>
    </RequireRole>
  );
}
