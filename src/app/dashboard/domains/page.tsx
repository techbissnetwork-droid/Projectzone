import type { Metadata } from "next";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import { ConnectDomainForm } from "@/components/dashboard/connect-domain-form";
import { Badge } from "@/components/ui/eyebrow";
import { domains, getSite } from "@/lib/data/dashboard";

export const metadata: Metadata = {
  title: "Domains",
  description: "Manage the domains connected to your TECHBISS websites.",
};

function formatDate(d: string) {
  return new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export default function DomainsPage() {
  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Domains"
        description="Every domain connected to your account, and which website it points to."
      />

      <Card className="!p-0 overflow-hidden">
        <ul>
          {domains.map((domain, i) => {
            const site = domain.siteId ? getSite(domain.siteId) : undefined;
            return (
              <li
                key={domain.id}
                className={
                  "flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6" +
                  (i !== domains.length - 1 ? " border-b border-[var(--color-border)]" : "")
                }
              >
                <div>
                  <p className="text-[14.5px] font-medium text-[var(--color-ink)]">{domain.name}</p>
                  <p className="mt-1 text-[12.5px] text-[var(--color-ink-faint)]">
                    {site ? `Linked to ${site.name}` : "Not linked to a website"}
                  </p>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-[12.5px] text-[var(--color-ink-muted)]">
                    Expires {formatDate(domain.expires)}
                  </span>
                  <Badge tone={domain.status === "active" ? "live" : "build"}>{domain.status}</Badge>
                </div>
              </li>
            );
          })}
        </ul>
      </Card>

      <ConnectDomainForm />
    </div>
  );
}
