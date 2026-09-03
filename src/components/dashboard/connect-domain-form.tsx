"use client";

import { useState } from "react";
import { CheckCircle2, Globe, ArrowRight } from "lucide-react";
import { Card } from "@/components/dashboard/page-header";

const DOMAIN_RE = /^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})+$/i;

type Status = "idle" | "loading" | "success";

export function ConnectDomainForm() {
  const [value, setValue] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>("idle");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const trimmed = value.trim();
    if (!DOMAIN_RE.test(trimmed)) {
      setError("Enter a valid domain, like yourbusiness.com");
      return;
    }
    setError(null);
    setStatus("loading");
    // Real implementation: POST /api/domains { domain: trimmed }
    setTimeout(() => {
      setStatus("success");
    }, 800);
  }

  if (status === "success") {
    return (
      <Card className="flex flex-col items-start gap-3 border-[var(--color-live)]/30 bg-[var(--color-live-soft)] sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <CheckCircle2 className="size-5 shrink-0 text-[var(--color-live)]" strokeWidth={1.75} />
          <div>
            <p className="text-[14px] font-medium text-[var(--color-ink)]">
              {value.trim()} is being connected
            </p>
            <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-muted)]">
              DNS propagation can take up to 24 hours. We'll email you once it's active.
            </p>
          </div>
        </div>
        <button
          onClick={() => {
            setValue("");
            setStatus("idle");
          }}
          className="shrink-0 text-[12.5px] font-medium text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
        >
          Connect another
        </button>
      </Card>
    );
  }

  return (
    <Card>
      <div className="flex items-center gap-2.5">
        <Globe className="size-4 text-[var(--color-ink-faint)]" strokeWidth={1.75} />
        <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Connect a Domain</h3>
      </div>
      <p className="mt-1.5 text-[13px] text-[var(--color-ink-muted)]">
        Already own a domain? Point it at TECHBISS in a few minutes.
      </p>
      <form onSubmit={handleSubmit} className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start">
        <div className="flex-1">
          <input
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder="yourbusiness.com"
            className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]"
          />
          {error && <p className="mt-1.5 text-[12px] text-[#ff8a8a]">{error}</p>}
        </div>
        <button
          type="submit"
          disabled={status === "loading"}
          className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white disabled:opacity-60"
        >
          {status === "loading" ? (
            <>
              <span className="size-3.5 animate-spin rounded-full border-2 border-[var(--color-bg)] border-t-transparent" />
              Connecting…
            </>
          ) : (
            <>
              Connect
              <ArrowRight className="size-3.5" strokeWidth={2} />
            </>
          )}
        </button>
      </form>
    </Card>
  );
}
