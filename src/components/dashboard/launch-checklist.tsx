"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { motion, AnimatePresence } from "framer-motion";
import { Check, ArrowRight, Rocket, ExternalLink, Copy, CheckCircle2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import type { ChecklistItem, MySite } from "@/lib/data/dashboard";

const EASE = [0.16, 1, 0.3, 1] as const;

function slugifyDomain(site: MySite) {
  if (site.domain) return site.domain;
  const slug = site.name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
  return `${slug}.techbiss.site`;
}

export function LaunchChecklist({
  site,
  initialItems,
}: {
  site: MySite;
  initialItems: ChecklistItem[];
}) {
  const [items, setItems] = useState<ChecklistItem[]>(initialItems);
  const [launching, setLaunching] = useState(false);
  const [launched, setLaunched] = useState(false);
  const [copied, setCopied] = useState(false);

  const doneCount = items.filter((i) => i.done).length;
  const progress = Math.round((doneCount / items.length) * 100);

  const liveUrl = useMemo(() => `https://${slugifyDomain(site)}`, [site]);

  function toggle(key: string) {
    setItems((prev) => prev.map((i) => (i.key === key ? { ...i, done: !i.done } : i)));
    // Real implementation: PATCH /api/sites/{siteId}/checklist/{key}
  }

  function handleLaunch() {
    setLaunching(true);
    // Real implementation: POST /api/sites/{siteId}/launch
    setTimeout(() => {
      setLaunching(false);
      setLaunched(true);
    }, 900);
  }

  function copyUrl() {
    navigator.clipboard?.writeText(liveUrl).catch(() => {});
    setCopied(true);
    setTimeout(() => setCopied(false), 1600);
  }

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Launch"
        title={`Launch ${site.name}`}
        description="Work through the checklist below, then launch whenever you're ready — completing it first gives visitors the best first impression."
      />

      <AnimatePresence mode="wait">
        {launched ? (
          <motion.div
            key="success"
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, ease: EASE }}
          >
            <Card className="flex flex-col items-center gap-5 border-[var(--color-live)]/30 bg-[var(--color-live-soft)] py-12 text-center">
              <span className="flex size-14 items-center justify-center rounded-full bg-[var(--color-live)]/15">
                <Rocket className="size-6 text-[var(--color-live)]" strokeWidth={1.75} />
              </span>
              <div>
                <h2 className="text-[20px] font-medium text-[var(--color-ink)]">Your website is live</h2>
                <p className="mt-2 max-w-[440px] text-[13.5px] text-[var(--color-ink-muted)]">
                  {site.name} is now published and reachable at your launch URL below.
                </p>
              </div>
              <div className="flex flex-wrap items-center justify-center gap-2 rounded-full border border-[var(--color-border-strong)] bg-black/20 px-5 py-3">
                <a
                  href={liveUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 text-[13.5px] font-medium text-[var(--color-ink)]"
                >
                  {liveUrl}
                  <ExternalLink className="size-3.5" strokeWidth={1.75} />
                </a>
                <button
                  onClick={copyUrl}
                  aria-label="Copy launch URL"
                  className="flex size-7 items-center justify-center rounded-full border border-[var(--color-border-strong)] text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
                >
                  {copied ? (
                    <CheckCircle2 className="size-3.5 text-[var(--color-live)]" strokeWidth={1.75} />
                  ) : (
                    <Copy className="size-3.5" strokeWidth={1.75} />
                  )}
                </button>
              </div>
              <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
                <Link
                  href={`/dashboard/brand-studio/${site.id}`}
                  className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
                >
                  Back to Brand Studio
                </Link>
                <Link
                  href="/dashboard/websites"
                  className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-ink)] px-5 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white"
                >
                  My Websites
                  <ArrowRight className="size-3.5" strokeWidth={2} />
                </Link>
              </div>
            </Card>
          </motion.div>
        ) : (
          <motion.div
            key="checklist"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.4, ease: EASE }}
            className="flex flex-col gap-6"
          >
            <Card className="flex flex-col gap-5">
              <div className="flex items-center justify-between">
                <span className="text-[13.5px] font-medium text-[var(--color-ink)]">
                  {doneCount} of {items.length} complete
                </span>
                <span className="font-mono-label text-[12px] text-[var(--color-ink-faint)]">{progress}%</span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-white/[0.06]">
                <motion.div
                  className="h-full rounded-full bg-[var(--color-accent)]"
                  initial={false}
                  animate={{ width: `${progress}%` }}
                  transition={{ duration: 0.5, ease: EASE }}
                />
              </div>
            </Card>

            <Card className="!p-0">
              <ul>
                {items.map((item, i) => (
                  <li
                    key={item.key}
                    className={cn(
                      "flex items-center gap-4 px-5 py-4 sm:px-6",
                      i !== items.length - 1 && "border-b border-[var(--color-border)]",
                    )}
                  >
                    <button
                      onClick={() => toggle(item.key)}
                      aria-pressed={item.done}
                      aria-label={`Mark "${item.label}" as ${item.done ? "not done" : "done"}`}
                      className={cn(
                        "flex size-6 shrink-0 items-center justify-center rounded-full border transition-colors",
                        item.done
                          ? "border-[var(--color-live)] bg-[var(--color-live)] text-[#06110b]"
                          : "border-[var(--color-border-strong)] text-transparent hover:border-[var(--color-ink-muted)]",
                      )}
                    >
                      <Check className="size-3.5" strokeWidth={3} />
                    </button>
                    <span
                      className={cn(
                        "flex-1 text-[14px]",
                        item.done ? "text-[var(--color-ink-muted)] line-through" : "text-[var(--color-ink)]",
                      )}
                    >
                      {item.label}
                    </span>
                  </li>
                ))}
              </ul>
            </Card>

            {progress < 100 && (
              <p className="text-[12.5px] text-[var(--color-ink-faint)]">
                Tip: sites that launch with every item checked see far fewer post-launch support tickets.
              </p>
            )}

            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={handleLaunch}
                disabled={launching}
                className="inline-flex items-center gap-2 rounded-full bg-[var(--color-ink)] px-6 py-3 text-[14px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white disabled:opacity-60"
              >
                {launching ? (
                  <>
                    <span className="size-3.5 animate-spin rounded-full border-2 border-[var(--color-bg)] border-t-transparent" />
                    Launching…
                  </>
                ) : (
                  <>
                    Launch Website
                    <ArrowRight className="size-4" strokeWidth={2} />
                  </>
                )}
              </button>
              <Link
                href={`/dashboard/brand-studio/${site.id}`}
                className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-5 py-3 text-[13.5px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
              >
                Back to Brand Studio
              </Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
