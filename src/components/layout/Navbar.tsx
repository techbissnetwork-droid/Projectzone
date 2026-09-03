"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { ChevronDown, Menu, ShoppingCart, User, X } from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { primaryNav } from "@/lib/data/nav";
import { cn } from "@/lib/utils";
import { useCart } from "@/lib/cart-context";

export function Navbar() {
  const pathname = usePathname();
  const { count } = useCart();
  const [scrolled, setScrolled] = React.useState(false);
  const [mobileOpen, setMobileOpen] = React.useState(false);
  const [loginOpen, setLoginOpen] = React.useState(false);
  const [servicesOpen, setServicesOpen] = React.useState(false);

  React.useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  React.useEffect(() => {
    setMobileOpen(false);
    setLoginOpen(false);
  }, [pathname]);

  const isDashboardRoute = pathname?.startsWith("/dashboard");
  if (isDashboardRoute) return null;

  return (
    <header
      className={cn(
        "sticky top-0 z-50 w-full transition-all duration-300",
        scrolled ? "border-b border-(--color-border) glass" : "border-b border-transparent",
      )}
    >
      <Container size="wide">
        <div className="flex h-16 items-center justify-between sm:h-18">
          <Link href="/" className="focus-ring shrink-0 rounded-sm" aria-label="TECHBISS home">
            <Logo />
          </Link>

          <nav className="hidden items-center gap-1 lg:flex">
            {primaryNav.map((item) =>
              item.columns ? (
                <div
                  key={item.label}
                  className="relative"
                  onMouseEnter={() => setServicesOpen(true)}
                  onMouseLeave={() => setServicesOpen(false)}
                >
                  <Link
                    href={item.href}
                    className={cn(
                      "focus-ring flex items-center gap-1 rounded-full px-3.5 py-2 text-sm font-medium text-(--color-ink-muted) transition-colors hover:text-(--color-ink)",
                      pathname?.startsWith(item.href) && "text-(--color-ink)",
                    )}
                  >
                    {item.label}
                    <ChevronDown className={cn("h-3.5 w-3.5 transition-transform", servicesOpen && "rotate-180")} />
                  </Link>
                  <AnimatePresence>
                    {servicesOpen && (
                      <motion.div
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 8 }}
                        transition={{ duration: 0.18, ease: [0.16, 1, 0.3, 1] }}
                        className="absolute left-1/2 top-full z-10 w-[26rem] -translate-x-1/2 pt-3"
                      >
                        <div className="grid grid-cols-2 gap-1 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-3 shadow-xl">
                          {item.columns.flat().map((link) => (
                            <Link
                              key={link.label}
                              href={link.href}
                              className="focus-ring flex flex-col gap-0.5 rounded-(--radius-md) px-3.5 py-2.5 transition-colors hover:bg-(--color-surface-raised)"
                            >
                              <span className="text-sm font-medium text-(--color-ink)">{link.label}</span>
                              {link.description && (
                                <span className="text-xs text-(--color-ink-faint)">{link.description}</span>
                              )}
                            </Link>
                          ))}
                        </div>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </div>
              ) : (
                <Link
                  key={item.label}
                  href={item.href}
                  className={cn(
                    "focus-ring rounded-full px-3.5 py-2 text-sm font-medium text-(--color-ink-muted) transition-colors hover:text-(--color-ink)",
                    pathname === item.href && "text-(--color-ink)",
                  )}
                >
                  {item.label}
                </Link>
              ),
            )}
          </nav>

          <div className="flex items-center gap-1.5 sm:gap-2">
            <Link
              href="/marketplace/cart"
              className="focus-ring relative hidden h-10 w-10 items-center justify-center rounded-full text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised) hover:text-(--color-ink) sm:flex"
              aria-label="View cart"
            >
              <ShoppingCart className="h-[1.15rem] w-[1.15rem]" />
              {count > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-(--color-accent) text-[0.65rem] font-semibold text-white">
                  {count}
                </span>
              )}
            </Link>

            <div
              className="relative hidden sm:block"
              onMouseEnter={() => setLoginOpen(true)}
              onMouseLeave={() => setLoginOpen(false)}
            >
              <button
                type="button"
                className="focus-ring flex h-10 w-10 items-center justify-center rounded-full text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised) hover:text-(--color-ink)"
                aria-label="Login"
              >
                <User className="h-[1.15rem] w-[1.15rem]" />
              </button>
              <AnimatePresence>
                {loginOpen && (
                  <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 8 }}
                    transition={{ duration: 0.18, ease: [0.16, 1, 0.3, 1] }}
                    className="absolute right-0 top-full z-10 w-48 pt-3"
                  >
                    <div className="flex flex-col gap-1 rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-2 shadow-xl">
                      <p className="px-3 pb-1 pt-1.5 text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">
                        Sign in as
                      </p>
                      {[
                        { label: "Client", href: "/login/client" },
                        { label: "Staff", href: "/login/staff" },
                        { label: "Admin", href: "/login/admin" },
                      ].map((l) => (
                        <Link
                          key={l.label}
                          href={l.href}
                          className="focus-ring rounded-(--radius-sm) px-3 py-2 text-sm text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised) hover:text-(--color-ink)"
                        >
                          {l.label} Login
                        </Link>
                      ))}
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </div>

            <Button href="/contact" size="sm" variant="secondary" className="hidden sm:inline-flex">
              Get Started
            </Button>

            <button
              type="button"
              className="focus-ring flex h-10 w-10 items-center justify-center rounded-full text-(--color-ink) lg:hidden"
              onClick={() => setMobileOpen((v) => !v)}
              aria-label="Toggle menu"
              aria-expanded={mobileOpen}
            >
              {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </button>
          </div>
        </div>
      </Container>

      <AnimatePresence>
        {mobileOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] }}
            className="overflow-hidden border-t border-(--color-border) glass lg:hidden"
          >
            <Container size="wide" className="flex flex-col gap-1 py-4">
              {primaryNav.map((item) => (
                <Link
                  key={item.label}
                  href={item.href}
                  className="focus-ring rounded-(--radius-md) px-3 py-3 text-base font-medium text-(--color-ink) transition-colors hover:bg-(--color-surface-raised)"
                >
                  {item.label}
                </Link>
              ))}
              <div className="my-2 h-px bg-(--color-border)" />
              <div className="grid grid-cols-3 gap-2 px-1">
                {[
                  { label: "Client", href: "/login/client" },
                  { label: "Staff", href: "/login/staff" },
                  { label: "Admin", href: "/login/admin" },
                ].map((l) => (
                  <Link
                    key={l.label}
                    href={l.href}
                    className="focus-ring rounded-(--radius-md) border border-(--color-border-strong) px-3 py-2.5 text-center text-sm text-(--color-ink-muted) transition-colors hover:bg-(--color-surface-raised) hover:text-(--color-ink)"
                  >
                    {l.label}
                  </Link>
                ))}
              </div>
              <div className="mt-2 flex gap-2 px-1">
                <Button href="/marketplace/cart" variant="outline" size="md" className="flex-1">
                  Cart ({count})
                </Button>
                <Button href="/contact" variant="secondary" size="md" className="flex-1">
                  Get Started
                </Button>
              </div>
            </Container>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  );
}
