"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Container } from "@/components/ui/container";
import { ArrowUpRight } from "lucide-react";

const COLUMNS = [
  {
    title: "Company",
    links: [
      { label: "About", href: "/about" },
      { label: "Work", href: "/work" },
      { label: "Process", href: "/process" },
      { label: "Contact", href: "/contact" },
    ],
  },
  {
    title: "Services",
    links: [
      { label: "Websites", href: "/services/website-development" },
      { label: "Apps", href: "/services/mobile-app-development" },
      { label: "E-commerce", href: "/services/ecommerce" },
      { label: "Hosting", href: "/services/hosting-infrastructure" },
      { label: "Security", href: "/services/ssl-security" },
      { label: "Automation", href: "/services/automation" },
    ],
  },
  {
    title: "Marketplace",
    links: [
      { label: "Browse Themes", href: "/marketplace" },
      { label: "Categories", href: "/marketplace/categories" },
      { label: "Best Sellers", href: "/marketplace?sort=best-sellers" },
      { label: "New Releases", href: "/marketplace?sort=new" },
      { label: "Sell on TECHBISS", href: "/marketplace/sell" },
    ],
  },
  {
    title: "Platform",
    links: [
      { label: "My Websites", href: "/dashboard/websites" },
      { label: "My Products", href: "/dashboard/products" },
      { label: "Brand Studio", href: "/dashboard/products" },
      { label: "Support", href: "/dashboard/support" },
    ],
  },
];

export function SiteFooter() {
  const pathname = usePathname();
  if (pathname?.startsWith("/dashboard")) return null;

  return (
    <footer className="relative border-t border-[var(--color-border)] bg-[var(--color-bg-soft)]">
      <Container className="py-16 sm:py-24">
        <div className="grid grid-cols-2 gap-y-12 gap-x-8 sm:grid-cols-3 lg:grid-cols-[1.4fr_repeat(4,1fr)] lg:gap-x-10">
          <div className="col-span-2 sm:col-span-3 lg:col-span-1">
            <span className="font-mono-label text-[15px] font-semibold tracking-[0.08em]">
              TECHBISS
            </span>
            <p className="mt-4 max-w-[240px] text-[14px] leading-relaxed text-[var(--color-ink-muted)]">
              Digital transformation for businesses ready to move forward.
            </p>
            <Link
              href="/contact"
              className="mt-6 inline-flex items-center gap-1.5 text-[13px] font-medium text-[var(--color-ink)] hover:text-[var(--color-accent-ink)]"
            >
              Talk to TECHBISS
              <ArrowUpRight className="size-3.5" />
            </Link>
          </div>

          {COLUMNS.map((col) => (
            <div key={col.title}>
              <h4 className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
                {col.title}
              </h4>
              <ul className="mt-5 space-y-3">
                {col.links.map((link) => (
                  <li key={link.label}>
                    <Link
                      href={link.href}
                      className="text-[13.5px] text-[var(--color-ink-muted)] transition-colors hover:text-[var(--color-ink)]"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-16 flex flex-col gap-4 border-t border-[var(--color-border)] pt-8 text-[12.5px] text-[var(--color-ink-faint)] sm:flex-row sm:items-center sm:justify-between">
          <span>© 2026 TECHBISS. All rights reserved.</span>
          <div className="flex items-center gap-6">
            <Link href="/contact" className="hover:text-[var(--color-ink-muted)]">
              Privacy
            </Link>
            <Link href="/contact" className="hover:text-[var(--color-ink-muted)]">
              Terms
            </Link>
            <span className="inline-flex items-center gap-1.5">
              <span className="size-1.5 rounded-full bg-[var(--color-live)]" />
              All systems operational
            </span>
          </div>
        </div>
      </Container>
    </footer>
  );
}
