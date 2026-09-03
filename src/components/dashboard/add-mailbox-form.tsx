"use client";

import { useState } from "react";
import { CheckCircle2, Mail, ArrowRight } from "lucide-react";
import { Card } from "@/components/dashboard/page-header";
import type { DomainRecord } from "@/lib/data/dashboard";

type Status = "idle" | "loading" | "success";

export function AddMailboxForm({ domains }: { domains: DomainRecord[] }) {
  const [prefix, setPrefix] = useState("");
  const [domain, setDomain] = useState(domains[0]?.name ?? "");
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>("idle");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const trimmed = prefix.trim();
    if (!/^[a-z0-9._-]{1,40}$/i.test(trimmed)) {
      setError("Use letters, numbers, dots or dashes only.");
      return;
    }
    if (!domain) {
      setError("Choose a domain for this mailbox.");
      return;
    }
    setError(null);
    setStatus("loading");
    // Real implementation: POST /api/mailboxes { address: `${trimmed}@${domain}` }
    setTimeout(() => setStatus("success"), 800);
  }

  if (status === "success") {
    return (
      <Card className="flex items-center gap-3 border-[var(--color-live)]/30 bg-[var(--color-live-soft)]">
        <CheckCircle2 className="size-5 shrink-0 text-[var(--color-live)]" strokeWidth={1.75} />
        <div className="flex-1">
          <p className="text-[14px] font-medium text-[var(--color-ink)]">
            {prefix.trim()}@{domain} created
          </p>
          <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-muted)]">
            Setup instructions have been sent to your account email.
          </p>
        </div>
        <button
          onClick={() => {
            setPrefix("");
            setStatus("idle");
          }}
          className="shrink-0 text-[12.5px] font-medium text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
        >
          Add another
        </button>
      </Card>
    );
  }

  return (
    <Card>
      <div className="flex items-center gap-2.5">
        <Mail className="size-4 text-[var(--color-ink-faint)]" strokeWidth={1.75} />
        <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Add Mailbox</h3>
      </div>
      <form onSubmit={handleSubmit} className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start">
        <div className="flex flex-1 items-center gap-2 rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-[var(--color-accent)]">
          <input
            value={prefix}
            onChange={(e) => setPrefix(e.target.value)}
            placeholder="hello"
            className="min-w-0 flex-1 bg-transparent text-[13.5px] text-[var(--color-ink)] outline-none placeholder:text-[var(--color-ink-faint)]"
          />
          <span className="text-[13.5px] text-[var(--color-ink-faint)]">@</span>
          <select
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            className="bg-transparent text-[13.5px] text-[var(--color-ink-muted)] outline-none"
          >
            {domains.map((d) => (
              <option key={d.id} value={d.name} className="bg-[var(--color-surface)]">
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <button
          type="submit"
          disabled={status === "loading"}
          className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white disabled:opacity-60"
        >
          {status === "loading" ? (
            <>
              <span className="size-3.5 animate-spin rounded-full border-2 border-[var(--color-bg)] border-t-transparent" />
              Creating…
            </>
          ) : (
            <>
              Create
              <ArrowRight className="size-3.5" strokeWidth={2} />
            </>
          )}
        </button>
      </form>
      {error && <p className="mt-1.5 text-[12px] text-[#ff8a8a]">{error}</p>}
    </Card>
  );
}
