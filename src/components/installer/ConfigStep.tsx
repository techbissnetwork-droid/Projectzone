"use client";

import * as React from "react";
import { ArrowLeft, ArrowRight, Check } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input, Label, Select } from "@/components/ui/Field";
import { cn } from "@/lib/utils";
import { products } from "@/lib/data/products";
import type { InstallerState } from "@/components/installer/types";

const timezones = [
  "UTC (Coordinated Universal Time)",
  "America/New_York (Eastern Time)",
  "America/Los_Angeles (Pacific Time)",
  "Europe/London (GMT)",
  "Europe/Berlin (CET)",
  "Asia/Singapore (SGT)",
  "Asia/Tokyo (JST)",
  "Australia/Sydney (AEST)",
];

function strengthOf(pw: string) {
  let score = 0;
  if (pw.length >= 8) score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/\d/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  return Math.min(score, 4);
}

const strengthLabels = ["Very weak", "Weak", "Fair", "Strong", "Excellent"];
const strengthColors = ["bg-red-500", "bg-orange-500", "bg-amber-500", "bg-emerald-500", "bg-emerald-400"];

export function ConfigStep({
  state,
  update,
  onBack,
  onContinue,
}: {
  state: InstallerState;
  update: (patch: Partial<InstallerState>) => void;
  onBack: () => void;
  onContinue: () => void;
}) {
  const strength = strengthOf(state.adminPassword);
  const themeOptions = products.slice(0, 6);

  const canContinue =
    state.siteName.trim().length > 1 &&
    state.adminUser.trim().length > 1 &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.adminEmail) &&
    state.adminPassword.length >= 8 &&
    !!state.theme;

  return (
    <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
      <h2 className="text-lg font-medium text-(--color-ink)">Configure your site</h2>
      <p className="mt-1 text-sm text-(--color-ink-muted)">Set up your site details and administrator account.</p>

      <div className="mt-6 flex flex-col gap-4">
        <div>
          <Label htmlFor="site-name" required>
            Site name
          </Label>
          <Input
            id="site-name"
            value={state.siteName}
            onChange={(e) => update({ siteName: e.target.value })}
            placeholder="Acme Inc."
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="admin-user" required>
              Admin username
            </Label>
            <Input
              id="admin-user"
              value={state.adminUser}
              onChange={(e) => update({ adminUser: e.target.value })}
              placeholder="admin"
            />
          </div>
          <div>
            <Label htmlFor="admin-email" required>
              Admin email
            </Label>
            <Input
              id="admin-email"
              type="email"
              value={state.adminEmail}
              onChange={(e) => update({ adminEmail: e.target.value })}
              placeholder="you@company.com"
            />
          </div>
        </div>

        <div>
          <Label htmlFor="admin-password" required>
            Admin password
          </Label>
          <Input
            id="admin-password"
            type="password"
            value={state.adminPassword}
            onChange={(e) => update({ adminPassword: e.target.value })}
            placeholder="At least 8 characters"
          />
          {state.adminPassword && (
            <div className="mt-2">
              <div className="flex gap-1">
                {Array.from({ length: 4 }).map((_, i) => (
                  <span
                    key={i}
                    className={cn(
                      "h-1 flex-1 rounded-full bg-(--color-surface-raised)",
                      i < strength && strengthColors[strength],
                    )}
                  />
                ))}
              </div>
              <p className="mt-1.5 text-xs text-(--color-ink-faint)">{strengthLabels[strength]}</p>
            </div>
          )}
        </div>

        <div>
          <Label htmlFor="timezone">Timezone</Label>
          <Select id="timezone" value={state.timezone} onChange={(e) => update({ timezone: e.target.value })}>
            {timezones.map((tz) => (
              <option key={tz} value={tz}>
                {tz}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <Label required>Starting theme</Label>
          <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
            {themeOptions.map((p) => (
              <button
                key={p.slug}
                type="button"
                onClick={() => update({ theme: p.slug })}
                className={cn(
                  "focus-ring group relative aspect-square overflow-hidden rounded-(--radius-md) border-2 transition-all duration-200",
                  state.theme === p.slug ? "border-(--color-accent)" : "border-transparent hover:border-(--color-border-strong)",
                )}
                style={{ background: `linear-gradient(135deg, ${p.gradient[0]}, ${p.gradient[1]})` }}
                title={p.name}
              >
                {state.theme === p.slug && (
                  <span className="absolute right-1.5 top-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-white text-(--color-accent)">
                    <Check className="h-2.5 w-2.5" />
                  </span>
                )}
              </button>
            ))}
          </div>
          {state.theme && (
            <p className="mt-2 text-xs text-(--color-ink-faint)">
              Selected: {themeOptions.find((p) => p.slug === state.theme)?.name}
            </p>
          )}
        </div>
      </div>

      <div className="mt-8 flex gap-3">
        <Button variant="outline" size="lg" icon={<ArrowLeft className="h-4 w-4" />} iconPosition="left" onClick={onBack}>
          Back
        </Button>
        <Button
          variant="secondary"
          size="lg"
          className="flex-1"
          icon={<ArrowRight className="h-4 w-4" />}
          disabled={!canContinue}
          onClick={onContinue}
        >
          Review &amp; Deploy
        </Button>
      </div>
    </div>
  );
}
