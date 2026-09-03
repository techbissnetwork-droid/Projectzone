"use client";

import { useState } from "react";
import { CheckCircle2, ArrowRight } from "lucide-react";
import { Card } from "@/components/dashboard/page-header";

type Status = "idle" | "loading" | "success";

export function NewTicketForm() {
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>("idle");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!subject.trim() || !message.trim()) {
      setError("Please fill in both a subject and a message.");
      return;
    }
    setError(null);
    setStatus("loading");
    // Real implementation: POST /api/support/tickets { subject, message }
    setTimeout(() => setStatus("success"), 800);
  }

  if (status === "success") {
    return (
      <Card className="flex items-center gap-3 border-[var(--color-live)]/30 bg-[var(--color-live-soft)]">
        <CheckCircle2 className="size-5 shrink-0 text-[var(--color-live)]" strokeWidth={1.75} />
        <div className="flex-1">
          <p className="text-[14px] font-medium text-[var(--color-ink)]">Ticket submitted</p>
          <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-muted)]">
            We&rsquo;ll follow up by email — usually within a few hours.
          </p>
        </div>
        <button
          onClick={() => {
            setSubject("");
            setMessage("");
            setStatus("idle");
          }}
          className="shrink-0 text-[12.5px] font-medium text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
        >
          New ticket
        </button>
      </Card>
    );
  }

  return (
    <Card>
      <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">New Ticket</h3>
      <form onSubmit={handleSubmit} className="mt-4 flex flex-col gap-3">
        <input
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          placeholder="Subject"
          className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]"
        />
        <textarea
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Describe your issue or question…"
          rows={4}
          className="w-full resize-none rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] leading-relaxed text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]"
        />
        {error && <p className="text-[12px] text-[#ff8a8a]">{error}</p>}
        <button
          type="submit"
          disabled={status === "loading"}
          className="inline-flex w-fit items-center justify-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white disabled:opacity-60"
        >
          {status === "loading" ? (
            <>
              <span className="size-3.5 animate-spin rounded-full border-2 border-[var(--color-bg)] border-t-transparent" />
              Submitting…
            </>
          ) : (
            <>
              Submit ticket
              <ArrowRight className="size-3.5" strokeWidth={2} />
            </>
          )}
        </button>
      </form>
    </Card>
  );
}
