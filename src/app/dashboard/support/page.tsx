import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { Badge } from "@/components/ui/eyebrow";
import { NewTicketForm } from "@/components/dashboard/new-ticket-form";
import { supportTickets } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Support",
  description: "Get help from the TECHBISS team and track your support tickets.",
};

function formatDate(d: string) {
  return new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export default function SupportPage() {
  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Support"
        description="Track your open tickets, or start a new one — our team typically replies within a few hours."
      />

      <Card className="!p-0 overflow-hidden">
        <div className="border-b border-[var(--color-border)] px-6 py-4">
          <h3 className="text-[14.5px] font-medium text-[var(--color-ink)]">Your tickets</h3>
        </div>
        <ul>
          {supportTickets.map((ticket, i) => (
            <li
              key={ticket.id}
              className={
                "flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6" +
                (i !== supportTickets.length - 1 ? " border-b border-[var(--color-border)]" : "")
              }
            >
              <div>
                <p className="text-[14px] font-medium text-[var(--color-ink)]">{ticket.subject}</p>
                <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-faint)]">
                  {ticket.id} · updated {formatDate(ticket.updated)}
                </p>
              </div>
              <Badge tone={ticket.status === "open" ? "build" : "live"}>{ticket.status}</Badge>
            </li>
          ))}
        </ul>
      </Card>

      <NewTicketForm />

      <Card className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <p className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
            Help resources
          </p>
          <p className="mt-1.5 text-[15px] font-medium text-[var(--color-ink)]">
            Prefer to talk it through?
          </p>
          <p className="mt-1 text-[13px] text-[var(--color-ink-muted)]">
            Reach the TECHBISS team directly, or browse what we can build or fix for you.
          </p>
        </div>
        <div className="flex shrink-0 flex-wrap gap-2.5">
          <Link
            href="/contact"
            className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-4 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
          >
            Contact us
            <ArrowRight className="size-3.5" strokeWidth={2} />
          </Link>
          <Link
            href="/services"
            className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-4 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
          >
            Browse services
            <ArrowRight className="size-3.5" strokeWidth={2} />
          </Link>
        </div>
      </Card>
    </div>
  );
}
