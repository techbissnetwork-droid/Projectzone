"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { Menu, X, ArrowUpRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { primaryNav, mobileNavExtra } from "@/lib/data/nav";
import { Container } from "@/components/ui/container";

export function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const pathname = usePathname();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.documentElement.style.overflow = open ? "hidden" : "";
    return () => {
      document.documentElement.style.overflow = "";
    };
  }, [open]);

  const isDashboard = pathname?.startsWith("/dashboard");
  if (isDashboard) return null;

  return (
    <>
      <header
        className={cn(
          "fixed inset-x-0 top-0 z-50 transition-all duration-500 ease-out",
          scrolled ? "py-3" : "py-6"
        )}
      >
        <Container wide>
          <div
            className={cn(
              "flex items-center justify-between rounded-2xl border transition-all duration-500 ease-out",
              scrolled
                ? "border-line-dark bg-ink-950/80 px-4 py-2.5 backdrop-blur-xl shadow-[0_1px_0_0_rgba(255,255,255,0.04)]"
                : "border-transparent bg-transparent px-2 py-2"
            )}
          >
            <Link href="/" className="flex items-center gap-2 px-2">
              <span className="text-[15px] font-semibold tracking-[-0.01em] text-paper-50">
                TECHBISS
              </span>
            </Link>

            <nav className="hidden items-center gap-1 lg:flex">
              {primaryNav.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    "rounded-full px-4 py-2 text-[13px] font-medium text-paper-50/70 transition-colors hover:text-paper-50",
                    pathname === item.href && "text-paper-50"
                  )}
                >
                  {item.label}
                </Link>
              ))}
            </nav>

            <div className="hidden items-center gap-3 lg:flex">
              <Link
                href="/marketplace"
                className="text-[13px] font-medium text-paper-50/70 transition-colors hover:text-paper-50"
              >
                Browse Themes
              </Link>
              <Link
                href="/contact"
                className="group inline-flex items-center gap-1.5 rounded-full bg-gold-400 px-4 py-2.5 text-[13px] font-medium text-ink-950 transition-colors hover:bg-gold-300"
              >
                Start a Project
                <ArrowUpRight className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
              </Link>
            </div>

            <button
              aria-label="Open menu"
              onClick={() => setOpen(true)}
              className="flex size-10 items-center justify-center rounded-full text-paper-50 lg:hidden"
            >
              <Menu className="size-5" />
            </button>
          </div>
        </Container>
      </header>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.3 }}
            className="fixed inset-0 z-[60] flex flex-col bg-ink-950 lg:hidden"
          >
            <Container wide className="flex items-center justify-between py-6">
              <span className="text-[15px] font-semibold text-paper-50">TECHBISS</span>
              <button
                aria-label="Close menu"
                onClick={() => setOpen(false)}
                className="flex size-10 items-center justify-center rounded-full border border-line-dark text-paper-50"
              >
                <X className="size-5" />
              </button>
            </Container>

            <div className="flex flex-1 flex-col justify-between overflow-y-auto px-5 pb-10">
              <nav className="mt-4 flex flex-col">
                {[...primaryNav, ...mobileNavExtra].map((item, i) => (
                  <motion.div
                    key={item.href}
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.05 * i, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
                    className="border-b border-line-dark py-4"
                  >
                    <Link
                      href={item.href}
                      onClick={() => setOpen(false)}
                      className="text-[32px] font-medium tracking-tight text-paper-50"
                    >
                      {item.label}
                    </Link>
                  </motion.div>
                ))}
              </nav>

              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.4, duration: 0.4 }}
                className="mt-8 flex flex-col gap-3"
              >
                <Link
                  href="/marketplace"
                  onClick={() => setOpen(false)}
                  className="flex items-center justify-center rounded-full border border-line-dark-strong px-6 py-4 text-[15px] font-medium text-paper-50"
                >
                  Browse Themes
                </Link>
                <Link
                  href="/contact"
                  onClick={() => setOpen(false)}
                  className="flex items-center justify-center rounded-full bg-gold-400 px-6 py-4 text-[15px] font-medium text-ink-950"
                >
                  Start a Project
                </Link>
              </motion.div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}
