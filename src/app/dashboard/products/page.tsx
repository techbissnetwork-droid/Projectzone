import type { Metadata } from "next";
import Link from "next/link";
import { Palette, Download } from "lucide-react";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { Button } from "@/components/ui/button";
import { websites } from "@/lib/data/dashboard";
import { products } from "@/lib/data/marketplace";
import { formatPrice } from "@/lib/utils";

export const metadata: Metadata = { title: "My Products" };

export default function ProductsPage() {
  const owned = Array.from(new Set(websites.map((w) => w.product)))
    .map((name) => products.find((p) => p.name === name))
    .filter(Boolean) as typeof products;

  return (
    <div>
      <DashboardPageHeader
        title="My Products"
        subtitle="Themes, applications and digital products you own."
        action={
          <Button href="/marketplace" size="sm" arrow>
            Browse Marketplace
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {owned.map((p) => (
          <div key={p.slug} className="flex flex-col justify-between rounded-xl border border-line-dark bg-ink-900/40 p-6">
            <div>
              <div
                className="aspect-[16/10] rounded-lg"
                style={{ background: `linear-gradient(135deg, ${p.accent}33, transparent)` }}
              />
              <div className="mt-4 flex items-center justify-between">
                <div className="text-[15px] font-medium text-paper-50">{p.name}</div>
                <span className="text-[12.5px] text-paper-50/40">{formatPrice(p.priceCents)}</span>
              </div>
              <div className="mt-1 text-[12.5px] text-paper-50/45">{p.license}</div>
            </div>
            <div className="mt-5 flex items-center gap-2">
              <Link
                href={`/dashboard/brand-studio/${p.slug}`}
                className="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-gold-400 px-3.5 py-2 text-[12.5px] font-medium text-ink-950 hover:bg-gold-300"
              >
                <Palette className="size-3.5" />
                Brand Studio
              </Link>
              <Link
                href={`/marketplace/product/${p.slug}`}
                className="flex items-center justify-center gap-1.5 rounded-full border border-line-dark-strong px-3.5 py-2 text-[12.5px] font-medium text-paper-50/70 hover:text-paper-50"
              >
                <Download className="size-3.5" />
              </Link>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
