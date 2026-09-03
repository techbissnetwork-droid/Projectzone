"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, X } from "lucide-react";
import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";

const navLinks = [
  { label: "Home", href: "/concept-2" },
  { label: "About", href: "/concept-2/about" },
  { label: "Services", href: "/concept-2/services" },
  { label: "Portfolio", href: "/concept-2/portfolio" },
  { label: "Pricing", href: "/concept-2/pricing" },
  { label: "Process", href: "/concept-2/process" },
  { label: "Technology", href: "/concept-2/technology" },
  { label: "Contact", href: "/concept-2/contact" },
];

export function Nav() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const pathname = usePathname();

  // Close the mobile menu on navigation. This adjusts state during render
  // (React's recommended pattern for resetting state when a prop/derived
  // value changes) rather than in an effect, avoiding an extra render pass.
  const [lastPathname, setLastPathname] = useState(pathname);
  if (pathname !== lastPathname) {
    setLastPathname(pathname);
    setOpen(false);
  }

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [open]);

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  return (
    <header
      className={cn(
        "sticky top-0 z-50 border-b border-neutral-200 bg-white transition-shadow duration-300",
        scrolled && "shadow-[0_1px_0_0_rgba(0,0,0,0.04)]"
      )}
    >
      <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 sm:px-8 lg:px-10">
        <Link
          href="/concept-2"
          className={cn(
            fontSerif,
            "rounded-sm text-xl tracking-tight text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
          )}
        >
          TECHBISS
        </Link>

        <nav aria-label="Primary" className="hidden items-center gap-10 lg:flex">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="group relative rounded-sm py-1 text-sm text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
            >
              {link.label}
              <span className="absolute left-0 -bottom-0.5 h-px w-full origin-left scale-x-0 bg-neutral-900 transition-transform duration-300 group-hover:scale-x-100" />
            </Link>
          ))}
        </nav>

        <div className="hidden lg:block">
          <Link
            href="/concept-2/get-started"
            className="inline-flex min-h-[44px] items-center justify-center rounded-full bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-opacity duration-300 hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2"
          >
            Get Started
          </Link>
        </div>

        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          aria-controls="concept-2-mobile-menu"
          aria-label={open ? "Close menu" : "Open menu"}
          className="inline-flex h-11 w-11 items-center justify-center rounded-full text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 lg:hidden"
        >
          {open ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
        </button>
      </div>

      <div
        id="concept-2-mobile-menu"
        aria-hidden={!open}
        className={cn(
          "fixed inset-x-0 top-20 bottom-0 z-40 flex flex-col bg-white transition-opacity duration-300 lg:hidden",
          open ? "pointer-events-auto opacity-100" : "pointer-events-none opacity-0"
        )}
      >
        <nav aria-label="Mobile" className="flex flex-1 flex-col justify-center gap-1 px-8">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              tabIndex={open ? 0 : -1}
              className={cn(
                fontSerif,
                "border-b border-neutral-100 py-4 text-3xl text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
              )}
            >
              {link.label}
            </Link>
          ))}
        </nav>
        <div className="px-8 pb-10">
          <Link
            href="/concept-2/get-started"
            tabIndex={open ? 0 : -1}
            className="inline-flex min-h-[44px] w-full items-center justify-center rounded-full bg-neutral-900 px-6 py-4 text-sm font-medium text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2"
          >
            Get Started
          </Link>
        </div>
      </div>
    </header>
  );
}
