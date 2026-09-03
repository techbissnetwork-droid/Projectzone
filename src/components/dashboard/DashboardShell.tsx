"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Bell, LogOut, Menu, Search, X } from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { cn } from "@/lib/utils";
import { useAuth } from "@/lib/auth-context";

export type NavItem = { label: string; href: string; icon: React.ComponentType<{ className?: string }> };

export function DashboardShell({
  navItems,
  title,
  children,
}: {
  navItems: NavItem[];
  title: string;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const router = useRouter();
  const [mobileOpen, setMobileOpen] = React.useState(false);

  function handleLogout() {
    logout();
    router.push("/");
  }

  return (
    <div className="min-h-screen bg-(--color-canvas)">
      <div className="flex">
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-(--color-border) bg-(--color-surface) lg:flex">
          <div className="flex h-16 items-center border-b border-(--color-border) px-6">
            <Link href="/">
              <Logo />
            </Link>
          </div>
          <nav className="flex-1 overflow-y-auto px-3 py-5">
            <div className="flex flex-col gap-1">
              {navItems.map((item) => {
                const active = pathname === item.href;
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                      "focus-ring flex items-center gap-3 rounded-(--radius-sm) px-3 py-2.5 text-sm transition-colors",
                      active
                        ? "bg-(--color-accent)/12 text-(--color-accent-2)"
                        : "text-(--color-ink-muted) hover:bg-(--color-surface-raised) hover:text-(--color-ink)",
                    )}
                  >
                    <item.icon className="h-4 w-4" />
                    {item.label}
                  </Link>
                );
              })}
            </div>
          </nav>
          <div className="border-t border-(--color-border) p-4">
            <div className="flex items-center gap-3 rounded-(--radius-md) p-2">
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[linear-gradient(135deg,#4b5bff,#17c3ff)] text-xs font-medium text-white">
                {user?.name
                  .split(" ")
                  .map((n) => n[0])
                  .join("")}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-(--color-ink)">{user?.name}</p>
                <p className="truncate text-xs text-(--color-ink-faint)">{user?.email}</p>
              </div>
              <button
                type="button"
                onClick={handleLogout}
                aria-label="Log out"
                className="focus-ring flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-(--color-ink-faint) transition-colors hover:bg-(--color-surface-raised) hover:text-red-400"
              >
                <LogOut className="h-4 w-4" />
              </button>
            </div>
          </div>
        </aside>

        {mobileOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <div className="absolute inset-0 bg-black/60" onClick={() => setMobileOpen(false)} />
            <aside className="absolute inset-y-0 left-0 flex w-72 flex-col bg-(--color-surface)">
              <div className="flex h-16 items-center justify-between border-b border-(--color-border) px-5">
                <Logo />
                <button type="button" onClick={() => setMobileOpen(false)} className="focus-ring text-(--color-ink-muted)">
                  <X className="h-5 w-5" />
                </button>
              </div>
              <nav className="flex-1 overflow-y-auto px-3 py-5">
                <div className="flex flex-col gap-1">
                  {navItems.map((item) => {
                    const active = pathname === item.href;
                    return (
                      <Link
                        key={item.href}
                        href={item.href}
                        onClick={() => setMobileOpen(false)}
                        className={cn(
                          "focus-ring flex items-center gap-3 rounded-(--radius-sm) px-3 py-2.5 text-sm transition-colors",
                          active
                            ? "bg-(--color-accent)/12 text-(--color-accent-2)"
                            : "text-(--color-ink-muted) hover:bg-(--color-surface-raised) hover:text-(--color-ink)",
                        )}
                      >
                        <item.icon className="h-4 w-4" />
                        {item.label}
                      </Link>
                    );
                  })}
                </div>
              </nav>
              <div className="border-t border-(--color-border) p-4">
                <button
                  type="button"
                  onClick={handleLogout}
                  className="focus-ring flex w-full items-center gap-2 rounded-(--radius-sm) px-3 py-2.5 text-sm text-(--color-ink-muted) hover:bg-(--color-surface-raised)"
                >
                  <LogOut className="h-4 w-4" /> Log out
                </button>
              </div>
            </aside>
          </div>
        )}

        <div className="flex min-h-screen flex-1 flex-col lg:pl-64">
          <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-(--color-border) bg-(--color-canvas)/95 px-5 backdrop-blur sm:px-8">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setMobileOpen(true)}
                className="focus-ring flex h-9 w-9 items-center justify-center rounded-full text-(--color-ink-muted) lg:hidden"
                aria-label="Open menu"
              >
                <Menu className="h-5 w-5" />
              </button>
              <h1 className="text-base font-medium text-(--color-ink) sm:text-lg">{title}</h1>
            </div>
            <div className="flex items-center gap-2">
              <button className="focus-ring hidden h-9 w-9 items-center justify-center rounded-full text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised) sm:flex" aria-label="Search">
                <Search className="h-4 w-4" />
              </button>
              <button className="focus-ring relative flex h-9 w-9 items-center justify-center rounded-full text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised)" aria-label="Notifications">
                <Bell className="h-4 w-4" />
                <span className="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-(--color-accent)" />
              </button>
            </div>
          </header>
          <main className="flex-1 px-5 py-8 sm:px-8 sm:py-10">{children}</main>
        </div>
      </div>
    </div>
  );
}
