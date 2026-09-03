"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { useScroll, useMotionValueEvent, AnimatePresence } from "framer-motion";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { MobileMenu } from "@/components/layout/mobile-menu";

const NAV_LINKS = [
  { label: "Services", href: "/services" },
  { label: "Solutions", href: "/solutions" },
  { label: "Marketplace", href: "/marketplace" },
  { label: "Work", href: "/work" },
  { label: "Process", href: "/process" },
  { label: "About", href: "/about" },
];

export function SiteHeader() {
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const pathname = usePathname();
  const [prevPathname, setPrevPathname] = useState(pathname);
  const { scrollY } = useScroll();

  useMotionValueEvent(scrollY, "change", (latest) => {
    setScrolled(latest > 24);
  });

  if (pathname !== prevPathname) {
    setPrevPathname(pathname);
    setMenuOpen(false);
  }

  useEffect(() => {
    document.documentElement.style.overflow = menuOpen ? "hidden" : "";
    return () => {
      document.documentElement.style.overflow = "";
    };
  }, [menuOpen]);

  const isDashboard = pathname?.startsWith("/dashboard");

  return (
    <>
      <header
        className={cn(
          "fixed inset-x-0 top-0 z-50 transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]",
          isDashboard && "hidden",
        )}
      >
        <div
          className={cn(
            "mx-auto flex items-center justify-between transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]",
            scrolled
              ? "mt-3 max-w-[1080px] rounded-full border border-[var(--color-border-strong)] bg-[#0a0b0dcc] px-5 py-2.5 backdrop-blur-xl shadow-[0_8px_30px_rgba(0,0,0,0.35)]"
              : "max-w-[1400px] px-6 py-6 sm:px-8 lg:px-10",
          )}
        >
          <Link
            href="/"
            className="font-mono-label text-[15px] font-semibold tracking-[0.08em] text-[var(--color-ink)]"
          >
            TECHBISS
          </Link>

          <nav className="hidden items-center gap-1 lg:flex">
            {NAV_LINKS.map((link) => {
              const active =
                pathname === link.href || (link.href !== "/" && pathname?.startsWith(link.href));
              return (
                <Link
                  key={link.href}
                  href={link.href}
                  className={cn(
                    "relative rounded-full px-3.5 py-2 text-[13px] font-medium transition-colors duration-200",
                    active
                      ? "text-[var(--color-ink)]"
                      : "text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]",
                  )}
                >
                  {link.label}
                </Link>
              );
            })}
          </nav>

          <div className="hidden items-center gap-2 lg:flex">
            <Button href="/marketplace" variant="ghost" size="sm" icon={false}>
              Browse Themes
            </Button>
            <Button href="/contact" variant="primary" size="sm">
              Start a Project
            </Button>
          </div>

          <button
            onClick={() => setMenuOpen(true)}
            aria-label="Open menu"
            className="flex flex-col items-end gap-[5px] p-2 lg:hidden"
          >
            <span className="h-px w-6 bg-[var(--color-ink)]" />
            <span className="h-px w-4 bg-[var(--color-ink)]" />
          </button>
        </div>
      </header>

      <AnimatePresence>
        {menuOpen && <MobileMenu onClose={() => setMenuOpen(false)} />}
      </AnimatePresence>
    </>
  );
}
