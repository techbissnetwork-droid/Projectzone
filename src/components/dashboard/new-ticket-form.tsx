"use client";

import { useState, FormEvent } from "react";
import { Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-4 py-3 text-[14px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

export function NewTicketForm() {
  const [sent, setSent] = useState(false);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSent(true);
  }

  if (sent) {
    return (
      <div className="flex h-fit flex-col items-start gap-3 rounded-xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-6">
        <span className="flex size-10 items-center justify-center rounded-full bg-gold-400 text-ink-950">
          <Check className="size-5" strokeWidth={2.5} />
        </span>
        <div className="text-[15px] font-medium text-paper-50">Ticket submitted.</div>
        <p className="text-[13px] leading-relaxed text-paper-50/55">
          The TECHBISS support team will respond shortly.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex h-fit flex-col gap-4 rounded-xl border border-line-dark bg-ink-900/40 p-6">
      <div className="text-[15px] font-medium text-paper-50">New Support Ticket</div>
      <input className={cn(inputClass)} placeholder="Subject" required />
      <textarea className={cn(inputClass, "resize-none")} rows={4} placeholder="Describe the issue" required />
      <Button type="submit" className="w-full justify-center">
        Submit Ticket
      </Button>
    </form>
  );
}
