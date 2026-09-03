"use client";

import { useState } from "react";
import { CheckCircle2 } from "lucide-react";
import { Card } from "@/components/dashboard/page-header";
import { cn } from "@/lib/utils";

type Status = "idle" | "loading" | "success";

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[12.5px] font-medium text-[var(--color-ink-muted)]">{label}</span>
      {children}
    </label>
  );
}

function inputClass() {
  return "w-full rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]";
}

function SubmitButton({ status, label }: { status: Status; label: string }) {
  return (
    <button
      type="submit"
      disabled={status === "loading"}
      className="inline-flex w-fit items-center justify-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white disabled:opacity-60"
    >
      {status === "loading" ? (
        <>
          <span className="size-3.5 animate-spin rounded-full border-2 border-[var(--color-bg)] border-t-transparent" />
          Saving…
        </>
      ) : status === "success" ? (
        <>
          <CheckCircle2 className="size-3.5" strokeWidth={2} />
          Saved
        </>
      ) : (
        label
      )}
    </button>
  );
}

function ProfileForm() {
  const [name, setName] = useState("Alex Morgan");
  const [email, setEmail] = useState("alex@yourbusiness.com");
  const [status, setStatus] = useState<Status>("idle");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("loading");
    // Real implementation: PATCH /api/account/profile { name, email }
    setTimeout(() => setStatus("success"), 700);
    setTimeout(() => setStatus("idle"), 2400);
  }

  return (
    <Card className="flex flex-col gap-5">
      <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Profile</h3>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Full name">
            <input value={name} onChange={(e) => setName(e.target.value)} className={inputClass()} />
          </Field>
          <Field label="Email">
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={inputClass()}
            />
          </Field>
        </div>
        <SubmitButton status={status} label="Save profile" />
      </form>
    </Card>
  );
}

function SecurityForm() {
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>("idle");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!current || next.length < 8) {
      setError("New password must be at least 8 characters.");
      return;
    }
    setError(null);
    setStatus("loading");
    // Real implementation: POST /api/account/password { current, next }
    setTimeout(() => {
      setStatus("success");
      setCurrent("");
      setNext("");
    }, 700);
    setTimeout(() => setStatus("idle"), 2400);
  }

  return (
    <Card className="flex flex-col gap-5">
      <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Security</h3>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Current password">
            <input
              type="password"
              value={current}
              onChange={(e) => setCurrent(e.target.value)}
              className={inputClass()}
            />
          </Field>
          <Field label="New password">
            <input
              type="password"
              value={next}
              onChange={(e) => setNext(e.target.value)}
              className={inputClass()}
            />
          </Field>
        </div>
        {error && <p className="text-[12px] text-[#ff8a8a]">{error}</p>}
        <SubmitButton status={status} label="Update password" />
      </form>
    </Card>
  );
}

const NOTIFICATION_PREFS = [
  { key: "product", label: "Product updates & new features", defaultOn: true },
  { key: "billing", label: "Billing & invoice reminders", defaultOn: true },
  { key: "domain", label: "Domain & SSL expiry alerts", defaultOn: true },
  { key: "support", label: "Support ticket replies", defaultOn: true },
  { key: "marketing", label: "Marketplace deals & promotions", defaultOn: false },
];

function NotificationsForm() {
  const [prefs, setPrefs] = useState<Record<string, boolean>>(
    Object.fromEntries(NOTIFICATION_PREFS.map((p) => [p.key, p.defaultOn])),
  );

  function toggle(key: string) {
    setPrefs((prev) => ({ ...prev, [key]: !prev[key] }));
    // Real implementation: PATCH /api/account/notifications { [key]: !prev[key] }
  }

  return (
    <Card className="flex flex-col gap-5">
      <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Notifications</h3>
      <div className="flex flex-col divide-y divide-[var(--color-border)]">
        {NOTIFICATION_PREFS.map((pref) => (
          <div key={pref.key} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
            <span className="text-[13.5px] text-[var(--color-ink)]">{pref.label}</span>
            <button
              role="switch"
              aria-checked={prefs[pref.key]}
              onClick={() => toggle(pref.key)}
              className={cn(
                "relative h-6 w-11 shrink-0 rounded-full transition-colors",
                prefs[pref.key] ? "bg-[var(--color-accent)]" : "bg-white/10",
              )}
            >
              <span
                className={cn(
                  "absolute top-0.5 size-5 rounded-full bg-white transition-transform",
                  prefs[pref.key] ? "translate-x-[22px]" : "translate-x-0.5",
                )}
              />
            </button>
          </div>
        ))}
      </div>
    </Card>
  );
}

export function SettingsForms() {
  return (
    <div className="flex flex-col gap-6">
      <ProfileForm />
      <SecurityForm />
      <NotificationsForm />
    </div>
  );
}
