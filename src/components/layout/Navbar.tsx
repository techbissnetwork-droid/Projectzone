"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { primaryNav, site } from "@/lib/data/site";
import { Button } from "@/components/ui/Button";
import { MobileMenu } from "./MobileMenu";
import { cn } from "@/lib/utils/cn";

export function Navbar() {
  const pathname = usePathname();
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [lastPathname, setLastPathname] = useState(pathname);

  if (pathname !== lastPathname) {
    setLastPathname(pathname);
    setMenuOpen(false);
  }

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.documentElement.style.overflow = menuOpen ? "hidden" : "";
  }, [menuOpen]);

  useEffect(() => {
    if (!menuOpen) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") setMenuOpen(false);
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [menuOpen]);

  return (
    <>
      <header
        className={cn(
          "fixed inset-x-0 top-0 z-50 transition-[padding,background-color,backdrop-filter,border-color] duration-500",
          scrolled || menuOpen
            ? "border-b border-line bg-ink/80 py-3 backdrop-blur-xl"
            : "border-b border-transparent bg-transparent py-5 md:py-7",
        )}
      >
        <div className="container-content flex items-center justify-between">
          <Link
            href="/"
            className="text-eyebrow relative z-10 flex items-center gap-2 font-semibold tracking-[0.16em] text-paper"
          >
            TECHBISS
          </Link>

          <nav className="hidden items-center gap-1 lg:flex">
            {primaryNav.map((item) => {
              const active = pathname.startsWith(item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    "relative rounded-full px-4 py-2 text-sm tracking-tight transition-colors duration-300",
                    active
                      ? "text-paper"
                      : "text-paper-dim hover:text-paper",
                  )}
                >
                  {item.label}
                  {active && (
                    <motion.span
                      layoutId="nav-active"
                      className="absolute inset-x-3 -bottom-0.5 h-px bg-gold"
                      transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
                    />
                  )}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-3">
            <div className="hidden lg:block">
              <Button href="/contact" size="md">
                Start a Project
              </Button>
            </div>
            <button
              type="button"
              aria-label={menuOpen ? "Close menu" : "Open menu"}
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen((v) => !v)}
              className="relative z-10 flex h-11 w-11 flex-col items-center justify-center gap-[5px] rounded-full border border-line-strong lg:hidden"
            >
              <motion.span
                animate={
                  menuOpen
                    ? { rotate: 45, y: 6.5 }
                    : { rotate: 0, y: 0 }
                }
                className="h-px w-4 bg-paper"
              />
              <motion.span
                animate={{ opacity: menuOpen ? 0 : 1 }}
                className="h-px w-4 bg-paper"
              />
              <motion.span
                animate={
                  menuOpen
                    ? { rotate: -45, y: -6.5 }
                    : { rotate: 0, y: 0 }
                }
                className="h-px w-4 bg-paper"
              />
            </button>
          </div>
        </div>
      </header>

      <AnimatePresence>
        {menuOpen && <MobileMenu onClose={() => setMenuOpen(false)} />}
      </AnimatePresence>

      <span className="sr-only">{site.name}</span>
    </>
  );
}
