import type { Metadata } from "next";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { AddMailboxForm } from "@/components/dashboard/add-mailbox-form";
import { mailboxes, domains, getSite } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Business Email",
  description: "Manage the professional mailboxes attached to your domains.",
};

export default function EmailPage() {
  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Business Email"
        description="Professional mailboxes on your own domain, included with hosting."
      />

      <Card className="!p-0 overflow-hidden">
        <ul>
          {mailboxes.map((mbx, i) => {
            const site = mbx.siteId ? getSite(mbx.siteId) : undefined;
            const pct = Math.min(100, Math.round((mbx.storageUsedGB / mbx.storageLimitGB) * 100));
            return (
              <li
                key={mbx.id}
                className={
                  "flex flex-col gap-3 px-5 py-4 sm:px-6" +
                  (i !== mailboxes.length - 1 ? " border-b border-[var(--color-border)]" : "")
                }
              >
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="text-[14.5px] font-medium text-[var(--color-ink)]">{mbx.address}</p>
                    {site && (
                      <p className="mt-0.5 text-[12.5px] text-[var(--color-ink-faint)]">{site.name}</p>
                    )}
                  </div>
                  <span className="text-[12.5px] text-[var(--color-ink-muted)]">
                    {mbx.storageUsedGB} GB / {mbx.storageLimitGB} GB
                  </span>
                </div>
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-white/[0.06]">
                  <div className="h-full rounded-full bg-[var(--color-accent)]" style={{ width: `${pct}%` }} />
                </div>
              </li>
            );
          })}
        </ul>
      </Card>

      <AddMailboxForm domains={domains} />
    </div>
  );
}
