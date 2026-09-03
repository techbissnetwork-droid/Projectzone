import type { Metadata } from "next";
import Link from "next/link";
import { Palette, ArrowUpRight } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { websites } from "@/lib/data/dashboard";
import { products } from "@/lib/data/marketplace";

export const metadata: Metadata = { title: "Brand Studio" };

export default function BrandStudioIndexPage() {
  const owned = Array.from(new Set(websites.map((w) => w.product)))
    .map((name) => products.find((p) => p.name === name))
    .filter(Boolean) as typeof products;

  return (
    <div>
      <DashboardPageHeader
        title="Brand Studio"
        subtitle="Choose a product to customize with your brand, content and business details."
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {owned.map((p) => (
          <Link
            key={p.slug}
            href={`/dashboard/brand-studio/${p.slug}`}
            className="group flex flex-col justify-between rounded-xl border border-line-dark bg-ink-900/40 p-6 hover:border-line-dark-strong"
          >
            <div>
              <span className="flex size-10 items-center justify-center rounded-lg border border-line-dark bg-ink-950 text-gold-400">
                <Palette className="size-4.5" strokeWidth={1.75} />
              </span>
              <div className="mt-5 text-[15px] font-medium text-paper-50">{p.name}</div>
              <div className="mt-1 text-[12.5px] text-paper-50/45">{p.industry}</div>
            </div>
            <span className="mt-6 flex items-center gap-1.5 text-[13px] font-medium text-paper-50/60 group-hover:text-gold-400">
              Open Editor
              <ArrowUpRight className="size-3.5" />
            </span>
          </Link>
        ))}
      </div>
    </div>
  );
}
