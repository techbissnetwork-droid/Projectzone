import type { Metadata } from "next";
import Link from "next/link";
import { Eye, Palette, Rocket } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { StatusPill } from "@/components/ui/status-pill";
import { Button } from "@/components/ui/button";
import { websites } from "@/lib/data/dashboard";

export const metadata: Metadata = { title: "My Websites" };

function slugify(name: string) {
  return name.toLowerCase().replace(/\s+/g, "-");
}

export default function WebsitesPage() {
  return (
    <div>
      <DashboardPageHeader
        title="My Websites"
        subtitle="Every website built or purchased through TECHBISS, in one place."
        action={
          <Button href="/marketplace" size="sm" arrow>
            Add a Website
          </Button>
        }
      />

      <div className="grid gap-4">
        {websites.map((w) => (
          <div
            key={w.id}
            className="flex flex-col gap-5 rounded-xl border border-line-dark bg-ink-900/40 p-6 sm:flex-row sm:items-center sm:justify-between"
          >
            <div className="flex items-center gap-4">
              <span
                className="size-12 shrink-0 rounded-xl"
                style={{ background: `linear-gradient(135deg, ${w.accent}44, transparent)` }}
              />
              <div>
                <div className="text-[16px] font-medium text-paper-50">{w.name}</div>
                <div className="mt-0.5 text-[13px] text-paper-50/45">
                  {w.domain ?? "No domain connected"} · {w.product}
                </div>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <StatusPill status={w.status} />
                  <StatusPill status={w.hosting} />
                  <StatusPill status={w.ssl} />
                </div>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Link
                href={`/marketplace/product/${slugify(w.product)}/preview`}
                className="flex items-center gap-1.5 rounded-full border border-line-dark-strong px-3.5 py-2 text-[12.5px] font-medium text-paper-50/70 hover:border-line-dark-strong hover:text-paper-50"
              >
                <Eye className="size-3.5" />
                Preview
              </Link>
              <Link
                href={`/dashboard/brand-studio/${slugify(w.product)}`}
                className="flex items-center gap-1.5 rounded-full border border-line-dark-strong px-3.5 py-2 text-[12.5px] font-medium text-paper-50/70 hover:border-line-dark-strong hover:text-paper-50"
              >
                <Palette className="size-3.5" />
                Brand Studio
              </Link>
              <Link
                href={`/marketplace/launch/${slugify(w.product)}`}
                className="flex items-center gap-1.5 rounded-full bg-gold-400 px-3.5 py-2 text-[12.5px] font-medium text-ink-950 hover:bg-gold-300"
              >
                <Rocket className="size-3.5" />
                Launch
              </Link>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
