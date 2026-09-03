"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import {
  LayoutDashboard,
  Globe,
  Package,
  Link2,
  Server,
  Mail,
  CreditCard,
  LifeBuoy,
  Settings,
  Menu,
  X,
  ArrowLeft,
} from "lucide-react";
import { cn } from "@/lib/utils";

const EASE = [0.16, 1, 0.3, 1] as const;

const NAV_LINKS = [
  { label: "Overview", href: "/dashboard", icon: LayoutDashboard, exact: true },
  { label: "My Websites", href: "/dashboard/websites", icon: Globe },
  { label: "My Products", href: "/dashboard/products", icon: Package },
  { label: "Domains", href: "/dashboard/domains", icon: Link2 },
  { label: "Hosting", href: "/dashboard/hosting", icon: Server },
  { label: "Business Email", href: "/dashboard/email", icon: Mail },
  { label: "Billing", href: "/dashboard/billing", icon: CreditCard },
  { label: "Support", href: "/dashboard/support", icon: LifeBuoy },
  { label: "Settings", href: "/dashboard/settings", icon: Settings },
];

// Mock signed-in account — in production this would come from the session/auth provider.
const ACCOUNT = { name: "Alex Morgan", email: "alex@yourbusiness.com" };

function isActive(pathname: string | null, href: string, exact?: boolean) {
  if (!pathname) return false;
  return exact ? pathname === href : pathname === href || pathname.startsWith(href + "/");
}

function AccountChip() {
  const initial = ACCOUNT.name
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2);
  return (
    <div className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] bg-white/[0.03] px-3 py-2.5">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-soft)] text-[12px] font-semibold text-[var(--color-accent-ink)]">
        {initial}
      </span>
      <div className="min-w-0">
        <p className="truncate text-[13px] font-medium text-[var(--color-ink)]">{ACCOUNT.name}</p>
        <p className="truncate text-[11.5px] text-[var(--color-ink-faint)]">{ACCOUNT.email}</p>
      </div>
    </div>
  );
}

function NavList({ pathname, onNavigate }: { pathname: string | null; onNavigate?: () => void }) {
  return (
    <nav className="flex flex-1 flex-col gap-1">
      {NAV_LINKS.map((link) => {
        const active = isActive(pathname, link.href, link.exact);
        const Icon = link.icon;
        return (
          <Link
            key={link.href}
            href={link.href}
            onClick={onNavigate}
            className={cn(
              "group flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13.5px] font-medium transition-colors duration-200",
              active
                ? "bg-white/[0.07] text-[var(--color-ink)]"
                : "text-[var(--color-ink-muted)] hover:bg-white/[0.04] hover:text-[var(--color-ink)]",
            )}
          >
            <Icon
              className={cn(
                "size-4 shrink-0",
                active ? "text-[var(--color-accent-ink)]" : "text-[var(--color-ink-faint)] group-hover:text-[var(--color-ink-muted)]",
              )}
              strokeWidth={1.75}
            />
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const [panelOpen, setPanelOpen] = useState(false);
  const [prevPathname, setPrevPathname] = useState(pathname);

  if (pathname !== prevPathname) {
    setPrevPathname(pathname);
    setPanelOpen(false);
  }

  useEffect(() => {
    document.documentElement.style.overflow = panelOpen ? "hidden" : "";
    return () => {
      document.documentElement.style.overflow = "";
    };
  }, [panelOpen]);

  return (
    <div className="min-h-screen bg-[var(--color-bg)]">
      {/* Desktop sidebar */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[252px] flex-col border-r border-[var(--color-border)] bg-[var(--color-bg-soft)] lg:flex">
        <div className="flex flex-col gap-6 px-5 pt-6 pb-4">
          <Link href="/" className="font-mono-label text-[15px] font-semibold tracking-[0.08em] text-[var(--color-ink)]">
            TECHBISS
          </Link>
          <AccountChip />
        </div>

        <div className="flex-1 overflow-y-auto scrollbar-none px-3">
          <NavList pathname={pathname} />
        </div>

        <div className="border-t border-[var(--color-border)] px-5 py-4">
          <Link
            href="/"
            className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-ink-faint)] transition-colors hover:text-[var(--color-ink-muted)]"
          >
            <ArrowLeft className="size-3.5" strokeWidth={1.75} />
            Back to TECHBISS.com
          </Link>
        </div>
      </aside>

      {/* Mobile/tablet top bar */}
      <header className="sticky top-0 z-40 flex items-center justify-between border-b border-[var(--color-border)] bg-[var(--color-bg-soft)]/95 px-5 py-4 backdrop-blur-sm lg:hidden">
        <Link href="/" className="font-mono-label text-[14px] font-semibold tracking-[0.08em] text-[var(--color-ink)]">
          TECHBISS
        </Link>
        <button
          onClick={() => setPanelOpen(true)}
          aria-label="Open dashboard menu"
          className="flex size-9 items-center justify-center rounded-full border border-[var(--color-border-strong)] text-[var(--color-ink)]"
        >
          <Menu className="size-4" strokeWidth={1.75} />
        </button>
      </header>

      {/* Mobile slide-out panel */}
      <AnimatePresence>
        {panelOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.25, ease: EASE }}
              onClick={() => setPanelOpen(false)}
              className="fixed inset-0 z-50 bg-black/60 lg:hidden"
            />
            <motion.div
              initial={{ x: "-100%" }}
              animate={{ x: 0 }}
              exit={{ x: "-100%" }}
              transition={{ duration: 0.4, ease: EASE }}
              className="fixed inset-y-0 left-0 z-50 flex w-[82%] max-w-[320px] flex-col bg-[var(--color-bg-soft)] shadow-2xl lg:hidden"
            >
              <div className="flex items-center justify-between px-5 py-6">
                <span className="font-mono-label text-[14px] font-semibold tracking-[0.08em] text-[var(--color-ink)]">
                  TECHBISS
                </span>
                <button
                  onClick={() => setPanelOpen(false)}
                  aria-label="Close menu"
                  className="flex size-9 items-center justify-center rounded-full border border-[var(--color-border-strong)]"
                >
                  <X className="size-4" strokeWidth={1.75} />
                </button>
              </div>
              <div className="px-5 pb-4">
                <AccountChip />
              </div>
              <div className="flex-1 overflow-y-auto scrollbar-none px-3">
                <NavList pathname={pathname} onNavigate={() => setPanelOpen(false)} />
              </div>
              <div className="border-t border-[var(--color-border)] px-5 py-4">
                <Link
                  href="/"
                  onClick={() => setPanelOpen(false)}
                  className="inline-flex items-center gap-1.5 text-[12.5px] text-[var(--color-ink-faint)]"
                >
                  <ArrowLeft className="size-3.5" strokeWidth={1.75} />
                  Back to TECHBISS.com
                </Link>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>

      <main className="lg:pl-[252px]">
        <div className="mx-auto max-w-[1180px] px-5 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12">{children}</div>
      </main>
    </div>
  );
}
