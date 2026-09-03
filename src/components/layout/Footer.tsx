"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { IconGithub, IconLinkedIn, IconX } from "@/components/ui/SocialIcons";
import { Logo } from "@/components/ui/Logo";
import { Container } from "@/components/ui/Container";
import { footerNav } from "@/lib/data/nav";

const columns: { title: string; links: { label: string; href: string }[] }[] = [
  { title: "Company", links: footerNav.company },
  { title: "Services", links: footerNav.services },
  { title: "Marketplace", links: footerNav.marketplace },
  { title: "Access", links: footerNav.access },
];

export function Footer() {
  const pathname = usePathname();
  if (pathname?.startsWith("/dashboard")) return null;

  return (
    <footer className="border-t border-(--color-border) bg-(--color-surface)">
      <Container size="wide" className="py-16 sm:py-20">
        <div className="grid grid-cols-2 gap-x-6 gap-y-12 sm:grid-cols-6 lg:grid-cols-12">
          <div className="col-span-2 flex flex-col gap-4 sm:col-span-6 lg:col-span-4">
            <Logo />
            <p className="max-w-xs text-sm leading-relaxed text-(--color-ink-muted)">
              TECHBISS engineers digital transformation for the world&apos;s most ambitious companies — product,
              platform and marketplace, in one place.
            </p>
            <div className="flex items-center gap-2">
              {[
                { icon: IconX, label: "Twitter" },
                { icon: IconLinkedIn, label: "LinkedIn" },
                { icon: IconGithub, label: "GitHub" },
              ].map(({ icon: Icon, label }) => (
                <a
                  key={label}
                  href="#"
                  aria-label={label}
                  className="focus-ring flex h-9 w-9 items-center justify-center rounded-full border border-(--color-border-strong) text-(--color-ink-muted) transition-colors hover:border-(--color-accent) hover:text-(--color-ink)"
                >
                  <Icon className="h-4 w-4" />
                </a>
              ))}
            </div>
          </div>

          {columns.map((col) => (
            <div key={col.title} className="col-span-1 sm:col-span-2 lg:col-span-2">
              <h4 className="mb-4 text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">
                {col.title}
              </h4>
              <ul className="flex flex-col gap-3">
                {col.links.map((link) => (
                  <li key={link.label}>
                    <Link
                      href={link.href}
                      className="focus-ring text-sm text-(--color-ink-muted) transition-colors hover:text-(--color-ink)"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-14 flex flex-col items-center gap-4 border-t border-(--color-border) pt-8 sm:flex-row sm:justify-between">
          <p className="text-xs text-(--color-ink-faint)">© {new Date().getFullYear()} TECHBISS, Inc. All rights reserved.</p>
          <div className="flex items-center gap-6">
            <Link href="/contact" className="text-xs text-(--color-ink-faint) transition-colors hover:text-(--color-ink-muted)">
              Privacy Policy
            </Link>
            <Link href="/contact" className="text-xs text-(--color-ink-faint) transition-colors hover:text-(--color-ink-muted)">
              Terms of Service
            </Link>
            <Link href="/contact" className="text-xs text-(--color-ink-faint) transition-colors hover:text-(--color-ink-muted)">
              Security
            </Link>
          </div>
        </div>
      </Container>
    </footer>
  );
}
