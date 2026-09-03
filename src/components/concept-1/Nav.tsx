"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { Menu, X } from "lucide-react";
import { company } from "@/lib/site-data";
import { Button } from "@/components/concept-1/Button";
import { cn } from "@/lib/cn";

const navLinks = [
  { label: "Home", href: "/concept-1" },
  { label: "About", href: "/concept-1/about" },
  { label: "Services", href: "/concept-1/services" },
  { label: "Portfolio", href: "/concept-1/portfolio" },
  { label: "Pricing", href: "/concept-1/pricing" },
  { label: "Process", href: "/concept-1/process" },
  { label: "Technology", href: "/concept-1/technology" },
  { label: "Contact", href: "/concept-1/contact" },
];

export function Nav() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const pathname = usePathname();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    setOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [open]);

  const isActive = (href: string) =>
    href === "/concept-1" ? pathname === "/concept-1" : pathname?.startsWith(href);

  return (
    <header
      className={cn(
        "fixed inset-x-0 top-0 z-50 transition-colors duration-300",
        scrolled
          ? "border-b border-white/10 bg-black/60 backdrop-blur-xl"
          : "border-b border-transparent bg-transparent"
      )}
    >
      <nav className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 sm:px-8 lg:px-10">
        <Link
          href="/concept-1"
          className="text-lg font-semibold tracking-tight text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded-full"
        >
          {company.name}
          <span className="ml-1 bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-transparent">
            .
          </span>
        </Link>

        <div className="hidden items-center gap-1 lg:flex">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              aria-current={isActive(link.href) ? "page" : undefined}
              className={cn(
                "relative rounded-full px-4 py-2 text-sm font-medium text-neutral-300 transition-colors hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70",
                isActive(link.href) && "text-neutral-50"
              )}
            >
              {link.label}
              {isActive(link.href) ? (
                <span
                  aria-hidden="true"
                  className="absolute inset-x-4 -bottom-1 h-px bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500"
                />
              ) : null}
            </Link>
          ))}
        </div>

        <div className="hidden lg:block">
          <Button href="/concept-1/get-started" variant="primary" className="px-6 py-2.5 text-sm">
            Get Started
          </Button>
        </div>

        <button
          type="button"
          onClick={() => setOpen((prev) => !prev)}
          aria-expanded={open}
          aria-controls="mobile-nav-drawer"
          aria-label={open ? "Close menu" : "Open menu"}
          className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-neutral-100 backdrop-blur-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 lg:hidden"
        >
          {open ? <X className="h-5 w-5" aria-hidden="true" /> : <Menu className="h-5 w-5" aria-hidden="true" />}
        </button>
      </nav>

      <AnimatePresence>
        {open ? (
          <motion.div
            id="mobile-nav-drawer"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.25 }}
            className="fixed inset-0 top-20 z-40 bg-black/70 backdrop-blur-2xl lg:hidden"
          >
            <motion.ul
              initial="closed"
              animate="open"
              exit="closed"
              variants={{
                open: { transition: { staggerChildren: 0.06, delayChildren: 0.05 } },
                closed: {},
              }}
              className="flex h-full flex-col gap-2 px-6 pt-10"
            >
              {navLinks.map((link) => (
                <motion.li
                  key={link.href}
                  variants={{
                    open: { opacity: 1, y: 0 },
                    closed: { opacity: 0, y: 16 },
                  }}
                  transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                >
                  <Link
                    href={link.href}
                    onClick={() => setOpen(false)}
                    className="block rounded-2xl px-4 py-4 text-2xl font-medium tracking-tight text-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70"
                  >
                    {link.label}
                  </Link>
                </motion.li>
              ))}
              <motion.li
                variants={{
                  open: { opacity: 1, y: 0 },
                  closed: { opacity: 0, y: 16 },
                }}
                transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                className="mt-4"
              >
                <Button href="/concept-1/get-started" variant="primary" className="w-full">
                  Get Started
                </Button>
              </motion.li>
            </motion.ul>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </header>
  );
}
