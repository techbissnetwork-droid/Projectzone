"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-4 py-3 text-[14px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

function Toggle({ label, hint, defaultOn = true }: { label: string; hint: string; defaultOn?: boolean }) {
  const [on, setOn] = useState(defaultOn);
  return (
    <div className="flex items-center justify-between border-b border-line-dark py-4 last:border-0">
      <div>
        <div className="text-[14px] font-medium text-paper-50">{label}</div>
        <div className="mt-0.5 text-[12.5px] text-paper-50/45">{hint}</div>
      </div>
      <button
        onClick={() => setOn((v) => !v)}
        className={cn(
          "relative h-6 w-11 shrink-0 rounded-full transition-colors",
          on ? "bg-gold-400" : "bg-ink-700"
        )}
      >
        <span
          className={cn(
            "absolute top-0.5 size-5 rounded-full bg-white transition-transform",
            on ? "translate-x-[22px]" : "translate-x-0.5"
          )}
        />
      </button>
    </div>
  );
}

export function SettingsForm() {
  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <div className="rounded-xl border border-line-dark bg-ink-900/40 p-6">
        <div className="text-[15px] font-medium text-paper-50">Business Profile</div>
        <div className="mt-5 flex flex-col gap-4">
          <input className={inputClass} defaultValue="TECHBISS User" placeholder="Full name" />
          <input className={inputClass} defaultValue="team@techbiss.com" placeholder="Email" />
          <input className={inputClass} placeholder="Business name" />
          <input className={inputClass} placeholder="Phone number" />
          <Button className="w-fit">Save Changes</Button>
        </div>
      </div>

      <div className="rounded-xl border border-line-dark bg-ink-900/40 p-6">
        <div className="text-[15px] font-medium text-paper-50">Notifications</div>
        <div className="mt-2">
          <Toggle label="Product updates" hint="Updates to purchased themes and applications" />
          <Toggle label="Billing alerts" hint="Invoices, renewals and payment issues" />
          <Toggle label="Support replies" hint="Responses to your support tickets" />
          <Toggle label="Marketing emails" hint="New marketplace products and offers" defaultOn={false} />
        </div>
      </div>
    </div>
  );
}
