"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import * as Icons from "lucide-react";
import { cn } from "@/lib/utils";
import { dashboardNav } from "@/lib/data/dashboard-nav";

function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = (Icons as unknown as Record<string, Icons.LucideIcon>)[name] ?? Icons.Circle;
  return <Cmp className={className} strokeWidth={1.75} />;
}

export function DashboardSidebar({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <div className="flex h-full flex-col">
      <Link href="/" className="flex items-center gap-2 px-2 py-1">
        <span className="text-[15px] font-semibold tracking-[-0.01em] text-paper-50">
          TECHBISS
        </span>
      </Link>

      <nav className="mt-8 flex flex-1 flex-col gap-6 overflow-y-auto">
        {dashboardNav.map((group) => (
          <div key={group.group}>
            <div className="font-mono-label px-2 text-[10px] uppercase text-paper-50/30">
              {group.group}
            </div>
            <div className="mt-2 flex flex-col gap-0.5">
              {group.items.map((item) => {
                const active =
                  pathname === item.href ||
                  (item.href !== "/dashboard" && pathname?.startsWith(item.href));
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={onNavigate}
                    className={cn(
                      "flex items-center gap-3 rounded-lg px-2.5 py-2 text-[13.5px] font-medium transition-colors",
                      active
                        ? "bg-gold-500/10 text-gold-300"
                        : "text-paper-50/60 hover:bg-ink-900/60 hover:text-paper-50"
                    )}
                  >
                    <Icon name={item.icon} className="size-4" />
                    {item.label}
                  </Link>
                );
              })}
            </div>
          </div>
        ))}
      </nav>

      <Link
        href="/marketplace"
        className="mt-4 flex items-center justify-between rounded-lg border border-line-dark px-3 py-2.5 text-[12.5px] font-medium text-paper-50/60 hover:border-line-dark-strong hover:text-paper-50"
      >
        Browse Marketplace
        <Icons.ArrowUpRight className="size-3.5" />
      </Link>
    </div>
  );
}
